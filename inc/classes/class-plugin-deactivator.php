<?php

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 */

class Plugin_Deactivator {

    public static function deactivate() {
        // drop tables
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_item_number';
        $sql        = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query( $sql );
        
        // Clean up cron jobs
        self::cleanup_cron_jobs();
    }

    /**
     * Clean up all cron jobs
     */
    public static function cleanup_cron_jobs() {
        $cron_hooks = [
            'wasp_prepare_sales_returns_cron',
            'wasp_prepare_woo_orders_cron',
            'wasp_import_sales_returns_cron',
            'wasp_import_woo_orders_cron',
            'wasp_remove_completed_woo_orders_cron',
            'wasp_remove_completed_sales_returns_cron'
        ];

        foreach ( $cron_hooks as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }
    }

    public static function remove_sync_sales_return_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_sales_returns_data';
        $sql        = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query( $sql );
    }

    public static function remove_sync_wasp_woo_orders_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_wasp_woo_orders_data';
        $sql        = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query( $sql );
    }

}