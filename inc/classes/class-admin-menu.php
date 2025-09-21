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

        // register cron jobs sub menu
        add_action( 'admin_menu', [ $this, 'wasp_cron_jobs_sub_menu_page' ] );

        // register inventory-cloud-options sub menu
        add_action( 'admin_menu', [ $this, 'wasp_inventory_cloud_settings_sub_menu_page' ] );

        // add plugin action links
        add_filter( 'plugin_action_links_' . PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );

        // Handle AJAX request to save options
        add_action( 'wp_ajax_save_inventory_cloud_options', [ $this, 'save_inventory_cloud_options' ] );
        add_action( 'wp_ajax_instant_update_inventory', [ $this, 'instant_update_inventory_callback' ] );
        add_action( 'wp_ajax_run_wasp_endpoint', [ $this, 'run_wasp_endpoint' ] );
        
        // Handle AJAX requests for table data
        add_action( 'wp_ajax_fetch_sales_returns_data', [ $this, 'fetch_sales_returns_data' ] );
        add_action( 'wp_ajax_fetch_orders_data', [ $this, 'fetch_orders_data' ] );
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
        $api_username     = sanitize_text_field( $_POST['api_username'] ?? '' );
        $api_password     = sanitize_text_field( $_POST['api_password'] ?? '' );

        // Update the options in the database
        update_option( 'inv_cloud_base_url', $api_base_url );
        update_option( 'inv_cloud_token', $api_token );
        update_option( 'inv_cloud_update_quantity', $update_quantity );
        update_option( 'inv_cloud_update_inventory', $update_inventory );
        update_option( 'inv_cloud_api_username', $api_username );
        update_option( 'inv_cloud_api_password', $api_password );

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
            'Dashboard',
            'Dashboard',
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
            'Settings',
            'Settings',
            'manage_options',
            'inventory-cloud-options',
            [ $this, 'wasp_inventory_cloud_sub_menu_page_html' ]
        );
    }

    public function wasp_order_import_sub_menu_page() {
        add_submenu_page(
            'wasp-settings',
            'Order Import',
            'Order Import',
            'manage_options',
            'wasp-order-import',
            [ $this, 'wasp_order_import_sub_menu_page_html' ]
        );
    }

    public function wasp_sales_return_import_sub_menu_page() {
        add_submenu_page(
            'wasp-settings',
            'Sales/Return Import',
            'Sales/Return Import',
            'manage_options',
            'wasp-sales-return-import',
            [ $this, 'wasp_sales_return_import_sub_menu_page_html' ]
        );
    }

    public function wasp_cron_jobs_sub_menu_page() {
        add_submenu_page(
            'wasp-settings',
            'Cron Jobs',
            'Cron Jobs',
            'manage_options',
            'wasp-cron-jobs',
            [ $this, 'wasp_cron_jobs_sub_menu_page_html' ]
        );
    }

    private function get_api_endpoints() {
        $site_url = site_url('/wp-json/atebol/v1/');
        return [
            'server-status' => [
                'url' => $site_url . 'server-status',
                'description' => 'Check if the server is up and running.',
                'method' => 'GET'
            ],
            'insert-item-number-stock-db' => [
                'url' => $site_url . 'insert-item-number-stock-db',
                'description' => 'Sync all item numbers from Wasp to the local database.',
                'method' => 'GET'
            ],
            'update-woo-product-stock' => [
                'url' => $site_url . 'update-woo-product-stock',
                'description' => 'Update WooCommerce product stock from the local database.',
                'method' => 'GET'
            ],
            'prepare-sales-returns' => [
                'url' => $site_url . 'prepare-sales-returns',
                'description' => 'Prepare sales returns for import.',
                'method' => 'GET'
            ],
            'prepare-woo-orders' => [
                'url' => $site_url . 'prepare-woo-orders',
                'description' => 'Prepare WooCommerce orders for import.',
                'method' => 'GET'
            ],
            'import-sales-returns' => [
                'url' => $site_url . 'import-sales-returns',
                'description' => 'Import sales and returns into Wasp.',
                'method' => 'GET'
            ],
            'import-woo-orders' => [
                'url' => $site_url . 'import-woo-orders',
                'description' => 'Import WooCommerce orders into Wasp.',
                'method' => 'GET'
            ],
            'sales-returns-status' => [
                'url' => $site_url . 'sales-returns-status',
                'description' => 'Get the status summary of sales/returns sync.',
                'method' => 'GET'
            ],
            'wasp-woo-orders-status' => [
                'url' => $site_url . 'wasp-woo-orders-status',
                'description' => 'Get the status summary of Woo orders sync.',
                'method' => 'GET'
            ],
            'remove-completed-sales-returns' => [
                'url' => $site_url . 'remove-completed-sales-returns',
                'description' => 'Clean up old, completed sales/returns records.',
                'method' => 'GET'
            ],
            'remove-completed-woo-orders' => [
                'url' => $site_url . 'remove-completed-woo-orders',
                'description' => 'Clean up old, completed Woo order records.',
                'method' => 'GET'
            ]
        ];
    }

    public function wasp_inventory_cloud_sub_menu_page_html() {

        $base_url         = get_option( 'inv_cloud_base_url' );
        $token            = get_option( 'inv_cloud_token' );
        $update_quantity  = get_option( 'inv_cloud_update_quantity' );
        $update_inventory = get_option( 'inv_cloud_update_inventory' );
        $api_username     = get_option( 'inv_cloud_api_username' );
        $api_password     = get_option( 'inv_cloud_api_password' );
        $api_endpoints    = $this->get_api_endpoints();
        ?>

        <div class="wasp-options-container">
            <h1 class="wasp-options-title">Wasp Inventory Options</h1>

            <div class="wasp-options-grid">
                <!-- General Settings Card -->
                <div class="wasp-options-card">
                    <h2>General Settings</h2>
                    <div class="wasp-form-group">
                        <label>Update Inventory from Wasp</label>
                        <div class="wasp-radio-group">
                            <label><input type="radio" name="update-inventory" value="enable" <?= checked($update_inventory, 'enable'); ?>> Enable</label>
                            <label><input type="radio" name="update-inventory" value="disable" <?= checked($update_inventory, 'disable'); ?>> Disable</label>
                        </div>
                    </div>
                    <div class="wasp-form-group">
                        <label for="inv-cloud-update_quantity">Products to Update</label>
                        <input type="number" id="inv-cloud-update_quantity" name="update_quantity" value="<?= esc_attr($update_quantity) ?>" placeholder="e.g., 100">
                        <p class="description">Number of products to update per batch from Wasp.</p>
                    </div>
                </div>

                <!-- API Credentials Card -->
                <div class="wasp-options-card">
                    <h2>API Credentials</h2>
                    <div class="wasp-form-group">
                        <label for="inv-cloud-base-url">API Base URL</label>
                        <input type="text" id="inv-cloud-base-url" name="api-base-url" value="<?= esc_attr($base_url) ?>" placeholder="https://atebol.waspinventorycloud.com">
                    </div>
                    <div class="wasp-form-group">
                        <label for="inv-cloud-token">Authorization Token</label>
                        <input type="password" id="inv-cloud-token" name="api-token" value="<?= esc_attr($token) ?>" placeholder="Enter your API token">
                    </div>
                    <div class="wasp-form-group">
                        <label for="inv-cloud-api-username">API Username (Basic Auth)</label>
                        <input type="text" id="inv-cloud-api-username" name="api-username" value="<?= esc_attr($api_username) ?>" placeholder="Enter API username">
                    </div>
                    <div class="wasp-form-group">
                        <label for="inv-cloud-api-password">API Password (Basic Auth)</label>
                        <input type="password" id="inv-cloud-api-password" name="api-password" value="<?= esc_attr($api_password) ?>" placeholder="Enter API password">
                    </div>
                </div>
            </div>

            <div class="wasp-options-actions">
                <button type="button" id="inv-cloud-save-btn" class="button button-primary">Save Changes</button>
            </div>

            <!-- API Endpoints Card -->
            <div class="wasp-options-card">
                <h2>API Endpoints</h2>
                <table class="wasp-endpoints-table">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($api_endpoints as $endpoint): ?>
                            <tr>
                                <td class="endpoint-url">
                                    <span class="endpoint-method"><?= esc_html($endpoint['method']) ?></span>
                                    <code><?= esc_html(str_replace(site_url(), '', $endpoint['url'])) ?></code>
                                </td>
                                <td><?= esc_html($endpoint['description']) ?></td>
                                <td class="endpoint-actions">
                                    <button class="button button-secondary run-endpoint-btn" data-url="<?= esc_url($endpoint['url']) ?>">
                                        <span class="dashicons dashicons-controls-play"></span> Run
                                    </button>
                                    <button class="button button-secondary copy-endpoint-btn" data-url="<?= esc_url($endpoint['url']) ?>">
                                        <span class="dashicons dashicons-admin-page"></span> Copy
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- API Response Card -->
            <div id="wasp-api-response-wrapper" class="wasp-options-card" style="display: none;">
                <h2>API Response</h2>
                <button id="clear-response-btn" class="button button-secondary">Clear</button>
                <pre id="wasp-api-response"></pre>
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

    public function wasp_cron_jobs_sub_menu_page_html() {
        include_once PLUGIN_BASE_PATH . '/templates/menus/wasp-cron-jobs.php';
    }

    public function run_wasp_endpoint() {
        check_ajax_referer( 'inv_cloud_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }

        $url = esc_url_raw( $_POST['url'] );

        $api_username = get_option( 'inv_cloud_api_username' );
        $api_password = get_option( 'inv_cloud_api_password' );
        $headers = [];
        if ( $api_username && $api_password ) {
            $headers['Authorization'] = 'Basic ' . base64_encode( $api_username . ':' . $api_password );
        }

        $response = wp_remote_get( $url, [
            'timeout'   => 60,
            'sslverify' => false,
            'headers'   => $headers,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [
                'message' => $response->get_error_message()
            ], 500 );
        } else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true ); // Use true for associative array

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                // If not JSON, return as plain text
                wp_send_json_success( ['data' => $body, 'is_json' => false] );
            }
            wp_send_json_success( ['data' => $data, 'is_json' => true] );
        }
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

    /**
     * Fetch sales returns data for the table
     */
    public function fetch_sales_returns_data() {
        check_ajax_referer( 'wasp_cloud_nonce', 'nonce' );

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        // Get parameters
        $page = intval( $_POST['page'] ?? 1 );
        $per_page = intval( $_POST['per_page'] ?? 10 );
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $status_filter = sanitize_text_field( $_POST['status_filter'] ?? '' );

        // Calculate offset
        $offset = ( $page - 1 ) * $per_page;

        // Build WHERE clause
        $where_conditions = [];
        $where_values = [];

        if ( !empty( $search ) ) {
            $where_conditions[] = "(item_number LIKE %s OR customer_number LIKE %s OR site_name LIKE %s OR location_code LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $search ) . '%';
            $where_values = array_merge( $where_values, [ $search_term, $search_term, $search_term, $search_term ] );
        }

        if ( !empty( $status_filter ) ) {
            $where_conditions[] = "status = %s";
            $where_values[] = $status_filter;
        }

        $where_clause = !empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table $where_clause";
        if ( !empty( $where_values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $where_values );
        }
        $total = $wpdb->get_var( $count_sql );

        // Get data
        $data_sql = "SELECT * FROM $table $where_clause LIMIT %d OFFSET %d";
        $data_values = array_merge( $where_values, [ $per_page, $offset ] );
        $data_sql = $wpdb->prepare( $data_sql, $data_values );
        $results = $wpdb->get_results( $data_sql, ARRAY_A );

        // Calculate pagination info
        $total_pages = ceil( $total / $per_page );

        wp_send_json_success( [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ] );
    }

    /**
     * Fetch orders data for the table
     */
    public function fetch_orders_data() {
        check_ajax_referer( 'wasp_cloud_nonce', 'nonce' );

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sync_wasp_woo_orders_data';

        // Get parameters
        $page = intval( $_POST['page'] ?? 1 );
        $per_page = intval( $_POST['per_page'] ?? 10 );
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $status_filter = sanitize_text_field( $_POST['status_filter'] ?? '' );

        // Calculate offset
        $offset = ( $page - 1 ) * $per_page;

        // Build WHERE clause
        $where_conditions = [];
        $where_values = [];

        if ( !empty( $search ) ) {
            $where_conditions[] = "(item_number LIKE %s OR customer_number LIKE %s OR site_name LIKE %s OR location_code LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $search ) . '%';
            $where_values = array_merge( $where_values, [ $search_term, $search_term, $search_term, $search_term ] );
        }

        if ( !empty( $status_filter ) ) {
            $where_conditions[] = "status = %s";
            $where_values[] = $status_filter;
        }

        $where_clause = !empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table $where_clause";
        if ( !empty( $where_values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $where_values );
        }
        $total = $wpdb->get_var( $count_sql );

        // Get data
        $data_sql = "SELECT * FROM $table $where_clause LIMIT %d OFFSET %d";
        $data_values = array_merge( $where_values, [ $per_page, $offset ] );
        $data_sql = $wpdb->prepare( $data_sql, $data_values );
        $results = $wpdb->get_results( $data_sql, ARRAY_A );

        // Calculate pagination info
        $total_pages = ceil( $total / $per_page );

        wp_send_json_success( [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ] );
    }

}