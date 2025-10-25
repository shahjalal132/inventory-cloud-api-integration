<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;
use BOILERPLATE\Inc\Enums\Status_Enums;

class Retry {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        // Register REST API endpoints
        add_action( 'rest_api_init', [ $this, 'register_retry_endpoints' ] );
        
        // AJAX handlers for enable/disable toggles
        add_action( 'wp_ajax_toggle_order_retry', [ $this, 'handle_toggle_order_retry' ] );
        add_action( 'wp_ajax_toggle_sales_return_retry', [ $this, 'handle_toggle_sales_return_retry' ] );
        
        // AJAX handlers for instant retry
        add_action( 'wp_ajax_instant_order_retry', [ $this, 'handle_instant_order_retry' ] );
        add_action( 'wp_ajax_instant_sales_return_retry', [ $this, 'handle_instant_sales_return_retry' ] );
        
        // AJAX handler for stats
        add_action( 'wp_ajax_get_retry_stats', [ $this, 'handle_get_retry_stats' ] );
    }

    /**
     * Register REST API endpoints
     */
    public function register_retry_endpoints() {
        // Order retry endpoint
        register_rest_route( 'atebol/v1', '/order-retry', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_order_retry' ],
            'permission_callback' => '__return_true',
        ] );

        // Sales return retry endpoint
        register_rest_route( 'atebol/v1', '/sales-return-retry', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_sales_return_retry' ],
            'permission_callback' => '__return_true',
        ] );

        // Retry stats endpoint
        register_rest_route( 'atebol/v1', '/retry-stats', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_retry_stats' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle order retry endpoint
     */
    public function handle_order_retry( $request ) {
        // Check if retry is enabled
        $is_enabled = get_option( 'wasp_order_retry_enable', false );
        
        if ( ! $is_enabled ) {
            return new \WP_REST_Response( [
                'message' => 'Order retry is disabled.',
                'enabled' => false,
            ], 200 );
        }

        return $this->process_order_retry();
    }

    /**
     * Handle sales return retry endpoint
     */
    public function handle_sales_return_retry( $request ) {
        // Check if retry is enabled
        $is_enabled = get_option( 'wasp_sales_return_retry_enable', false );
        
        if ( ! $is_enabled ) {
            return new \WP_REST_Response( [
                'message' => 'Sales return retry is disabled.',
                'enabled' => false,
            ], 200 );
        }

        return $this->process_sales_return_retry();
    }

    /**
     * Process order retry (fetch FAILED/IGNORED items and store in retry table)
     */
    private function process_order_retry( $limit = 50 ) {
        global $wpdb;
        $source_table = $wpdb->prefix . 'sync_wasp_woo_orders_data';
        $retry_table  = $wpdb->prefix . 'sync_wasp_retry_items';

        // Fetch FAILED and IGNORED items
        $failed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $source_table WHERE status IN (%s, %s) LIMIT %d",
                Status_Enums::FAILED->value,
                Status_Enums::IGNORED->value,
                $limit
            )
        );

        if ( empty( $failed_items ) ) {
            return new \WP_REST_Response( [
                'message' => 'No failed or ignored order items found.',
                'count'   => 0,
            ], 200 );
        }

        $added_count = 0;
        $errors      = [];

        foreach ( $failed_items as $item ) {
            // Check if already exists in retry table
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $retry_table WHERE original_id = %d AND item_type = 'order'",
                    $item->id
                )
            );

            if ( $exists > 0 ) {
                continue; // Skip if already in retry queue
            }

            // Insert into retry table
            $result = $wpdb->insert(
                $retry_table,
                [
                    'original_id'     => $item->id,
                    'item_type'       => 'order',
                    'item_number'     => $item->item_number,
                    'original_status' => $item->status,
                    'retry_status'    => 'PENDING',
                    'retry_count'     => 0,
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%d' ]
            );

            if ( $result ) {
                $added_count++;
                
                // Now try to re-process this item
                $this->retry_single_order( $item->id );
            } else {
                $errors[] = [
                    'item_id' => $item->id,
                    'error'   => $wpdb->last_error,
                ];
            }
        }

        return new \WP_REST_Response( [
            'message'      => sprintf( 'Added %d order items to retry queue.', $added_count ),
            'added_count'  => $added_count,
            'total_found'  => count( $failed_items ),
            'errors'       => $errors,
        ], 200 );
    }

    /**
     * Process sales return retry (fetch FAILED/IGNORED items and store in retry table)
     */
    private function process_sales_return_retry( $limit = 50 ) {
        global $wpdb;
        $source_table = $wpdb->prefix . 'sync_sales_returns_data';
        $retry_table  = $wpdb->prefix . 'sync_wasp_retry_items';

        // Fetch FAILED and IGNORED items
        $failed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $source_table WHERE status IN (%s, %s) LIMIT %d",
                Status_Enums::FAILED->value,
                Status_Enums::IGNORED->value,
                $limit
            )
        );

        if ( empty( $failed_items ) ) {
            return new \WP_REST_Response( [
                'message' => 'No failed or ignored sales return items found.',
                'count'   => 0,
            ], 200 );
        }

        $added_count = 0;
        $errors      = [];

        foreach ( $failed_items as $item ) {
            // Check if already exists in retry table
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $retry_table WHERE original_id = %d AND item_type = 'sales_return'",
                    $item->id
                )
            );

            if ( $exists > 0 ) {
                continue; // Skip if already in retry queue
            }

            // Insert into retry table
            $result = $wpdb->insert(
                $retry_table,
                [
                    'original_id'     => $item->id,
                    'item_type'       => 'sales_return',
                    'item_number'     => $item->item_number,
                    'original_status' => $item->status,
                    'retry_status'    => 'PENDING',
                    'retry_count'     => 0,
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%d' ]
            );

            if ( $result ) {
                $added_count++;
                
                // Now try to re-process this item
                $this->retry_single_sales_return( $item->id );
            } else {
                $errors[] = [
                    'item_id' => $item->id,
                    'error'   => $wpdb->last_error,
                ];
            }
        }

        return new \WP_REST_Response( [
            'message'      => sprintf( 'Added %d sales return items to retry queue.', $added_count ),
            'added_count'  => $added_count,
            'total_found'  => count( $failed_items ),
            'errors'       => $errors,
        ], 200 );
    }

    /**
     * Retry a single order item
     */
    private function retry_single_order( $order_id ) {
        global $wpdb;
        $source_table = $wpdb->prefix . 'sync_wasp_woo_orders_data';
        $retry_table  = $wpdb->prefix . 'sync_wasp_retry_items';

        // Get the order item
        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $source_table WHERE id = %d", $order_id )
        );

        if ( ! $item ) {
            return false;
        }

        // Update the original item status back to PENDING to trigger re-processing
        $wpdb->update(
            $source_table,
            [ 'status' => Status_Enums::PENDING->value ],
            [ 'id' => $order_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Update retry table
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $retry_table SET retry_count = retry_count + 1, last_retry_at = NOW(), retry_status = 'PROCESSING' WHERE original_id = %d AND item_type = 'order'",
                $order_id
            )
        );

        return true;
    }

    /**
     * Retry a single sales return item
     */
    private function retry_single_sales_return( $sales_return_id ) {
        global $wpdb;
        $source_table = $wpdb->prefix . 'sync_sales_returns_data';
        $retry_table  = $wpdb->prefix . 'sync_wasp_retry_items';

        // Get the sales return item
        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $source_table WHERE id = %d", $sales_return_id )
        );

        if ( ! $item ) {
            return false;
        }

        // Update the original item status back to PENDING to trigger re-processing
        $wpdb->update(
            $source_table,
            [ 'status' => Status_Enums::PENDING->value ],
            [ 'id' => $sales_return_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Update retry table
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $retry_table SET retry_count = retry_count + 1, last_retry_at = NOW(), retry_status = 'PROCESSING' WHERE original_id = %d AND item_type = 'sales_return'",
                $sales_return_id
            )
        );

        return true;
    }

    /**
     * Handle retry stats endpoint
     */
    public function handle_retry_stats( $request ) {
        global $wpdb;
        $orders_table        = $wpdb->prefix . 'sync_wasp_woo_orders_data';
        $sales_returns_table = $wpdb->prefix . 'sync_sales_returns_data';
        $retry_table         = $wpdb->prefix . 'sync_wasp_retry_items';

        // Orders stats
        $orders_ignored = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $orders_table WHERE status = %s", Status_Enums::IGNORED->value )
        );
        $orders_failed = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $orders_table WHERE status = %s", Status_Enums::FAILED->value )
        );
        $orders_retried = $wpdb->get_var(
            "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'order'"
        );
        $orders_retry_success = $wpdb->get_var(
            "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'order' AND retry_status = 'COMPLETED'"
        );

        // Sales returns stats
        $sales_ignored = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $sales_returns_table WHERE status = %s", Status_Enums::IGNORED->value )
        );
        $sales_failed = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $sales_returns_table WHERE status = %s", Status_Enums::FAILED->value )
        );
        $sales_retried = $wpdb->get_var(
            "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'sales_return'"
        );
        $sales_retry_success = $wpdb->get_var(
            "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'sales_return' AND retry_status = 'COMPLETED'"
        );

        return new \WP_REST_Response( [
            'orders' => [
                'ignored'        => intval( $orders_ignored ),
                'failed'         => intval( $orders_failed ),
                'total_issues'   => intval( $orders_ignored ) + intval( $orders_failed ),
                'retried'        => intval( $orders_retried ),
                'retry_success'  => intval( $orders_retry_success ),
            ],
            'sales_returns' => [
                'ignored'        => intval( $sales_ignored ),
                'failed'         => intval( $sales_failed ),
                'total_issues'   => intval( $sales_ignored ) + intval( $sales_failed ),
                'retried'        => intval( $sales_retried ),
                'retry_success'  => intval( $sales_retry_success ),
            ],
        ], 200 );
    }

    /**
     * Handle toggle order retry AJAX
     */
    public function handle_toggle_order_retry() {
        check_ajax_referer( 'wasp-retry-nonce', 'nonce' );

        $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === 'true';
        update_option( 'wasp_order_retry_enable', $enabled );

        wp_send_json_success( [
            'message' => $enabled ? 'Order retry enabled.' : 'Order retry disabled.',
            'enabled' => $enabled,
        ] );
    }

    /**
     * Handle toggle sales return retry AJAX
     */
    public function handle_toggle_sales_return_retry() {
        check_ajax_referer( 'wasp-retry-nonce', 'nonce' );

        $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === 'true';
        update_option( 'wasp_sales_return_retry_enable', $enabled );

        wp_send_json_success( [
            'message' => $enabled ? 'Sales return retry enabled.' : 'Sales return retry disabled.',
            'enabled' => $enabled,
        ] );
    }

    /**
     * Handle instant order retry AJAX
     */
    public function handle_instant_order_retry() {
        check_ajax_referer( 'wasp-retry-nonce', 'nonce' );

        $response = $this->process_order_retry();
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        wp_send_json_success( $response->data );
    }

    /**
     * Handle instant sales return retry AJAX
     */
    public function handle_instant_sales_return_retry() {
        check_ajax_referer( 'wasp-retry-nonce', 'nonce' );

        $response = $this->process_sales_return_retry();
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        wp_send_json_success( $response->data );
    }

    /**
     * Handle get retry stats AJAX
     */
    public function handle_get_retry_stats() {
        check_ajax_referer( 'wasp-retry-nonce', 'nonce' );

        $response = $this->handle_retry_stats( null );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        wp_send_json_success( $response->data );
    }

}