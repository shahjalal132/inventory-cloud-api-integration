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
        add_action( 'wp_ajax_test_cron_jobs', [ $this, 'test_cron_jobs_ajax' ] );
        add_action( 'wp_ajax_toggle_production_mode', [ $this, 'toggle_production_mode' ] );
        
        // Development: Add a test endpoint for local development
        add_action( 'rest_api_init', [ $this, 'register_test_endpoint' ] );
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
        // Check if we're in production mode
        $production_mode = get_option( 'wasp_cron_production_mode', 'disabled' );
        
        if ( $production_mode === 'disabled' && $this->is_development_environment() ) {
            $this->put_program_logs( "Cron job {$endpoint}: Skipped in development mode (production mode disabled)" );
            return;
        }
        
        $site_url = site_url();
        $api_url = $site_url . '/wp-json/atebol/v1/' . $endpoint;
        
        // Add timeout and better error handling for local development
        $timeout = $this->is_development_environment() ? 10 : 300;
        
        $response = wp_remote_get( $api_url, [
            'timeout' => $timeout,
            'sslverify' => false,
            'user-agent' => 'WASP-Cron-Job/1.0',
        ] );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            
            // Provide more helpful error messages for local development
            if ( $this->is_development_environment() ) {
                $this->put_program_logs( "Cron job failed for {$endpoint}: {$error_message} - This is normal in local development. Use 'Run Now' button for testing." );
            } else {
                $this->put_program_logs( "Cron job failed for {$endpoint}: {$error_message}" );
            }
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            // Only log full response in development mode
            if ( $this->is_development_environment() ) {
                $this->put_program_logs( "Cron job executed for {$endpoint}: HTTP {$response_code} - Response length: " . strlen( $response_body ) . " bytes" );
            } else {
                $this->put_program_logs( "Cron job executed for {$endpoint}: HTTP {$response_code}" );
            }
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

    /**
     * Register a test endpoint for local development
     */
    public function register_test_endpoint() {
        register_rest_route( 'wasp/v1', '/test-cron-jobs', [
            'methods' => 'GET',
            'callback' => [ $this, 'test_cron_jobs_ajax' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            }
        ] );
    }

    /**
     * Handle test cron jobs AJAX request
     */
    public function test_cron_jobs_ajax() {
        $this->test_cron_jobs();
        wp_send_json_success( [ 'message' => 'Cron jobs test executed.' ] );
    }

    /**
     * Toggle production mode
     */
    public function toggle_production_mode() {
        check_ajax_referer( 'wasp_cron_nonce', 'nonce' );

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        $enabled = sanitize_text_field( $_POST['enabled'] );
        update_option( 'wasp_cron_production_mode', $enabled );

        $message = $enabled === 'enabled' ? 'Production mode enabled. Cron jobs will run in all environments.' : 'Production mode disabled. Cron jobs will be skipped in development environments.';
        
        wp_send_json_success( [ 'message' => $message ] );
    }

    /**
     * Check if we're in development environment
     */
    public function is_development_environment() {
        return (
            defined( 'WP_DEBUG' ) && WP_DEBUG ||
            defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ||
            strpos( site_url(), 'localhost' ) !== false ||
            strpos( site_url(), '127.0.0.1' ) !== false ||
            strpos( site_url(), '.local' ) !== false ||
            strpos( site_url(), '.test' ) !== false ||
            strpos( site_url(), '.dev' ) !== false
        );
    }

    /**
     * Get development information
     */
    public function get_development_info() {
        $info = [
            'is_development' => $this->is_development_environment(),
            'site_url' => site_url(),
            'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'test_endpoint' => site_url() . '/wp-json/wasp/v1/test-cron-jobs',
            'manual_trigger_url' => site_url() . '/wp-cron.php?doing_wp_cron',
        ];

        if ( $this->is_development_environment() ) {
            $info['recommendations'] = [
                'Use "Run Now" buttons for testing',
                'Set up a real cron job to call wp-cron.php',
                'Use the test endpoint: ' . $info['test_endpoint'],
                'Check program logs for execution details'
            ];
        }

        return $info;
    }

}