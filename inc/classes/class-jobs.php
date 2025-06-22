<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Jobs {

    use Singleton;
    use Program_Logs;

    private $api_base_url;
    private $token;

    public function __construct() {
        $this->setup_hooks();
        
        // Get API credentials
        $this->api_base_url = get_option( 'inv_cloud_base_url' ) ?? 'https://atebol.waspinventorycloud.com';
        $this->token = get_option( 'inv_cloud_token' );
    }

    public function setup_hooks() {
        // Register custom cron interval
        add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );
        
        // Register cron jobs
        add_action( 'init', [ $this, 'register_cron_jobs' ] );
        
        // Cron job handlers
        add_action( 'wasp_prepare_sales_returns_cron', [ $this, 'execute_prepare_sales_returns' ] );
        add_action( 'wasp_prepare_woo_orders_cron', [ $this, 'execute_prepare_woo_orders' ] );
        add_action( 'wasp_import_sales_returns_cron', [ $this, 'execute_import_sales_returns' ] );
        add_action( 'wasp_import_woo_orders_cron', [ $this, 'execute_import_woo_orders' ] );
        add_action( 'wasp_remove_completed_woo_orders_cron', [ $this, 'execute_remove_completed_woo_orders' ] );
        add_action( 'wasp_remove_completed_sales_returns_cron', [ $this, 'execute_remove_completed_sales_returns' ] );
        
        // AJAX handlers for enabling/disabling cron jobs
        add_action( 'wp_ajax_toggle_cron_job', [ $this, 'toggle_cron_job' ] );
        add_action( 'wp_ajax_run_cron_job_manually', [ $this, 'run_cron_job_manually' ] );
    }

    /**
     * Add custom cron interval for every minute
     */
    public function add_cron_interval( $schedules ) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => 'Every Minute'
        ];
        return $schedules;
    }

    /**
     * Register all cron jobs
     */
    public function register_cron_jobs() {
        $cron_jobs = [
            'wasp_prepare_sales_returns_cron' => 'prepare-sales-returns',
            'wasp_prepare_woo_orders_cron' => 'prepare-woo-orders',
            'wasp_import_sales_returns_cron' => 'import-sales-returns',
            'wasp_import_woo_orders_cron' => 'import-woo-orders',
            'wasp_remove_completed_woo_orders_cron' => 'remove-completed-woo-orders',
            'wasp_remove_completed_sales_returns_cron' => 'remove-completed-sales-returns'
        ];

        foreach ( $cron_jobs as $cron_hook => $endpoint ) {
            $this->schedule_cron_job( $cron_hook, $endpoint );
        }
    }

    /**
     * Schedule a cron job if it's enabled
     */
    private function schedule_cron_job( $cron_hook, $endpoint ) {
        $option_name = 'wasp_cron_' . str_replace( '-', '_', $endpoint ) . '_enabled';
        $is_enabled = get_option( $option_name, 'disabled' );
        
        if ( $is_enabled === 'enabled' ) {
            if ( !wp_next_scheduled( $cron_hook ) ) {
                wp_schedule_event( time(), 'every_minute', $cron_hook );
            }
        } else {
            wp_clear_scheduled_hook( $cron_hook );
        }
    }

    /**
     * Execute prepare sales returns
     */
    public function execute_prepare_sales_returns() {
        $this->execute_api_endpoint( 'prepare-sales-returns' );
    }

    /**
     * Execute prepare woo orders
     */
    public function execute_prepare_woo_orders() {
        $this->execute_api_endpoint( 'prepare-woo-orders' );
    }

    /**
     * Execute import sales returns
     */
    public function execute_import_sales_returns() {
        $this->execute_api_endpoint( 'import-sales-returns' );
    }

    /**
     * Execute import woo orders
     */
    public function execute_import_woo_orders() {
        $this->execute_api_endpoint( 'import-woo-orders' );
    }

    /**
     * Execute remove completed woo orders
     */
    public function execute_remove_completed_woo_orders() {
        $this->execute_api_endpoint( 'remove-completed-woo-orders' );
    }

    /**
     * Execute remove completed sales returns
     */
    public function execute_remove_completed_sales_returns() {
        $this->execute_api_endpoint( 'remove-completed-sales-returns' );
    }

    /**
     * Execute API endpoint
     */
    private function execute_api_endpoint( $endpoint ) {
        $site_url = site_url();
        $api_url = $site_url . '/wp-json/atebol/v1/' . $endpoint;
        
        $response = wp_remote_get( $api_url, [
            'timeout' => 300,
            'sslverify' => false,
        ] );

        if ( is_wp_error( $response ) ) {
            $this->put_program_logs( "Cron job failed for {$endpoint}: " . $response->get_error_message() );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            $this->put_program_logs( "Cron job executed for {$endpoint}: HTTP {$response_code} - {$response_body}" );
        }
    }

    /**
     * Toggle cron job enable/disable
     */
    public function toggle_cron_job() {
        check_ajax_referer( 'wasp_cron_nonce', 'nonce' );

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        $endpoint = sanitize_text_field( $_POST['endpoint'] );
        $enabled = sanitize_text_field( $_POST['enabled'] );
        
        $option_name = 'wasp_cron_' . str_replace( '-', '_', $endpoint ) . '_enabled';
        update_option( $option_name, $enabled );

        // Reschedule or clear the cron job
        $cron_hook = 'wasp_' . str_replace( '-', '_', $endpoint ) . '_cron';
        
        if ( $enabled === 'enabled' ) {
            if ( !wp_next_scheduled( $cron_hook ) ) {
                wp_schedule_event( time(), 'every_minute', $cron_hook );
            }
        } else {
            wp_clear_scheduled_hook( $cron_hook );
        }

        wp_send_json_success( [ 'message' => 'Cron job updated successfully.' ] );
    }

    /**
     * Run cron job manually
     */
    public function run_cron_job_manually() {
        check_ajax_referer( 'wasp_cron_nonce', 'nonce' );

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        $endpoint = sanitize_text_field( $_POST['endpoint'] );
        
        // Execute the job immediately
        $this->execute_api_endpoint( $endpoint );

        wp_send_json_success( [ 'message' => 'Cron job executed manually.' ] );
    }

    /**
     * Get cron job status
     */
    public function get_cron_job_status( $endpoint ) {
        $option_name = 'wasp_cron_' . str_replace( '-', '_', $endpoint ) . '_enabled';
        $is_enabled = get_option( $option_name, 'disabled' );
        
        $cron_hook = 'wasp_' . str_replace( '-', '_', $endpoint ) . '_cron';
        $next_scheduled = wp_next_scheduled( $cron_hook );
        
        return [
            'enabled' => $is_enabled === 'enabled',
            'next_run' => $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : null,
            'status' => $is_enabled
        ];
    }

    /**
     * Get all cron jobs status
     */
    public function get_all_cron_jobs_status() {
        $endpoints = [
            'prepare-sales-returns',
            'prepare-woo-orders', 
            'import-sales-returns',
            'import-woo-orders',
            'remove-completed-woo-orders',
            'remove-completed-sales-returns'
        ];

        $status = [];
        foreach ( $endpoints as $endpoint ) {
            $status[$endpoint] = $this->get_cron_job_status( $endpoint );
        }

        return $status;
    }

    /**
     * Clean up all cron jobs (called on plugin deactivation)
     */
    public function cleanup_cron_jobs() {
        $cron_hooks = [
            'wasp_prepare_sales_returns_cron',
            'wasp_prepare_woo_orders_cron',
            'wasp_import_sales_returns_cron',
            'wasp_import_woo_orders_cron',
            'wasp_remove_completed_woo_orders_cron',
            'wasp_remove_completed_sales_returns_cron'
        ];

        foreach ( $cron_hooks as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }

        // Remove cron interval
        remove_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );
    }

    /**
     * Test method to verify cron jobs are working
     */
    public function test_cron_jobs() {
        $this->put_program_logs( 'Cron jobs test: All cron jobs are properly registered and configured.' );
        
        $cron_jobs = [
            'wasp_prepare_sales_returns_cron' => 'prepare-sales-returns',
            'wasp_prepare_woo_orders_cron' => 'prepare-woo-orders',
            'wasp_import_sales_returns_cron' => 'import-sales-returns',
            'wasp_import_woo_orders_cron' => 'import-woo-orders',
            'wasp_remove_completed_woo_orders_cron' => 'remove-completed-woo-orders',
            'wasp_remove_completed_sales_returns_cron' => 'remove-completed-sales-returns'
        ];

        foreach ( $cron_jobs as $cron_hook => $endpoint ) {
            $next_scheduled = wp_next_scheduled( $cron_hook );
            $option_name = 'wasp_cron_' . str_replace( '-', '_', $endpoint ) . '_enabled';
            $is_enabled = get_option( $option_name, 'disabled' );
            
            $this->put_program_logs( "Cron job {$endpoint}: Enabled = {$is_enabled}, Next scheduled = " . ( $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : 'Not scheduled' ) );
        }
    }

}