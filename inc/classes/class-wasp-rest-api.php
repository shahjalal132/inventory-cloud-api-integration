<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;
use BOILERPLATE\Inc\Enums\Status_Enums;

class Wasp_Rest_Api {

    use Singleton;
    use Program_Logs;

    private $api_base_url;
    private $transaction_remove_api_path = '/public-api/transactions/item/remove';
    private $transaction_add_api_path = '/public-api/transactions/item/add';
    private $token;
    private $timeout = 60;
    public function __construct() {

        // get api credentials
        $this->api_base_url = get_option( 'inv_cloud_base_url' ) ?? 'https://atebol.waspinventorycloud.com';
        $this->token        = get_option( 'inv_cloud_token' );

        // setup hooks
        $this->setup_hooks();
    }

    public function setup_hooks() {

        // Register the REST API endpoint
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/prepare-sales-returns', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_prepare_sales_returns' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );

        // Register the REST API endpoint
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/prepare-woo-orders', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_prepare_woo_orders' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );

        // Register the REST API endpoint
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/import-sales-returns', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_import_sales_returns' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );

        // Register the REST API endpoint
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/import-woo-orders', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_import_woo_orders' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );

        // Register the REST API endpoint
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/remove-completed-woo-orders', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_remove_completed_woo_orders' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );

        // Register the REST API endpoint for status summary
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/sales-returns-status', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_sales_returns_status' ],
                'permission_callback' => '__return_true',
            ] );
        } );

        // Register the REST API endpoint
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/remove-completed-sales-returns', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_remove_completed_sales_returns' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );

        // Register the REST API endpoint for status summary
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/wasp-woo-orders-status', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_wasp_woo_orders_status' ],
                'permission_callback' => '__return_true',
            ] );
        } );

    }

    public function handle_prepare_woo_orders( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_wasp_woo_orders_data';

        // Step 1: Get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 ) {
            $limit = 10;
        } elseif ( $limit > 100 ) {
            $limit = 100;
        }

        // Step 2: Get pending and error items
        $pending_items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE status = %s LIMIT %d", Status_Enums::PENDING->value, $limit )
        );

        if ( empty( $pending_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items found.' ], 200 );
        }

        $total_processed = 0;
        $success_count   = 0;
        $error_count     = 0;
        $ignored_count   = 0;
        $results         = [];

        foreach ( $pending_items as $item ) {
            $total_processed++;

            // if item number is not numeric, blank, or null, ignore it and update the status to IGNORED
            if ( !is_numeric( $item->item_number ) || empty( $item->item_number ) ) {
                $wpdb->update( $table, [ 'status' => Status_Enums::IGNORED->value, 'message' => 'Item number is not correct or empty' ], [ 'id' => $item->id ] );
                $ignored_count++;
                continue;
            }

            // Step 3: Get item details from API
            $api_result = $this->get_item_details_api( $this->token, $item->item_number );

            if ( $api_result['result'] === 'success' ) {
                // extract api response data
                $response_data = json_decode( $api_result['api_response'], true );

                // update api_response
                $update_result = $wpdb->update(
                    $table,
                    [
                        'api_response' => $api_result['api_response'],
                    ],
                    [ 'id' => $item->id ]
                );

                $site_name      = '';
                $location_code  = '';
                $found_non_cllc = false;

                if ( isset( $response_data['Data'] ) && is_array( $response_data['Data'] ) && count( $response_data['Data'] ) > 0 ) {
                    foreach ( $response_data['Data'] as $loc ) {
                        $site = strtoupper( $loc['SiteName'] ?? '' );
                        $code = strtoupper( $loc['LocationCode'] ?? '' );

                        // Step 4: If either SiteName or LocationCode is NOT CLLC, accept it
                        if ( $site !== 'CLLC' && $code !== 'CLLC' ) {
                            $site_name      = $loc['SiteName'] ?? '';
                            $location_code  = $loc['LocationCode'] ?? '';
                            $found_non_cllc = true;
                            break;
                        }
                    }

                    // Step 5: Update DB
                    if ( $found_non_cllc ) {
                        $update_result = $wpdb->update(
                            $table,
                            [
                                'site_name'     => $site_name,
                                'location_code' => $location_code,
                                'status'        => Status_Enums::READY->value,
                            ],
                            [ 'id' => $item->id ]
                        );

                        if ( $update_result !== false ) {
                            $success_count++;
                            $results[] = [
                                'item'          => $item->item_number,
                                'result'        => 'success',
                                'site_name'     => $site_name,
                                'location_code' => $location_code,
                            ];
                        } else {
                            $error_count++;
                            $results[] = [
                                'item'          => $item->item_number,
                                'result'        => 'error',
                                'error_message' => 'Database update failed',
                            ];
                        }
                    } else {
                        // No non-CLLC location found — IGNORE
                        $wpdb->update(
                            $table,
                            [
                                'status'       => Status_Enums::IGNORED->value,
                                'api_response' => $api_result['api_response'],
                                'message'      => 'Only CLLC locations found',
                            ],
                            [ 'id' => $item->id ]
                        );

                        $ignored_count++;
                        $results[] = [
                            'item'          => $item->item_number,
                            'result'        => 'ignored',
                            'error_message' => 'Only CLLC locations found. Ignored.',
                        ];
                    }
                } else {
                    // update status error
                    $wpdb->update( $table, [ 'status' => Status_Enums::FAILED->value ], [ 'id' => $item->id ] );

                    $error_count++;
                    $results[] = [
                        'item'          => $item->item_number,
                        'result'        => 'error',
                        'error_message' => 'No item data found in API response',
                    ];
                }
            } else {
                // update status to IGNORED
                $wpdb->update( $table, [ 'status' => Status_Enums::IGNORED->value, 'message' => 'No data found from api for this item' ], [ 'id' => $item->id ] );
                $error_count++;
                $results[] = [
                    'item'          => $item->item_number,
                    'result'        => 'error',
                    'error_message' => $api_result['error_message'] ?? 'API call failed',
                ];
            }
        }

        // Step 6: Prepare summary
        $summary_message = sprintf(
            'Total %d Items processed. %d Items updated. %d Items failed. %d Items ignored.',
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

    public function handle_import_woo_orders( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_wasp_woo_orders_data';

        // Step 1: Get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 ) {
            $limit = 10;
        } elseif ( $limit > 100 ) {
            $limit = 100;
        }

        // Step 2: Get READY items
        $ready_items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE status = %s LIMIT %d", Status_Enums::READY->value, $limit )
        );

        if ( empty( $ready_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items found.' ], 200 );
        }

        $results     = [];
        $success_cnt = 0;
        $error_cnt   = 0;

        // Step 3: Loop each item, call API one by one
        foreach ( $ready_items as $item ) {
            $payload = [
                'ItemNumber'     => $item->item_number,
                'CustomerNumber' => $item->customer_number,
                'SiteName'       => $item->site_name,
                'LocationCode'   => $item->location_code,
                'Quantity'       => $item->quantity,
                'DateRemoved'    => $item->remove_date,
            ];

            // Call remove API per item
            $remove_result = $this->transaction_remove_api( $this->token, [ $payload ] );

            // Log if needed
            // $this->put_program_logs( "Transaction Remove API Payload: " . json_encode( $payload ) );
            // $this->put_program_logs( "Transaction Remove API Result: " . json_encode( $remove_result ) );

            // Update status and store API response
            $new_status   = ( $remove_result['result'] === 'success' ) ? Status_Enums::COMPLETED->value : Status_Enums::FAILED->value;
            $api_response = isset( $remove_result['api_response'] ) ? $remove_result['api_response'] : '';

            $wpdb->update(
                $table,
                [
                    'status'       => $new_status,
                    'api_response' => $api_response,
                ],
                [ 'id' => $item->id ]
            );

            if ( $new_status === Status_Enums::COMPLETED->value ) {
                $success_cnt++;
            } else {
                $error_cnt++;
            }

            $results[] = [
                'id'       => $item->id,
                'item_num' => $item->item_number,
                'result'   => $remove_result,
                'status'   => $new_status,
            ];
        }

        // Step 4: Prepare summary
        $summary_message = sprintf(
            'Total %d items processed. %d completed, %d failed.',
            count( $ready_items ),
            $success_cnt,
            $error_cnt
        );

        // Step 5: HTTP status code
        $http_status = ( $error_cnt > 0 ) ? ( $success_cnt > 0 ? 207 : 500 ) : 200;

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => count( $ready_items ),
                'success_count'   => $success_cnt,
                'error_count'     => $error_cnt,
            ],
            'results' => $results,
        ], $http_status );
    }

    public function handle_remove_completed_woo_orders( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_wasp_woo_orders_data';

        // Step 1: Get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 ) {
            $limit = 10;
        } elseif ( $limit > 100 ) {
            $limit = 100;
        }

        // Step 2: Calculate the first and last date of previous month
        $now            = new \DateTimeImmutable( 'first day of this month' );
        $previous_month = $now->modify( '-1 month' );
        $month_start    = $previous_month->format( 'Y-m-01 00:00:00' );
        $month_end      = $previous_month->format( 'Y-m-t 23:59:59' );

        // Step 3: Fetch completed items created in previous month
        $completed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s AND created_at BETWEEN %s AND %s LIMIT %d",
                Status_Enums::COMPLETED->value,
                $month_start,
                $month_end,
                $limit
            )
        );

        if ( empty( $completed_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items completed orders found for previous month.' ], 200 );
        }

        // Step 4: Delete the items
        $deleted_count = 0;
        $deleted_ids   = [];

        foreach ( $completed_items as $item ) {
            $delete_result = $wpdb->delete( $table, [ 'id' => $item->id ] );
            if ( $delete_result !== false ) {
                $deleted_count++;
                $deleted_ids[] = $item->id;
            }
        }

        // Step 5: Prepare summary
        $summary_message = sprintf(
            'Total %d items deleted from previous month (%s).',
            $deleted_count,
            $previous_month->format( 'F Y' )
        );

        return new \WP_REST_Response( [
            'message'     => $summary_message,
            'summary'     => [
                'month'         => $previous_month->format( 'Y-m' ),
                'deleted_count' => $deleted_count,
            ],
            'deleted_ids' => $deleted_ids,
        ], 200 );
    }

    /**
     * prepare sales returns data for import
     * @param mixed $request
     * @return \WP_REST_Response
     */
    public function handle_prepare_sales_returns( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        // get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 )
            $limit = 10;
        if ( $limit > 100 )
            $limit = 100;

        // get all items with limit where status is PENDING
        $pending_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = %s LIMIT %d", Status_Enums::PENDING->value, $limit ) );
        if ( empty( $pending_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items found.' ], 200 );
        }

        // Initialize counters
        $total_processed = 0;
        $success_count   = 0;
        $error_count     = 0;
        $ignored_count   = 0;
        $results         = [];

        foreach ( $pending_items as $item ) {
            $total_processed++;

            // 2. check is the item number numeric or not. if not skip it and update status to IGNORED
            if ( !is_numeric( $item->item_number ) ) {
                $wpdb->update( $table, [ 'status' => Status_Enums::IGNORED->value ], [ 'id' => $item->id ] );
                $ignored_count++;
                $results[] = [
                    'item'          => $item->item_number,
                    'result'        => 'ignored',
                    'error_message' => 'Item number is not numeric',
                ];
                continue;
            }

            // 3. get item details from api
            $api_result = $this->get_item_details_api( $this->token, $item->item_number );

            // log api response
            // $this->put_program_logs( "API response for item number {$item->item_number}: " . json_encode( $api_result ) . "\n" );

            if ( $api_result['result'] === 'success' ) {
                $response_data = json_decode( $api_result['api_response'], true );

                // 4. the item may be multiple location and site name. CLLC priority first.
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

                    // update site_name, location_code and status to READY
                    $update_result = $wpdb->update(
                        $table,
                        [
                            'site_name'     => $site_name,
                            'location_code' => $location_code,
                            'status'        => Status_Enums::READY->value,
                        ],
                        [ 'id' => $item->id ]
                    );

                    if ( $update_result !== false ) {
                        $success_count++;
                        $results[] = [
                            'item'          => $item->item_number,
                            'result'        => 'success',
                            'site_name'     => $site_name,
                            'location_code' => $location_code,
                            'used_cllc'     => $found_cllc ? 'yes' : 'no',
                        ];
                    } else {
                        $error_count++;
                        $results[] = [
                            'item'          => $item->item_number,
                            'result'        => 'error',
                            'error_message' => 'Database update failed',
                        ];
                    }
                } else {
                    // update status to ERROR
                    $wpdb->update( $table, [ 'status' => Status_Enums::FAILED->value ], [ 'id' => $item->id ] );

                    $error_count++;
                    $results[] = [
                        'item'          => $item->item_number,
                        'result'        => 'error',
                        'error_message' => 'No item data found in API response',
                    ];
                }
            } else {
                // update status to IGNORED with message No data found from api for this item
                $wpdb->update( $table, [ 'status' => Status_Enums::IGNORED->value, 'message' => 'No data found from api for this item' ], [ 'id' => $item->id ] );
                $error_count++;
                $results[] = [
                    'item'          => $item->item_number,
                    'result'        => 'error',
                    'error_message' => $api_result['error_message'] ?? 'API call failed',
                ];
            }
        }

        // 5. Prepare summary message
        $summary_message = sprintf(
            'Total %d Items processed. %d Items updated. %d Items failed. %d Items ignored.',
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
     * import sales returns data
     */
    public function handle_import_sales_returns( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        // Get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        $limit = $limit <= 0 ? 10 : min( $limit, 100 );

        // Get all READY items with limit
        $ready_items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE status = %s LIMIT %d", Status_Enums::READY->value, $limit )
        );
        if ( empty( $ready_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items found.' ], 200 );
        }

        $results       = [];
        $processed     = 0;
        $add_count     = 0;
        $remove_count  = 0;
        $error_count   = 0;
        $success_count = 0;

        foreach ( $ready_items as $item ) {
            $payload    = [];
            $api_result = null;

            if ( strtoupper( $item->type ) === 'RETURN' ) {
                $payload = [
                    'ItemNumber'     => $item->item_number,
                    'Cost'           => $item->cost,
                    'DateAcquired'   => $item->date_acquired,
                    'CustomerNumber' => $item->customer_number,
                    'SiteName'       => $item->site_name,
                    'LocationCode'   => $item->location_code,
                    'Quantity'       => $item->quantity,
                ];

                $api_result = $this->transaction_add_api( $this->token, [ $payload ] ); // send as array
                // log response
                // $this->put_program_logs( "Transaction Add API Payload: " . json_encode( $payload ) );
                // $this->put_program_logs( "Transaction Add API Result: " . json_encode( $api_result ) );

                $add_count++;
                $results['add'][] = [
                    'id'       => $item->id,
                    'payload'  => $payload,
                    'response' => $api_result,
                ];
            } elseif ( strtoupper( $item->type ) === 'SALE' ) {
                $payload = [
                    'ItemNumber'     => $item->item_number,
                    'CustomerNumber' => $item->customer_number,
                    'SiteName'       => $item->site_name,
                    'LocationCode'   => $item->location_code,
                    'Quantity'       => $item->quantity,
                    'DateRemoved'    => $item->date_acquired,
                ];

                $api_result = $this->transaction_remove_api( $this->token, [ $payload ] ); // send as array
                // log response
                // $this->put_program_logs( "Transaction Remove API Payload: " . json_encode( $payload ) );
                // $this->put_program_logs( "Transaction Remove API Result: " . json_encode( $api_result ) );

                $remove_count++;
                $results['remove'][] = [
                    'id'       => $item->id,
                    'payload'  => $payload,
                    'response' => $api_result,
                ];
            }

            // log request/response
            // $this->put_program_logs( "Transaction API Payload (ID {$item->id}): " . json_encode( $payload ) );
            // $this->put_program_logs( "Transaction API Result (ID {$item->id}): " . json_encode( $api_result ) );

            // determine status
            $new_status = Status_Enums::FAILED->value;
            if ( isset( $api_result['result'] ) && $api_result['result'] === 'success' ) {
                $new_status = Status_Enums::COMPLETED->value;
                $success_count++;
            } else {
                $error_count++;
            }

            // update this item only with API response
            $api_response = isset( $api_result['api_response'] ) ? $api_result['api_response'] : '';
            $wpdb->update(
                $table,
                [
                    'status'       => $new_status,
                    'api_response' => $api_response,
                ],
                [ 'id' => $item->id ]
            );

            $processed++;
        }

        // Prepare summary
        $summary_message = sprintf(
            'Processed %d items. %d completed, %d errors. (%d add, %d remove)',
            $processed,
            $success_count,
            $error_count,
            $add_count,
            $remove_count
        );

        // HTTP status logic
        $http_status = $error_count > 0
            ? ( $success_count > 0 ? 207 : 500 )
            : 200;

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => $processed,
                'success_count'   => $success_count,
                'error_count'     => $error_count,
                'add_count'       => $add_count,
                'remove_count'    => $remove_count,
            ],
            'results' => $results,
        ], $http_status );
    }

    public function handle_remove_completed_sales_returns( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        // Step 1: Get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 ) {
            $limit = 10;
        } elseif ( $limit > 100 ) {
            $limit = 100;
        }

        // Step 2: Calculate the first and last date of previous month
        $now            = new \DateTimeImmutable( 'first day of this month' );
        $previous_month = $now->modify( '-1 month' );
        $month_start    = $previous_month->format( 'Y-m-01 00:00:00' );
        $month_end      = $previous_month->format( 'Y-m-t 23:59:59' );

        // Step 3: Fetch completed items created in previous month
        $completed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s AND created_at BETWEEN %s AND %s LIMIT %d",
                Status_Enums::COMPLETED->value,
                $month_start,
                $month_end,
                $limit
            )
        );

        if ( empty( $completed_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No completed sales/returns found for previous month.' ], 200 );
        }

        // Step 4: Delete the items
        $deleted_count = 0;
        $deleted_ids   = [];

        foreach ( $completed_items as $item ) {
            $delete_result = $wpdb->delete( $table, [ 'id' => $item->id ] );
            if ( $delete_result !== false ) {
                $deleted_count++;
                $deleted_ids[] = $item->id;
            }
        }

        // Step 5: Prepare summary
        $summary_message = sprintf(
            'Total %d completed sales/returns deleted from previous month (%s).',
            $deleted_count,
            $previous_month->format( 'F Y' )
        );

        return new \WP_REST_Response( [
            'message'     => $summary_message,
            'summary'     => [
                'month'         => $previous_month->format( 'Y-m' ),
                'deleted_count' => $deleted_count,
            ],
            'deleted_ids' => $deleted_ids,
        ], 200 );
    }

    /**
     * call the transaction remove API
     */
    private function transaction_remove_api( $token, $payload ) {

        // if token and payload are empty, return error
        if ( empty( $token ) || empty( $payload ) ) {
            return [
                'status_code'   => 400,
                'result'        => 'error',
                'error_message' => 'Token and payload are required',
            ];
        }

        // prepare api url
        $api_url = sprintf( "%s%s", $this->api_base_url, $this->transaction_remove_api_path );

        // call api
        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'    => json_encode( $payload ),
            'timeout' => $this->timeout,
        ] );

        // retrieve response status code
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            return [
                'status_code'   => $status_code,
                'result'        => 'error',
                'error_message' => $response->get_error_message(),
            ];
        } else {
            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            // Check if API response indicates success
            $is_success    = false;
            $error_message = 'Unknown error';

            if ( isset( $response_data['Data']['ResultList'][0] ) ) {
                $result = $response_data['Data']['ResultList'][0];
                if ( $result['Message'] === 'Success' && $result['HttpStatusCode'] === 200 ) {
                    $is_success = true;
                } else {
                    $error_message = $result['Message'] ?? 'API returned error';
                }
            }

            if ( $is_success ) {
                return [
                    'status_code'  => $status_code,
                    'result'       => 'success',
                    'api_response' => $response_body,
                ];
            } else {
                return [
                    'status_code'   => $status_code,
                    'result'        => 'error',
                    'error_message' => $error_message,
                    'api_response'  => $response_body,
                ];
            }
        }
    }

    /**
     * call the transaction add API
     */
    private function transaction_add_api( $token, $payload ) {

        // if token and payload are empty, return error
        if ( empty( $token ) || empty( $payload ) ) {
            return [
                'status_code'   => 400,
                'result'        => 'error',
                'error_message' => 'Token and payload are required',
            ];
        }

        // prepare api url
        $api_url = sprintf( "%s%s", $this->api_base_url, $this->transaction_add_api_path );

        // call api
        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'    => json_encode( $payload ),
            'timeout' => $this->timeout,
        ] );

        // retrieve response status code
        $status_code = wp_remote_retrieve_response_code( $response );

        // check for errors
        if ( is_wp_error( $response ) ) {
            return [
                'status_code'   => $status_code,
                'result'        => 'error',
                'error_message' => $response->get_error_message(),
            ];
        } else {
            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            // Check if API response indicates success
            $is_success    = false;
            $error_message = 'Unknown error';

            if ( isset( $response_data['Data']['ResultList'][0] ) ) {
                $result = $response_data['Data']['ResultList'][0];
                if ( $result['HttpStatusCode'] === 200 ) {
                    $is_success = true;
                } else {
                    $error_message = $result['Message'] ?? 'API returned error';
                }
            }

            if ( $is_success ) {
                return [
                    'status_code'  => $status_code,
                    'result'       => 'success',
                    'api_response' => $response_body,
                ];
            } else {
                return [
                    'status_code'   => $status_code,
                    'result'        => 'error',
                    'error_message' => $error_message,
                    'api_response'  => $response_body,
                ];
            }
        }
    }

    /**
     * call the item inventory search API
     */
    public function get_item_details_api( $token, $item_number ) {
        if ( empty( $token ) || empty( $item_number ) ) {
            return [
                'status_code'   => 400,
                'result'        => 'error',
                'error_message' => 'Token and item number are required',
            ];
        }

        $cache_key = 'sales_return_item_' . md5( $item_number );

        // ✅ Check transient cache
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            // $this->put_program_logs( "Cache hit for item number {$item_number}" );

            return [
                'status_code'  => 200,
                'result'       => 'success',
                'api_response' => $cached,
                'cache'        => 'hit',
            ];
        }

        // 🚀 Cache miss → Call API
        // $this->put_program_logs( "Cache miss for item number {$item_number}" );

        $api_url = sprintf( "%s/public-api/ic/item/inventorysearch", $this->api_base_url );
        $payload = [ 'ItemNumber' => $item_number ];

        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'    => json_encode( $payload ),
            'timeout' => $this->timeout,
        ] );

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            return [
                'status_code'   => $status_code,
                'result'        => 'error',
                'error_message' => $response->get_error_message(),
            ];
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        if ( isset( $response_data['Data'] ) && !empty( $response_data['Data'] ) ) {
            // ✅ Store in transient for 10 minutes
            set_transient( $cache_key, $response_body, 5 * MINUTE_IN_SECONDS );

            return [
                'status_code'  => $status_code,
                'result'       => 'success',
                'api_response' => $response_body,
                'cache'        => 'miss',
            ];
        } else {
            return [
                'status_code'   => $status_code,
                'result'        => 'error',
                'error_message' => 'No data found for the item',
                'api_response'  => $response_body,
            ];
        }
    }

    /**
     * Get sales returns status summary or filtered count
     */
    public function handle_sales_returns_status( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        $status = $request->get_param( 'status' );
        if ( $status !== null ) {
            $status = strtoupper( $status );
        }
        $valid_statuses = [ Status_Enums::PENDING->value, Status_Enums::IGNORED->value, Status_Enums::FAILED->value, Status_Enums::COMPLETED->value, Status_Enums::READY->value ];

        if ( $status && in_array( $status, $valid_statuses ) ) {
            $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status ) );
            return new \WP_REST_Response( [
                'status' => $status,
                'count'  => intval( $count ),
            ], 200 );
        }

        // If no status filter, return all counts
        $total     = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $pending   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::PENDING->value ) );
        $ignored   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::IGNORED->value ) );
        $error     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::FAILED->value ) );
        $completed = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::COMPLETED->value ) );
        $ready     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::READY->value ) );

        return new \WP_REST_Response( [
            'message'   => 'Sales returns status summary',
            'total'     => intval( $total ),
            'pending'   => intval( $pending ),
            'ignored'   => intval( $ignored ),
            'error'     => intval( $error ),
            'completed' => intval( $completed ),
            'ready'     => intval( $ready ),
        ], 200 );
    }

    public function handle_wasp_woo_orders_status( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_wasp_woo_orders_data';

        $status = $request->get_param( 'status' );
        if ( $status !== null ) {
            $status = strtoupper( $status );
        }
        $valid_statuses = [ Status_Enums::PENDING->value, Status_Enums::IGNORED->value, Status_Enums::FAILED->value, Status_Enums::COMPLETED->value, Status_Enums::READY->value ];

        if ( $status && in_array( $status, $valid_statuses ) ) {
            $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status ) );
            return new \WP_REST_Response( [
                'status' => $status,
                'count'  => intval( $count ),
            ], 200 );
        }

        // If no status filter, return all counts
        $total     = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $pending   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::PENDING->value ) );
        $ignored   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::IGNORED->value ) );
        $error     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::FAILED->value ) );
        $completed = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::COMPLETED->value ) );
        $ready     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", Status_Enums::READY->value ) );

        return new \WP_REST_Response( [
            'message'   => 'WASP Woo Orders status summary',
            'total'     => intval( $total ),
            'pending'   => intval( $pending ),
            'ignored'   => intval( $ignored ),
            'error'     => intval( $error ),
            'completed' => intval( $completed ),
            'ready'     => intval( $ready ),
        ], 200 );
    }

}