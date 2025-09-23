<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 */

class Plugin_Activator {

    public static function activate() {
        // create sync_item_number table
        global $wpdb;
        $table_name      = $wpdb->prefix . 'sync_item_number';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT,
            item_number VARCHAR(255) UNIQUE NOT NULL,
            quantity INT NOT NULL,
            status VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function create_sync_sales_return_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'sync_sales_returns_data';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_number VARCHAR(255) NOT NULL,
            cost DECIMAL(10,2) NOT NULL,
            date_acquired VARCHAR(50) NULL,
            customer_number VARCHAR(255) NULL,
            site_name VARCHAR(255) NULL,
            location_code VARCHAR(255) NULL,
            quantity DECIMAL(10,2) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT '',
            format VARCHAR(32) NULL,
            status VARCHAR(255) NOT NULL DEFAULT 'PENDING',
            api_response LONGTEXT NULL,
            message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function create_sync_wasp_woo_orders_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'sync_wasp_woo_orders_data';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_number VARCHAR(255) NOT NULL,
            customer_number VARCHAR(255) NULL,
            site_name VARCHAR(255) NULL,
            location_code VARCHAR(255) NULL,
            cost DECIMAL(10,2) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            remove_date VARCHAR(50) NULL,
            status VARCHAR(255) NOT NULL DEFAULT 'PENDING',
            api_response LONGTEXT NULL,
            message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

}