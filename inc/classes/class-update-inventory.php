<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Update_Inventory {

    use Singleton;
    use Program_Logs;

    private $api_base_url;
    private $token;
    private $update_inventory_enable_disable;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {

        add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
        add_action( 'woocommerce_thankyou', [ $this, 'check_update_product_remaining_stock' ] );

        // get api credentials
        $this->api_base_url                    = get_option( 'inv_cloud_base_url' );
        $this->token                           = get_option( 'inv_cloud_token' );
        $this->update_inventory_enable_disable = get_option( 'inv_cloud_update_inventory' );
    }

    public function register_api_endpoints() {

        // server status
        register_rest_route( 'atebol/v1', '/server-status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'server_status' ],
            'permission_callback' => '__return_true',
        ] );

        // insert item number to db
        register_rest_route( 'atebol/v1', '/insert-item-number-stock-db', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'insert_item_number_to_db' ],
            'permission_callback' => '__return_true',
        ] );

        // update woocommerce product stock
        register_rest_route( 'atebol/v1', '/update-woo-product-stock', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'update_woo_product_stock' ],
            'permission_callback' => '__return_true',
        ] );

        // check db stock items
        register_rest_route( 'atebol/v1', '/check-items', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'check_items' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function check_items() {
        return $this->check_db_items();
    }

    public function check_db_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_item_number';
        // $query = "SELECT COUNT(*) FROM $table_name";
        $query = "SELECT COUNT(*) FROM $table_name WHERE status = 'pending'";
        $items = $wpdb->get_results( $query );
        return $items;
    }

    public function server_status() {
        return 'Server is up and running';
    }

    public function insert_item_number_to_db() {
        return $this->insert_item_number_to_db_from_api();
    }

    public function insert_item_number_to_db_from_api() {

        if ( 'enable' === $this->update_inventory_enable_disable ) {
            global $wpdb;
            // get table name
            $table_name = $wpdb->prefix . 'sync_item_number';

            // truncate table (optional, uncomment if needed)
            // $wpdb->query( 'TRUNCATE TABLE ' . $table_name );

            // Loop through multiple API pages (10 in this case)
            for ( $i = 0; $i <= 10; $i++ ) {

                // Fetch the API response for each page
                $api_response_items = $this->fetch_all_inventory_item_from_api( $i );
                // Decode the API response
                $api_response_items_decode = json_decode( $api_response_items, true );

                // Check if data exists in the API response
                if ( isset( $api_response_items_decode['Data'] ) && !empty( $api_response_items_decode['Data'] ) ) {

                    $data = $api_response_items_decode['Data'];

                    // Loop through each item in the data
                    foreach ( $data as $item ) {

                        if ( array_key_exists( 'TotalAvailable', $item ) ) {
                            // Extract data
                            $item_number = $item['ItemNumber'];
                            $quantity    = $item['TotalAvailable'];

                            $message = sprintf( 'Item number: %s, quantity: %s', $item_number, $quantity );
                            // $this->put_program_logs( $message );

                            $sql = $wpdb->prepare(
                                "INSERT INTO $table_name (item_number, quantity, status) VALUES (%s, %s, %s)
                                ON DUPLICATE KEY UPDATE quantity = %s, status = %s",
                                $item_number,
                                intval( $quantity ),
                                'pending',
                                intval( $quantity ),
                                'pending'
                            );

                            $wpdb->query( $sql );

                            // Insert data into the database
                            /* $wpdb->insert(
                                $table_name,
                                [
                                    "item_number" => $item_number,
                                    "quantity"    => intval( $quantity ),
                                    "status"      => 'pending',
                                ]
                            ); */

                        } else {
                            // Log if 'ItemNumber' or 'TotalAvailable' is missing
                            $message = sprintf( 'Not found Item number: %s', $item['ItemNumber'] ?? 'Unknown' );
                            // $this->put_program_logs( $message );
                        }
                    }

                } else {
                    // Log if no data is found for the current page
                    // $this->put_program_logs( "Data not found for page $i" );
                }
            }

            // Return a success message after all iterations are complete
            return 'Item number and quantity inserted successfully for all pages';

        } else {
            return 'Update inventory is disabled';
        }

    }

    public function update_woo_product_stock() {

        if ( 'enable' === $this->update_inventory_enable_disable ) {

            // get how many items to update
            $limit = get_option( 'inv_cloud_update_quantity' );

            global $wpdb;
            $table_name = $wpdb->prefix . 'sync_item_number';
            $query      = "SELECT item_number, quantity FROM $table_name WHERE status = 'pending' LIMIT $limit";
            $items      = $wpdb->get_results( $query );

            if ( $items ) {
                foreach ( $items as $item ) {
                    $sku   = $item->item_number;
                    $stock = $item->quantity;
                    $this->update_woo_product_stock_by_sku( $sku, $stock );
                    // update status completed
                    $wpdb->update(
                        $table_name,
                        [
                            "status" => 'completed',
                        ],
                        [
                            "item_number" => $sku,
                        ]
                    );
                }

                return 'Stock updated successfully';
            } else {
                return 'No items found';
            }

        } else {
            return 'Update inventory is disabled';
        }
    }

    public function update_woo_product_stock_by_sku( $product_sku, $new_stock_quantity ) {

        // Get the product ID by SKU
        $product_id = wc_get_product_id_by_sku( $product_sku );

        if ( $product_id ) {
            // Get the product object using the ID
            $product = wc_get_product( $product_id );

            if ( $product ) {

                // update manage stock yes
                $product->set_manage_stock( true );

                // Update the stock quantity
                $product->set_stock_quantity( $new_stock_quantity );

                // Optionally set the stock status based on stock quantity
                if ( $new_stock_quantity > 0 ) {
                    $product->set_stock_status( 'instock' );
                } else {
                    $product->set_stock_status( 'outofstock' );
                }

                // Save the product to apply changes
                $product->save();

                // Log the success message (optional, using your Program_Logs trait)
                // $this->put_program_logs( 'Stock updated successfully for SKU: ' . $product_sku . ' to quantity: ' . $new_stock_quantity );
                $success_message = sprintf( 'Stock updated successfully for SKU: %s to quantity: %s', $product_sku, $new_stock_quantity );
                update_option( 'inv_cloud_message', $success_message );

                return 'Stock updated successfully!';
            } else {
                // log product not found error (optional)
                // $this->put_program_logs( 'Product not found for SKU: ' . $product_sku );
                $not_found_message = sprintf( 'Product not found for SKU: %s', $product_sku );
                update_option( 'inv_cloud_message', $not_found_message );
                return 'Product not found!';
            }
        } else {
            // log SKU not found error (optional)
            // $this->put_program_logs( 'No product found with the given SKU: ' . $product_sku );
            $message = sprintf( 'No product found with the given SKU: %s', $product_sku );
            update_option( 'inv_cloud_message', $message );
            return 'No product found with the given SKU!';
        }
    }

    public function check_update_product_remaining_stock( $order_id ) {

        // Get the order object
        $order = wc_get_order( $order_id );

        if ( $order ) {
            $order_items = $order->get_items();
            foreach ( $order_items as $item ) {
                $product = $item->get_product();

                if ( $product && $product->get_manage_stock() ) { // Ensure stock is managed for the product
                    $product_sku   = $product->get_sku();
                    $product_stock = $product->get_stock_quantity(); // Get remaining stock

                    // fetch single item from api
                    $single_item = $this->fetch_single_item_from_api( $product_sku );
                    // decode single item
                    $single_item_decode = json_decode( $single_item, true );

                    if ( isset( $single_item_decode['Data'] ) ) {
                        $payload = [];

                        foreach ( $single_item_decode['Data'] as $data_item ) {
                            $payload[] = [
                                'ItemNumber'   => intval( $data_item['ItemNumber'] ),
                                'AdjustType'   => 0,
                                'AdjustReason' => 'Cycle Count',
                                'SiteName'     => $data_item['SiteName'],
                                'LocationCode' => $data_item['LocationCode'],
                                'Quantity'     => floatval( $product_stock ),
                            ];
                        }

                        // put payload to log
                        // $this->put_program_logs( 'Payload: ' . json_encode( $payload ) );

                        // update inventory to api
                        $update_inventory = $this->update_inventory_to_api( $payload );
                        // put update inventory response to log
                        // $this->put_program_logs( 'Update Inventory: ' . $update_inventory );

                        // Log the product SKU and remaining stock
                        $message = sprintf( 'Product SKU: %s - Remaining stock: %s', $product_sku, $product_stock );
                        update_option( 'inv_cloud_message', $message );
                        // $this->put_program_logs( $message );
                    } else {
                        $not_found_message = sprintf( 'No data found for SKU: %s', $product_sku );
                        update_option( 'inv_cloud_message', $not_found_message );
                        // $this->put_program_logs( $not_found_message );
                    }
                } else {
                    // Log if stock is not managed for this product
                    $stock_management_disabled_message = sprintf( 'Stock management is disabled for product: %s', $product->get_name() );
                    update_option( 'inv_cloud_message', $stock_management_disabled_message );
                    // $this->put_program_logs( $stock_management_disabled_message );
                }
            }
        }
    }

    public function fetch_stock_value_from_api() {

        $payload = [
            "ItemNumber" => "",
        ];

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $this->api_base_url . '/public-api/ic/item/inventorysearch',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode( $payload ),
            CURLOPT_HTTPHEADER     => array(
                "Authorization: Bearer $this->token",
                "Content-Type: application/json",
            ),
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );
        return $response;
    }

    public function fetch_single_item_from_api( $item_number ) {

        $payload = [
            "ItemNumber" => intval( $item_number ),
        ];

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $this->api_base_url . '/public-api/ic/item/inventorysearch',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode( $payload ),
            CURLOPT_HTTPHEADER     => array(
                "Content-Type: application/json",
                "Authorization: Bearer $this->token",
            ),
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );
        return $response;

    }

    public function fetch_all_inventory_item_from_api( $pageNumber ) {

        $payload = [
            "PageSize"   => 500,
            "PageNumber" => intval( $pageNumber ),
        ];

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $this->api_base_url . '/public-api/ic/item/advancedinventorysearch',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode( $payload ),
            CURLOPT_HTTPHEADER     => array(
                "Authorization: Bearer $this->token",
                "Content-Type: application/json",
            ),
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );
        return $response;

    }

    public function update_inventory_to_api( $payload ) {

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $this->api_base_url . '/public-api/transactions/item/adjust',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode( $payload ),
            CURLOPT_HTTPHEADER     => array(
                "Authorization: Bearer $this->token",
                "Content-Type: application/json",
            ),
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );
        return $response;

    }
}
