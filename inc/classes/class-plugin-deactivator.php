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
    }

}