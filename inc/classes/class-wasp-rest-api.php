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
            register_rest_route( 'atebol/v1', '/import-sales-returns', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_import_sales_returns' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );


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
        $pending_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'PENDING' LIMIT %d", $limit ) );
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

        // Get limit from query param, default 10, max 100
        $limit = intval( $request->get_param( 'limit' ) );
        if ( $limit <= 0 )
            $limit = 10;
        if ( $limit > 100 )
            $limit = 100;

        // Get all PENDING items with limit
        $pending_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'PENDING' LIMIT %d", $limit ) );
        if ( empty( $pending_items ) ) {
            return new \WP_REST_Response( [ 'message' => 'No pending items found.' ], 200 );
        }

        // Initialize counters
        $total_processed = 0;
        $sale_success    = 0;
        $sale_error      = 0;
        $return_success  = 0;
        $return_error    = 0;
        $results         = [];

        foreach ( $pending_items as $item ) {
            $total_processed++;

            if ( strtoupper( $item->type ) === 'SALE' ) {
                // Transaction Remove
                $payload    = [
                    [
                        'ItemNumber'     => $item->item_number,
                        'CustomerNumber' => $item->customer_number,
                        'SiteName'       => $item->site_name,
                        'LocationCode'   => $item->location_code,
                        'Quantity'       => $item->quantity,
                        'DateRemoved'    => $item->date_acquired,
                    ],
                ];
                $api_result = $this->transaction_remove_api( $this->token, $payload );

                // Track success/error for SALE transactions
                if ( $api_result['result'] === 'success' ) {
                    $sale_success++;
                    // Update status to COMPLETED for successful transactions
                    $wpdb->update( $table, [ 'status' => 'COMPLETED' ], [ 'id' => $item->id ] );
                } else {
                    $sale_error++;
                    // Update status to ERROR for failed transactions
                    $wpdb->update( $table, [ 'status' => 'ERROR' ], [ 'id' => $item->id ] );
                }

            } elseif ( strtoupper( $item->type ) === 'RETURN' ) {
                // Transaction Add
                $payload    = [
                    [
                        'ItemNumber'     => $item->item_number,
                        'Cost'           => $item->cost,
                        'DateAcquired'   => $item->date_acquired,
                        'CustomerNumber' => $item->customer_number,
                        'SiteName'       => $item->site_name,
                        'LocationCode'   => $item->location_code,
                        'Quantity'       => $item->quantity,
                    ],
                ];
                $api_result = $this->transaction_add_api( $this->token, $payload );

                // Track success/error for RETURN transactions
                if ( $api_result['result'] === 'success' ) {
                    $return_success++;
                    // Update status to COMPLETED for successful transactions
                    $wpdb->update( $table, [ 'status' => 'COMPLETED' ], [ 'id' => $item->id ] );
                } else {
                    $return_error++;
                    // Update status to ERROR for failed transactions
                    $wpdb->update( $table, [ 'status' => 'ERROR' ], [ 'id' => $item->id ] );
                }
            } else {
                $results[] = [ 'item' => $item->item_number, 'result' => 'Unknown type' ];
                continue;
            }

            $results[] = array_merge( [ 'item' => $item->item_number ], $api_result );
        }

        // Prepare summary message
        $summary_message = sprintf(
            'Total %d Items processed. %d Item (remove) %d Item (add) %d Item return Error',
            $total_processed,
            $sale_success,
            $return_success,
            ( $sale_error + $return_error )
        );

        // Determine HTTP status code
        $http_status = 200; // Default success
        if ( ( $sale_error + $return_error ) > 0 ) {
            $http_status = 207; // Multi-Status (some succeeded, some failed)
        }
        if ( ( $sale_success + $return_success ) === 0 ) {
            $http_status = 500; // All failed
        }

        return new \WP_REST_Response( [
            'message' => $summary_message,
            'summary' => [
                'total_processed' => $total_processed,
                'sale_success'    => $sale_success,
                'sale_error'      => $sale_error,
                'return_success'  => $return_success,
                'return_error'    => $return_error,
                'total_errors'    => ( $sale_error + $return_error ),
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

}