<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Admin_Menu {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        add_action( 'admin_menu', [ $this, 'admin_menu_options_page' ] );
        add_filter( 'plugin_action_links_' . PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );

        // Handle AJAX request to save options
        add_action( 'wp_ajax_save_inventory_cloud_options', [ $this, 'save_inventory_cloud_options' ] );
    }

    // Handle AJAX request to save options
    public function save_inventory_cloud_options() {

        check_ajax_referer( 'inv_cloud_nonce', 'nonce' );

        // check if manage options capability is available
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        // Get the posted values
        $api_base_url     = sanitize_text_field( $_POST['api_base_url'] );
        $api_token        = sanitize_text_field( $_POST['api_token'] );
        $update_quantity  = sanitize_text_field( $_POST['update_quantity'] );
        $update_inventory = sanitize_text_field( $_POST['update_inventory'] );

        // Update the options in the database
        update_option( 'inv_cloud_base_url', $api_base_url );
        update_option( 'inv_cloud_token', $api_token );
        update_option( 'inv_cloud_update_quantity', $update_quantity );
        update_option( 'inv_cloud_update_inventory', $update_inventory );

        wp_send_json_success( [ 'message' => 'Options saved successfully.' ] );
    }

    // Add settings link on the plugin page
    function add_plugin_action_links( $links ) {
        $settings_link = '<a href="admin.php?page=inventory-cloud-options">' . __( 'Settings', 'inventory-cloud' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function admin_menu_options_page() {
        add_submenu_page(
            'options-general.php',
            'Inventory Cloud Options',
            'Inventory Cloud Options',
            'manage_options',
            'inventory-cloud-options',
            [ $this, 'atebol_options_page_html' ]
        );
    }

    public function atebol_options_page_html() {

        $base_url         = get_option( 'inv_cloud_base_url' );
        $token            = get_option( 'inv_cloud_token' );
        $update_quantity  = get_option( 'inv_cloud_update_quantity' );
        $update_inventory = get_option( 'inv_cloud_update_inventory' );
    
        ?>
    
        <h1>Inventory Cloud Options</h1>
    
        <div class="update-inventory-enabled-disabled inv-cloud-mt-30 inv-cloud-wrapper">
            <h3>Update Inventory Enable/Disable:</h3>
            <label>
                <input type="radio" name="update-inventory" id="update-inventory-enable" value="enable"
                    <?= $update_inventory === 'enable' ? 'checked' : '' ?>>
                Enable
            </label>
    
            <label>
                <input type="radio" name="update-inventory" id="update-inventory-disable" value="disable"
                    <?= $update_inventory === 'disable' ? 'checked' : '' ?>>
                Disable
            </label>
        </div>
    
        <div class="api-base-url inv-cloud-wrapper">
            <h3>API Base Url:</h3>
            <input type="text" placeholder="https://api.example.com" value="<?= esc_attr( $base_url ) ?>" name="api-base-url"
                id="inv-cloud-base-url" class="widefat" style="width: 20%">
        </div>
    
        <div class="inv-cloud-wrapper">
            <h3>Token:</h3>
            <input type="text" placeholder="token" value="<?= esc_attr( $token ) ?>" name="api-token" id="inv-cloud-token" class="widefat"
                style="width: 20%">
        </div>
    
        <div class="inv-cloud-wrapper">
            <h3>Update Quantity:</h3>
            <input type="number" placeholder="How many Products update per minute" value="<?= esc_attr( $update_quantity ) ?>"
                name="update_quantity" id="inv-cloud-update_quantity" class="widefat" style="width: 20%">
        </div>
    
        <button type="button" id="inv-cloud-save-btn" class="button button-primary">Save</button>
    
        <div class="inv-mt-20"></div>
        <hr>
        <div class="inv-mb-20"></div>
    
        <?php
        $site_url = site_url();
        ?>
    
        <h1>API Endpoints</h1>
    
        <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/server-status' ); ?></h4>
        <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/insert-item-number-stock-db' ); ?></h4>
        <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/update-woo-product-stock' ); ?></h4>
    
        <?php
    }    

}