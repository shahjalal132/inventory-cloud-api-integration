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

        // register wasp settings top label menu
        add_action( 'admin_menu', [ $this, 'wasp_settings_top_menu' ] );

        // register woocommerce order import sub menu
        add_action( 'admin_menu', [ $this, 'wasp_order_import_sub_menu_page' ] );

        // register sales/return import sub menu
        add_action( 'admin_menu', [ $this, 'wasp_sales_return_import_sub_menu_page' ] );

        // register inventory-cloud-options sub menu
        add_action( 'admin_menu', [ $this, 'wasp_inventory_cloud_settings_sub_menu_page' ] );

        // add plugin action links
        add_filter( 'plugin_action_links_' . PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );

        // Handle AJAX request to save options
        add_action( 'wp_ajax_save_inventory_cloud_options', [ $this, 'save_inventory_cloud_options' ] );
        add_action( 'wp_ajax_instant_update_inventory', [ $this, 'instant_update_inventory_callback' ] );
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
        $settings_link = '<a href="admin.php?page=wasp-settings">' . __( 'Settings', 'inventory-cloud' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function wasp_settings_top_menu() {
        add_menu_page(
            'Wasp Settings',
            'Wasp Settings',
            'manage_options',
            'wasp-settings',
            [ $this, 'wasp_settings_page_html' ],
            'dashicons-admin-generic',
            20
        );
    }

    public function wasp_settings_page_html() {
        include_once PLUGIN_BASE_PATH . '/templates/menus/wasp-top-menu.php';
    }

    public function wasp_inventory_cloud_settings_sub_menu_page() {
        add_submenu_page(
            'wasp-settings',
            'Wasp Options',
            'Wasp Options',
            'manage_options',
            'inventory-cloud-options',
            [ $this, 'wasp_inventory_cloud_sub_menu_page_html' ]
        );
    }

    public function wasp_order_import_sub_menu_page() {
        add_submenu_page(
            'wasp-settings',
            'Wasp Order Import',
            'Wasp Order Import',
            'manage_options',
            'wasp-order-import',
            [ $this, 'wasp_order_import_sub_menu_page_html' ]
        );
    }

    public function wasp_sales_return_import_sub_menu_page() {
        add_submenu_page(
            'wasp-settings',
            'Wasp Sales/Return Import',
            'Wasp Sales/Return Import',
            'manage_options',
            'wasp-sales-return-import',
            [ $this, 'wasp_sales_return_import_sub_menu_page_html' ]
        );
    }

    public function wasp_inventory_cloud_sub_menu_page_html() {

        $base_url         = get_option( 'inv_cloud_base_url' );
        $token            = get_option( 'inv_cloud_token' );
        $update_quantity  = get_option( 'inv_cloud_update_quantity' );
        $update_inventory = get_option( 'inv_cloud_update_inventory' );

        ?>

        <div class="wasp-inv-container">
            <h1 class="wasp-inv-container-title">Inventory Cloud Options</h1>

            <div class="wasp-inventory-controller">
                <h3>Inventory Management Controller</h3>
                <div class="update-inventory-enabled-disabled inv-cloud-wrapper">
                    <h4>Update Inventory Enable/Disable:</h4>
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
                    <h4>Instant Update Inventory:</h4>
                    <button type="button" id="instant-update-inventory" class="button button-primary">
                        <div class="instant-update-inventory-wrapper">
                            <span>Update Inventory</span>
                            <span class="loader-wrapper"></span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="wasp-api-credentials">
                <h3>Api Credentials</h3>
                <div class="api-base-url inv-cloud-wrapper">
                    <h4>API Base Url:</h4>
                    <input type="text" placeholder="https://api.example.com" value="<?= esc_attr( $base_url ) ?>"
                        name="api-base-url" id="inv-cloud-base-url" class="widefat" style="width: 20%">
                </div>

                <div class="inv-cloud-wrapper">
                    <h4>Token:</h4>
                    <input type="text" placeholder="token" value="<?= esc_attr( $token ) ?>" name="api-token"
                        id="inv-cloud-token" class="widefat" style="width: 20%">
                </div>

                <div class="inv-cloud-wrapper">
                    <h4>Update Quantity:</h4>
                    <input type="number" placeholder="How many Products update per minute"
                        value="<?= esc_attr( $update_quantity ) ?>" name="update_quantity" id="inv-cloud-update_quantity"
                        class="widefat" style="width: 20%">
                </div>

                <button type="button" id="inv-cloud-save-btn" class="button button-primary">Save</button>
            </div>

            <?php
            $site_url = site_url();
            ?>

            <div class="wasp-endpoints">
                <h3>API Endpoints</h3>

                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/server-status' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/insert-item-number-stock-db' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/update-woo-product-stock' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/prepare-sales-returns' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/prepare-woo-orders' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/import-sales-returns' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/import-woo-orders' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/sales-returns-status' ); ?></h4>
                <h4><?= esc_html( $site_url . '/wp-json/atebol/v1/wasp-woo-orders-status' ); ?></h4>
            </div>
        </div>

        <?php
    }

    public function wasp_order_import_sub_menu_page_html() {
        include_once PLUGIN_BASE_PATH . '/templates/menus/wasp-order-import.php';
    }

    public function wasp_sales_return_import_sub_menu_page_html() {
        include_once PLUGIN_BASE_PATH . '/templates/menus/wasp-sales-return-import.php';
    }

    public function instant_update_inventory_callback() {

        check_ajax_referer( 'inv_cloud_nonce', 'nonce' );

        // check if manage options capability is available
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        $site_url = site_url();
        $url      = $site_url . '/wp-json/atebol/v1/insert-item-number-stock-db';

        $response      = wp_remote_get( $url, [
            'timeout'   => 300,
            'sslverify' => false,
        ] );
        $response_body = wp_remote_retrieve_body( $response );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Error inserting item number to db.' ] );
        } else {
            wp_send_json_success( [ 'message' => $response_body ] );
        }

    }

}