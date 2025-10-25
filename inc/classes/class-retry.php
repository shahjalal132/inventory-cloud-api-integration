<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;
use BOILERPLATE\Inc\Enums\Status_Enums;

/**
 * Retry class for handling FAILED and IGNORED items
 * 
 * This class extends Wasp_Rest_Api to reuse API communication methods
 * and implements retry logic following the same patterns as the main processing
 */
class Retry extends Wasp_Rest_Api {

    // use Singleton;
    // use Program_Logs;

    private $api_base_url;
    private $token;
    private $timeout = 60;

    public function __construct() {

        // get api credentials
        $this->api_base_url = get_option( 'inv_cloud_base_url' ) ?? '';
        $this->token        = get_option( 'inv_cloud_token' );

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

        // AJAX handler for truncate table
        add_action( 'wp_ajax_truncate_table', [ $this, 'handle_truncate_table' ] );
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

        if ( !$is_enabled ) {
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

        if ( !$is_enabled ) {
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

        // Get last processed ID to track progress
        $last_processed_id = get_option( 'wasp_order_retry_last_processed_id', 0 );

        // Fetch FAILED and IGNORED items ordered by ID ASC for sequential processing
        $failed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $source_table WHERE status IN (%s, %s) AND id > %d ORDER BY id ASC LIMIT %d",
                Status_Enums::FAILED->value,
                Status_Enums::IGNORED->value,
                $last_processed_id,
                $limit
            )
        );

        if ( empty( $failed_items ) ) {
            // Reset last processed ID when no more items found
            delete_option( 'wasp_order_retry_last_processed_id' );
            
            return new \WP_REST_Response( [
                'message' => 'No failed or ignored order items found.',
                'count'   => 0,
            ], 200 );
        }

        $total_processed = 0;
        $success_count   = 0;
        $error_count     = 0;
        $ignored_count   = 0;
        $results         = [];

        foreach ( $failed_items as $item ) {
            $total_processed++;

            // Check if item already in retry queue with PENDING status
            $retry_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $retry_table WHERE original_id = %d AND item_type = 'order' AND retry_status = %s",
                    $item->id,
                    Status_Enums::PENDING->value
                )
            );

            // Skip if already in retry queue
            if ( $retry_exists > 0 ) {
                continue;
            }

