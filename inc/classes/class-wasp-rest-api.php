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
            register_rest_route( 'wasp/v1', '/import-sales-returns', [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_import_sales_returns' ],
                'permission_callback' => '__return_true', // Adjust as needed
            ] );
        } );
    }

    /**
     * Handle the /import-sales-returns endpoint
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
        $sale_success = 0;
        $sale_error = 0;
        $return_success = 0;
        $return_error = 0;
        $results = [];

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
            ($sale_error + $return_error)
        );

        // Determine HTTP status code
        $http_status = 200; // Default success
        if ( ($sale_error + $return_error) > 0 ) {
            $http_status = 207; // Multi-Status (some succeeded, some failed)
        }
        if ( ($sale_success + $return_success) === 0 ) {
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
                'total_errors'    => ($sale_error + $return_error)
            ],
            'results' => $results
        ], $http_status );
    }

    /**
     * Call the transaction remove API
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
                'Authorization' => $token,
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
     * Call the transaction add API
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
                'Authorization' => $token,
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

}