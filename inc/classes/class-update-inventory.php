<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Update_Inventory {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
        // add_action('woocommerce_update_product_stock', [$this, 'update_stock_by_sku'], 10, 2);
    }

    public function register_api_endpoints() {
        // server status
        register_rest_route( 'atebol/v1', '/server-status', [
            'methods'  => 'GET',
            'callback' => [ $this, 'atebol_server_status' ],
        ] );
    }

    public function atebol_server_status() {
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Server is up and running.',
        ], 200 );
    }

    /**
     * Update stock quantity for a product by SKU.
     *
     * @param string $product_sku The product SKU.
     * @param int $new_stock_quantity The new stock quantity.
     * @return string
     */
    public function update_stock_by_sku( $product_sku, $new_stock_quantity ) {

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
                $this->put_program_logs( 'Stock updated successfully for SKU: ' . $product_sku . ' to quantity: ' . $new_stock_quantity );

                return 'Stock updated successfully!';
            } else {
                // log product not found error (optional)
                $this->put_program_logs( 'Product not found for SKU: ' . $product_sku );
                return 'Product not found!';
            }
        } else {
            // log SKU not found error (optional)
            $this->put_program_logs( 'No product found with the given SKU: ' . $product_sku );
            return 'No product found with the given SKU!';
        }
    }
}