            // Validate item number (if not numeric, blank, or null, ignore it)
            if ( !is_numeric( $item->item_number ) || empty( $item->item_number ) ) {
                $ignored_count++;
                
                // Track in retry table
                $wpdb->insert(
                    $retry_table,
                    [
                        'original_id'     => $item->id,
                        'item_type'       => 'order',
                        'item_number'     => $item->item_number ?? '',
                        'original_status' => $item->status,
                        'retry_status'    => Status_Enums::IGNORED->value,
                        'retry_count'     => 1,
                        'retry_message'   => 'Item number is not correct or empty',
                        'last_retry_at'   => current_time( 'mysql' ),
                    ],
                    [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                );

                $results[] = [
                    'item_id'       => $item->id,
                    'item_number'   => $item->item_number,
                    'result'        => 'ignored',
                    'error_message' => 'Item number is not correct or empty',
                ];
                continue;
            }

            // Get item details from API (following handle_prepare_woo_orders logic)
            $api_result = $this->get_item_details_api( $this->token, $item->item_number );

            if ( $api_result['result'] === 'success' ) {
                // Extract api response data
                $response_data = json_decode( $api_result['api_response'], true );

                // Update api_response in source table
                $wpdb->update(
                    $source_table,
                    [ 'api_response' => $api_result['api_response'] ],
                    [ 'id' => $item->id ]
                );

                $site_name      = '';
                $location_code  = '';
                $found_non_cllc = false;

                if ( isset( $response_data['Data'] ) && is_array( $response_data['Data'] ) && count( $response_data['Data'] ) > 0 ) {
                    foreach ( $response_data['Data'] as $loc ) {
                        $site = strtoupper( $loc['SiteName'] ?? '' );
                        $code = strtoupper( $loc['LocationCode'] ?? '' );

                        // If either SiteName or LocationCode is NOT CLLC, accept it
                        if ( $site !== 'CLLC' && $code !== 'CLLC' ) {
                            $site_name      = $loc['SiteName'] ?? '';
                            $location_code  = $loc['LocationCode'] ?? '';
                            $found_non_cllc = true;
                            break;
                        }
                    }

                    // Update DB if non-CLLC location found
                    if ( $found_non_cllc ) {
                        $update_result = $wpdb->update(
                            $source_table,
                            [
                                'site_name'     => $site_name,
                                'location_code' => $location_code,
                                'status'        => Status_Enums::READY->value,
                            ],
                            [ 'id' => $item->id ]
                        );

                        if ( $update_result !== false ) {
                            $success_count++;

                            // Track successful retry
                            $wpdb->insert(
                                $retry_table,
                                [
                                    'original_id'     => $item->id,
                                    'item_type'       => 'order',
                                    'item_number'     => $item->item_number,
                                    'original_status' => $item->status,
                                    'retry_status'    => Status_Enums::READY->value,
                                    'retry_count'     => 1,
                                    'retry_message'   => 'Successfully retried and set to READY',
                                    'last_retry_at'   => current_time( 'mysql' ),
                                ],
                                [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                            );

                            $results[] = [
                                'item_id'       => $item->id,
                                'item_number'   => $item->item_number,
                                'result'        => 'success',
                                'site_name'     => $site_name,
                                'location_code' => $location_code,
                            ];
                        } else {
                            $error_count++;
                            
                            $wpdb->insert(
                                $retry_table,
                                [
                                    'original_id'     => $item->id,
                                    'item_type'       => 'order',
                                    'item_number'     => $item->item_number,
                                    'original_status' => $item->status,
                                    'retry_status'    => Status_Enums::FAILED->value,
                                    'retry_count'     => 1,
                                    'retry_message'   => 'Database update failed',
                                    'last_retry_at'   => current_time( 'mysql' ),
                                ],
                                [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                            );

                            $results[] = [
                                'item_id'       => $item->id,
                                'item_number'   => $item->item_number,
                                'result'        => 'error',
                                'error_message' => 'Database update failed',
                            ];
                        }
                    } else {
                        // No non-CLLC location found — IGNORE
                        $wpdb->update(
                            $source_table,
                            [
                                'status'       => Status_Enums::IGNORED->value,
                                'api_response' => $api_result['api_response'],
                                'message'      => 'Only CLLC locations found',
                            ],
                            [ 'id' => $item->id ]
                        );

                        $ignored_count++;

                        $wpdb->insert(
                            $retry_table,
                            [
                                'original_id'     => $item->id,
                                'item_type'       => 'order',
                                'item_number'     => $item->item_number,
                                'original_status' => $item->status,
                                'retry_status'    => Status_Enums::IGNORED->value,
                                'retry_count'     => 1,
                                'retry_message'   => 'Only CLLC locations found',
                                'last_retry_at'   => current_time( 'mysql' ),
                            ],
                            [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                        );

                        $results[] = [
                            'item_id'       => $item->id,
                            'item_number'   => $item->item_number,
                            'result'        => 'ignored',
                            'error_message' => 'Only CLLC locations found. Ignored.',
                        ];
                    }
                } else {
                    // No item data found in API response — update status to FAILED
                    $wpdb->update( $source_table, [ 'status' => Status_Enums::FAILED->value ], [ 'id' => $item->id ] );

                    $error_count++;

                    $wpdb->insert(
                        $retry_table,
                        [
                            'original_id'     => $item->id,
                            'item_type'       => 'order',
                            'item_number'     => $item->item_number,
                            'original_status' => $item->status,
                            'retry_status'    => Status_Enums::FAILED->value,
                            'retry_count'     => 1,
                            'retry_message'   => 'No item data found in API response',
                            'last_retry_at'   => current_time( 'mysql' ),
                        ],
                        [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                    );

                    $results[] = [
                        'item_id'       => $item->id,
                        'item_number'   => $item->item_number,
                        'result'        => 'error',
                        'error_message' => 'No item data found in API response',
                    ];
                }
            } else {
                // API call failed
                $error_count++;

                $wpdb->insert(
                    $retry_table,
                    [
                        'original_id'     => $item->id,
                        'item_type'       => 'order',
                        'item_number'     => $item->item_number,
                        'original_status' => $item->status,
                        'retry_status'    => Status_Enums::FAILED->value,
                        'retry_count'     => 1,
                        'retry_message'   => $api_result['error_message'] ?? 'API call failed',
                        'last_retry_at'   => current_time( 'mysql' ),
                    ],
                    [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                );

                $results[] = [
                    'item_id'       => $item->id,
                    'item_number'   => $item->item_number,
                    'result'        => 'error',
                    'error_message' => $api_result['error_message'] ?? 'API call failed',
                ];
            }

            // Update last processed ID after each item
            update_option( 'wasp_order_retry_last_processed_id', $item->id );
        }

        // Prepare summary message
        $summary_message = sprintf(
            'Total %d items processed. %d items updated. %d items failed. %d items ignored.',
            $total_processed,
            $success_count,
            $error_count,
            $ignored_count
        );

        // Set response code
        $http_status = 200;
        if ( $error_count > 0 ) {
            $http_status = 207;
        }
        if ( $success_count === 0 ) {
            $http_status = 500;
        }

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => $total_processed,
                'success_count'   => $success_count,
                'error_count'     => $error_count,
                'ignored_count'   => $ignored_count,
            ],
            'results' => $results,
        ], $http_status );
    }

    /**
     * Process sales return retry (fetch FAILED/IGNORED items and store in retry table)
     */
    private function process_sales_return_retry( $limit = 50 ) {
        global $wpdb;
        $source_table = $wpdb->prefix . 'sync_sales_returns_data';
        $retry_table  = $wpdb->prefix . 'sync_wasp_retry_items';

        // Get last processed ID to track progress
        $last_processed_id = get_option( 'wasp_sales_return_retry_last_processed_id', 0 );

        // Fetch FAILED and IGNORED items ordered by ID ASC for sequential processing
        $failed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $source_table WHERE status IN (%s, %s) AND id > %d ORDER BY id ASC LIMIT %d",
                Status_Enums::FAILED->value,
                Status_Enums::IGNORED->value,
                $last_processed_id,
                $limit
            )
        );

