<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

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

        // Register the REST API endpoint for status summary
        add_action( 'rest_api_init', function () {
            register_rest_route( 'atebol/v1', '/sales-returns-status', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_sales_returns_status' ],
                'permission_callback' => '__return_true',
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
            $wpdb->prepare( "SELECT * FROM $table WHERE status = 'PENDING' OR status = 'ERROR' LIMIT %d", $limit )
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

            // Step 3: Get item details from API
            $api_result = $this->get_item_details_api( $this->token, $item->item_number );

            if ( $api_result['result'] === 'success' ) {
                $response_data = json_decode( $api_result['api_response'], true );

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
                                'status'        => 'READY',
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
                        $wpdb->update( $table, [ 'status' => 'IGNORED' ], [ 'id' => $item->id ] );
                        $ignored_count++;
                        $results[] = [
                            'item'          => $item->item_number,
                            'result'        => 'ignored',
                            'error_message' => 'Only CLLC locations found. Ignored.',
                        ];
                    }
                } else {
                    $error_count++;
                    $results[] = [
                        'item'          => $item->item_number,
                        'result'        => 'error',
                        'error_message' => 'No item data found in API response',
                    ];
                }
            } else {
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
            $wpdb->prepare( "SELECT * FROM $table WHERE status = 'READY' LIMIT %d", $limit )
        );

        if ( empty( $ready_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items found.' ], 200 );
        }

        // Step 3: Prepare remove payload
        $remove_payload = [];
        $remove_ids     = [];

        foreach ( $ready_items as $item ) {
            $remove_payload[] = [
                'ItemNumber'     => $item->item_number,
                'CustomerNumber' => $item->customer_number,
                'SiteName'       => $item->site_name,
                'LocationCode'   => $item->location_code,
                'Quantity'       => $item->quantity,
                'DateRemoved'    => $item->remove_date,
            ];
            $remove_ids[]     = $item->id;
        }

        $results       = [];
        $remove_result = null;

        // Step 4: Call remove API
        if ( !empty( $remove_payload ) ) {
            $remove_result     = $this->transaction_remove_api( $this->token, $remove_payload );
            $results['remove'] = $remove_result;

            // Update status for each item
            $new_status = ( $remove_result['result'] === 'success' ) ? 'COMPLETED' : 'ERROR';
            foreach ( $remove_ids as $id ) {
                $wpdb->update( $table, [ 'status' => $new_status ], [ 'id' => $id ] );
            }
        }

        // Step 5: Prepare summary
        $summary_message = sprintf(
            'Total %d Items processed. %d Items removed.',
            count( $ready_items ),
            count( $remove_ids )
        );

        // Step 6: Determine HTTP status code
        $http_status = 200;
        if ( $remove_result && $remove_result['result'] === 'error' ) {
            $http_status = 500;
        }

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => count( $ready_items ),
                'remove_count'    => count( $remove_ids ),
            ],
            'results' => $results,
        ], $http_status );
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
        $pending_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'PENDING' or status = 'ERROR' LIMIT %d", $limit ) );
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
                $wpdb->update( $table, [ 'status' => 'IGNORED' ], [ 'id' => $item->id ] );
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
                            'status'        => 'READY',
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
                    $error_count++;
                    $results[] = [
                        'item'          => $item->item_number,
                        'result'        => 'error',
                        'error_message' => 'No item data found in API response',
                    ];
                }
            } else {
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

        // get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 )
            $limit = 10;
        if ( $limit > 100 )
            $limit = 100;

        // Get all READY items with limit
        $ready_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'READY' LIMIT %d", $limit ) );
        if ( empty( $ready_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No items found.' ], 200 );
        }

        // Prepare payloads
        $add_payload    = [];
        $remove_payload = [];
        $add_ids        = [];
        $remove_ids     = [];
        $add_count      = 0;
        $remove_count   = 0;

        foreach ( $ready_items as $item ) {
            if ( strtoupper( $item->type ) === 'RETURN' ) {
                $add_payload[] = [
                    'ItemNumber'     => $item->item_number,
                    'Cost'           => $item->cost,
                    'DateAcquired'   => $item->date_acquired,
                    'CustomerNumber' => $item->customer_number,
                    'SiteName'       => $item->site_name,
                    'LocationCode'   => $item->location_code,
                    'Quantity'       => $item->quantity,
                ];
                $add_ids[]     = $item->id;
                $add_count++;
            } elseif ( strtoupper( $item->type ) === 'SALE' ) {
                $remove_payload[] = [
                    'ItemNumber'     => $item->item_number,
                    'CustomerNumber' => $item->customer_number,
                    'SiteName'       => $item->site_name,
                    'LocationCode'   => $item->location_code,
                    'Quantity'       => $item->quantity,
                    'DateRemoved'    => $item->date_acquired,
                ];
                $remove_ids[]     = $item->id;
                $remove_count++;
            }
        }

        $results       = [];
        $add_result    = null;
        $remove_result = null;

        // Call add API to add items
        if ( !empty( $add_payload ) ) {
            $add_result     = $this->transaction_add_api( $this->token, $add_payload );
            $results['add'] = $add_result;

            // update status to COMPLETED or ERROR for each item
            $new_status = ( $add_result['result'] === 'success' ) ? 'COMPLETED' : 'ERROR';
            foreach ( $add_ids as $id ) {
                $wpdb->update( $table, [ 'status' => $new_status ], [ 'id' => $id ] );
            }
        }

        // Call remove API to remove items
        if ( !empty( $remove_payload ) ) {
            $remove_result     = $this->transaction_remove_api( $this->token, $remove_payload );
            $results['remove'] = $remove_result;
            // update status to COMPLETED or ERROR for each item
            $new_status = ( $remove_result['result'] === 'success' ) ? 'COMPLETED' : 'ERROR';
            foreach ( $remove_ids as $id ) {
                $wpdb->update( $table, [ 'status' => $new_status ], [ 'id' => $id ] );
            }
        }

        // Prepare summary message
        $summary_message = sprintf(
            'Total %d Items processed. %d Items (remove) %d Items (add).',
            count( $ready_items ),
            $remove_count,
            $add_count
        );

        // Determine HTTP status code
        $http_status = 200;
        if ( ( $add_result && $add_result['result'] === 'error' ) || ( $remove_result && $remove_result['result'] === 'error' ) ) {
            $http_status = 207;
        }
        if ( ( $add_result && $add_result['result'] === 'error' && $add_count > 0 ) && ( $remove_result && $remove_result['result'] === 'error' && $remove_count > 0 ) ) {
            $http_status = 500;
        }

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => count( $ready_items ),
                'remove_count'    => $remove_count,
                'add_count'       => $add_count,
            ],
            'results' => $results,
        ], $http_status );
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
    private function get_item_details_api( $token, $item_number ) {
        // if token and item_number are empty, return error
        if ( empty( $token ) || empty( $item_number ) ) {
            return [
                'status_code'   => 400,
                'result'        => 'error',
                'error_message' => 'Token and item number are required',
            ];
        }

        // prepare api url
        $api_url = sprintf( "%s/public-api/ic/item/inventorysearch", $this->api_base_url );

        // prepare payload
        $payload = [
            'ItemNumber' => $item_number,
        ];

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

            if ( isset( $response_data['Data'] ) && !empty( $response_data['Data'] ) ) {
                $is_success = true;
            } else {
                $error_message = 'No data found for the item';
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
     * Get sales returns status summary or filtered count
     */
    public function handle_sales_returns_status( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        $status = $request->get_param( 'status' );
        if ( $status !== null ) {
            $status = strtoupper( $status );
        }
        $valid_statuses = [ 'PENDING', 'IGNORED', 'ERROR', 'COMPLETED', 'READY' ];

        if ( $status && in_array( $status, $valid_statuses ) ) {
            $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status ) );
            return new \WP_REST_Response( [
                'status' => $status,
                'count'  => intval( $count ),
            ], 200 );
        }

        // If no status filter, return all counts
        $total     = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $pending   = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'PENDING'" );
        $ignored   = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'IGNORED'" );
        $error     = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'ERROR'" );
        $completed = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'COMPLETED'" );
        $ready     = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'READY'" );

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
        $valid_statuses = [ 'PENDING', 'IGNORED', 'ERROR', 'COMPLETED', 'READY' ];

        if ( $status && in_array( $status, $valid_statuses ) ) {
            $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status ) );
            return new \WP_REST_Response( [
                'status' => $status,
                'count'  => intval( $count ),
            ], 200 );
        }

        // If no status filter, return all counts
        $total     = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $pending   = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'PENDING'" );
        $ignored   = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'IGNORED'" );
        $error     = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'ERROR'" );
        $completed = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'COMPLETED'" );
        $ready     = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'READY'" );

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