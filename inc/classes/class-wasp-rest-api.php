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
            return [ 'message' => 'No pending items found.' ];
        }

        $results = [];
        foreach ( $pending_items as $item ) {

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
            } else {
                $results[] = [ 'item' => $item->item_number, 'result' => 'Unknown type' ];
                continue;
            }

            $results[] = array_merge( [ 'item' => $item->item_number ], $api_result );
        }

        return [ 'results' => $results ];
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
            return [
                'status_code'  => $status_code,
                'result'       => 'success',
                'api_response' => wp_remote_retrieve_body( $response ),
            ];
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
            return [
                'status_code'  => $status_code,
                'result'       => 'success',
                'api_response' => wp_remote_retrieve_body( $response ),
            ];
        }
    }

}