        if ( empty( $failed_items ) ) {
            // Reset last processed ID when no more items found
            delete_option( 'wasp_sales_return_retry_last_processed_id' );
            
            return new \WP_REST_Response( [
                'message' => 'No failed or ignored sales return items found.',
                'count'   => 0,
            ], 200 );
        }

        $total_processed = 0;
        $success_count   = 0;
        $error_count     = 0;
        $ignored_count   = 0;
        $results         = [];

        foreach ( $failed_items as $item ) {
            $total_processed++;

            // Check if item already in retry queue with PENDING status
            $retry_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $retry_table WHERE original_id = %d AND item_type = 'sales_return' AND retry_status = %s",
                    $item->id,
                    Status_Enums::PENDING->value
                )
            );

            // Skip if already in retry queue
            if ( $retry_exists > 0 ) {
                continue;
            }

            // Check if the item number is numeric. If not, skip it and update status to IGNORED
            if ( !is_numeric( $item->item_number ) ) {
                $ignored_count++;

                // Track in retry table
                $wpdb->insert(
                    $retry_table,
                    [
                        'original_id'     => $item->id,
                        'item_type'       => 'sales_return',
                        'item_number'     => $item->item_number ?? '',
                        'original_status' => $item->status,
                        'retry_status'    => Status_Enums::IGNORED->value,
                        'retry_count'     => 1,
                        'retry_message'   => 'Item number is not numeric',
                        'last_retry_at'   => current_time( 'mysql' ),
                    ],
                    [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                );

                $results[] = [
                    'item_id'       => $item->id,
                    'item_number'   => $item->item_number,
                    'result'        => 'ignored',
                    'error_message' => 'Item number is not numeric',
                ];
                continue;
            }

            // Get item details from API (following handle_prepare_sales_returns logic)
            $api_result = $this->get_item_details_api( $this->token, $item->item_number );

            if ( $api_result['result'] === 'success' ) {
                $response_data = json_decode( $api_result['api_response'], true );

                // The item may be multiple location and site name. CLLC priority first.
                $site_name     = '';
                $location_code = '';
                $found_cllc    = false;

                if ( isset( $response_data['Data'] ) && is_array( $response_data['Data'] ) && count( $response_data['Data'] ) > 0 ) {
                    foreach ( $response_data['Data'] as $loc ) {
                        if (
                            ( isset( $loc['SiteName'] ) && strtoupper( $loc['SiteName'] ) === 'CLLC' ) ||
                            ( isset( $loc['LocationCode'] ) && strtoupper( $loc['LocationCode'] ) === 'CLLC' )
                        ) {
                            $site_name     = $loc['SiteName'] ?? '';
                            $location_code = $loc['LocationCode'] ?? '';
                            $found_cllc    = true;
                            break;
                        }
                    }

                    // If not found CLLC, use the first SiteName/LocationCode from API response
                    if ( !$found_cllc ) {
                        $first_loc     = $response_data['Data'][0];
                        $site_name     = $first_loc['SiteName'] ?? '';
                        $location_code = $first_loc['LocationCode'] ?? '';
                    }

                    // Update site_name, location_code and status to READY
                    $update_result = $wpdb->update(
                        $source_table,
                        [
                            'site_name'     => $site_name,
                            'location_code' => $location_code,
                            'status'        => Status_Enums::READY->value,
                        ],
                        [ 'id' => $item->id ]
                    );

                    if ( $update_result !== false ) {
                        $success_count++;

                        // Track successful retry
                        $wpdb->insert(
                            $retry_table,
                            [
                                'original_id'     => $item->id,
                                'item_type'       => 'sales_return',
                                'item_number'     => $item->item_number,
                                'original_status' => $item->status,
                                'retry_status'    => Status_Enums::READY->value,
                                'retry_count'     => 1,
                                'retry_message'   => 'Successfully retried and set to READY' . ( $found_cllc ? ' (used CLLC)' : '' ),
                                'last_retry_at'   => current_time( 'mysql' ),
                            ],
                            [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                        );

                        $results[] = [
                            'item_id'       => $item->id,
                            'item_number'   => $item->item_number,
                            'result'        => 'success',
                            'site_name'     => $site_name,
                            'location_code' => $location_code,
                            'used_cllc'     => $found_cllc ? 'yes' : 'no',
                        ];
                    } else {
                        $error_count++;

                        $wpdb->insert(
                            $retry_table,
                            [
                                'original_id'     => $item->id,
                                'item_type'       => 'sales_return',
                                'item_number'     => $item->item_number,
                                'original_status' => $item->status,
                                'retry_status'    => Status_Enums::FAILED->value,
                                'retry_count'     => 1,
                                'retry_message'   => 'Database update failed',
                                'last_retry_at'   => current_time( 'mysql' ),
                            ],
                            [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                        );

                        $results[] = [
                            'item_id'       => $item->id,
                            'item_number'   => $item->item_number,
                            'result'        => 'error',
                            'error_message' => 'Database update failed',
                        ];
                    }
                } else {
                    // Update status to ERROR
                    $wpdb->update( $source_table, [ 'status' => Status_Enums::FAILED->value ], [ 'id' => $item->id ] );

                    $error_count++;

                    $wpdb->insert(
                        $retry_table,
                        [
                            'original_id'     => $item->id,
                            'item_type'       => 'sales_return',
                            'item_number'     => $item->item_number,
                            'original_status' => $item->status,
                            'retry_status'    => Status_Enums::FAILED->value,
                            'retry_count'     => 1,
                            'retry_message'   => 'No item data found in API response',
                            'last_retry_at'   => current_time( 'mysql' ),
                        ],
                        [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                    );

                    $results[] = [
                        'item_id'       => $item->id,
                        'item_number'   => $item->item_number,
                        'result'        => 'error',
                        'error_message' => 'No item data found in API response',
                    ];
                }
            } else {
                // Update status to IGNORED with message No data found from api for this item
                $error_count++;

                $wpdb->insert(
                    $retry_table,
                    [
                        'original_id'     => $item->id,
                        'item_type'       => 'sales_return',
                        'item_number'     => $item->item_number,
                        'original_status' => $item->status,
                        'retry_status'    => Status_Enums::FAILED->value,
                        'retry_count'     => 1,
                        'retry_message'   => $api_result['error_message'] ?? 'API call failed',
                        'last_retry_at'   => current_time( 'mysql' ),
                    ],
                    [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                );

                $results[] = [
                    'item_id'       => $item->id,
                    'item_number'   => $item->item_number,
                    'result'        => 'error',
                    'error_message' => $api_result['error_message'] ?? 'API call failed',
                ];
            }

            // Update last processed ID after each item
            update_option( 'wasp_sales_return_retry_last_processed_id', $item->id );
        }

        // Prepare summary message
        $summary_message = sprintf(
            'Total %d items processed. %d items updated. %d items failed. %d items ignored.',
            $total_processed,
            $success_count,
            $error_count,
            $ignored_count
        );

        // Determine HTTP status code
        $http_status = 200; // Default success
        if ( $error_count > 0 ) {
            $http_status = 207; // Multi-Status (some succeeded, some failed)
        }
        if ( $success_count === 0 ) {
            $http_status = 500; // All failed
        }

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => $total_processed,
                'success_count'   => $success_count,
                'error_count'     => $error_count,
                'ignored_count'   => $ignored_count,
            ],
            'results' => $results,
        ], $http_status );
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
        $orders_ignored       = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $orders_table WHERE status = %s", Status_Enums::IGNORED->value )
        );
        $orders_failed        = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $orders_table WHERE status = %s", Status_Enums::FAILED->value )
        );
        $orders_retried       = $wpdb->get_var(
            "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'order' AND retry_count > 0"
        );
        // Count items that were successfully retried (status changed to READY or COMPLETED)
        $orders_retry_success = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'order' AND retry_status IN (%s, %s)",
                Status_Enums::READY->value,
                Status_Enums::COMPLETED->value
            )
        );

        // Sales returns stats
        $sales_ignored       = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $sales_returns_table WHERE status = %s", Status_Enums::IGNORED->value )
        );
        $sales_failed        = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $sales_returns_table WHERE status = %s", Status_Enums::FAILED->value )
        );
        $sales_retried       = $wpdb->get_var(
            "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'sales_return' AND retry_count > 0"
        );
        // Count items that were successfully retried (status changed to READY or COMPLETED)
        $sales_retry_success = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $retry_table WHERE item_type = 'sales_return' AND retry_status IN (%s, %s)",
                Status_Enums::READY->value,
                Status_Enums::COMPLETED->value
            )
        );

        return new \WP_REST_Response( [
            'orders'        => [
                'ignored'       => intval( $orders_ignored ),
                'failed'        => intval( $orders_failed ),
                'total_issues'  => intval( $orders_ignored ) + intval( $orders_failed ),
                'retried'       => intval( $orders_retried ),
                'retry_success' => intval( $orders_retry_success ),
            ],
            'sales_returns' => [
                'ignored'       => intval( $sales_ignored ),
                'failed'        => intval( $sales_failed ),
                'total_issues'  => intval( $sales_ignored ) + intval( $sales_failed ),
                'retried'       => intval( $sales_retried ),
                'retry_success' => intval( $sales_retry_success ),
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

    /**
     * Handle truncate table AJAX
     */
    public function handle_truncate_table() {
        check_ajax_referer( 'wasp-retry-nonce', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied. You do not have permission to perform this action.' ] );
        }

        // Get table type from request
        $table_type = isset( $_POST['table'] ) ? sanitize_text_field( $_POST['table'] ) : '';

        if ( empty( $table_type ) ) {
            wp_send_json_error( [ 'message' => 'Invalid table type.' ] );
        }

        // Validate table type
        if ( ! in_array( $table_type, [ 'orders', 'sales_returns' ] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid table type specified.' ] );
        }

        global $wpdb;

        // Determine table name
        if ( $table_type === 'orders' ) {
            $table_name = $wpdb->prefix . 'sync_wasp_woo_orders_data';
            $display_name = 'Orders';
        } else {
            $table_name = $wpdb->prefix . 'sync_sales_returns_data';
            $display_name = 'Sales Returns';
        }

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );

        if ( ! $table_exists ) {
            wp_send_json_error( [ 'message' => "Table {$table_name} does not exist." ] );
        }

        // Get count before truncation for confirmation
        $count_before = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        // Log the action before truncation
        $this->put_program_logs( "[DANGER] User " . wp_get_current_user()->user_login . " is truncating table: {$table_name} (Records: {$count_before})" );

        // Perform truncation
        $result = $wpdb->query( "TRUNCATE TABLE $table_name" );

        if ( $result === false ) {
            // Truncation failed
            $error_message = $wpdb->last_error ?: 'Unknown database error';
            $this->put_program_logs( "[ERROR] Failed to truncate table: {$table_name}. Error: {$error_message}" );
            
            wp_send_json_error( [ 
                'message' => "Failed to truncate {$display_name} table. Database error: {$error_message}"
            ] );
        }

        // Verify truncation
        $count_after = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        // Log successful truncation
        $this->put_program_logs( "[SUCCESS] Table {$table_name} truncated successfully. Records before: {$count_before}, Records after: {$count_after}" );

        // Reset last processed ID options for retry tracking
        if ( $table_type === 'orders' ) {
            delete_option( 'wasp_order_retry_last_processed_id' );
        } else {
            delete_option( 'wasp_sales_return_retry_last_processed_id' );
        }

        wp_send_json_success( [
            'message' => "✅ {$display_name} table truncated successfully. {$count_before} records were permanently deleted.",
            'records_deleted' => intval( $count_before ),
            'table_name' => $table_name,
        ] );
    }

}