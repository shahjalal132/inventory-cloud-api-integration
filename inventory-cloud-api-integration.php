<?php

use BOILERPLATE\Inc\Cleanup_Scheduler;

/**
 *  
 * Plugin Name: Inventory Cloud API Integration
 * Plugin URI:  https://github.com/shahjalal132/inventory-cloud-api-integration
 * Author:      Shah Jalal
 * Author URI:  https://github.com/shahjalal132
 * Description: Update inventory in woocommerce and store bidirectionally
 * Version:     3.0.0
 * text-domain: inventory-cloud
 * Domain Path: /languages
 * 
 */

defined( "ABSPATH" ) || exit( "Direct Access Not Allowed" );

// Define plugin base NAME
if ( !defined( 'PLUGIN_BASENAME' ) ) {
    define( 'PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Define plugin base path
if ( !defined( 'PLUGIN_BASE_PATH' ) ) {
    define( 'PLUGIN_BASE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

// Define plugin base url
if ( !defined( 'PLUGIN_BASE_URL' ) ) {
    define( 'PLUGIN_BASE_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
}

// Define admin assets dir path
if ( !defined( 'PLUGIN_ADMIN_ASSETS_DIR_PATH' ) ) {
    define( 'PLUGIN_ADMIN_ASSETS_DIR_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) . '/assets/admin' ) );
}

// Define plugin admin assets url
if ( !defined( 'PLUGIN_ADMIN_ASSETS_DIR_URL' ) ) {
    define( 'PLUGIN_ADMIN_ASSETS_DIR_URL', untrailingslashit( plugin_dir_url( __FILE__ ) . '/assets/admin' ) );
}

// Define plugin public assets url
if ( !defined( 'PLUGIN_PUBLIC_ASSETS_URL' ) ) {
    define( 'PLUGIN_PUBLIC_ASSETS_URL', untrailingslashit( plugin_dir_url( __FILE__ ) . '/assets/public' ) );
}

// Define plugin libs dir path
if ( !defined( 'PLUGIN_LIBS_DIR_PATH' ) ) {
    define( 'PLUGIN_LIBS_DIR_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) . '/inc/libs' ) );
}

// Define plugin libs url
if ( !defined( 'PLUGIN_LIBS_DIR_URL' ) ) {
    define( 'PLUGIN_LIBS_DIR_URL', untrailingslashit( plugin_dir_url( __FILE__ ) . '/inc/libs' ) );
}

// Require files
require_once PLUGIN_BASE_PATH . '/loader.php';

// Ensure enum is loaded
class_exists( 'BOILERPLATE\Inc\Enums\Status_Enums' );
require_once PLUGIN_BASE_PATH . '/inc/helpers/autoloader.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in inc/classes/class-plugin-activator.php file
 */
function wpb_plugin_activator() {
    require_once PLUGIN_BASE_PATH . '/inc/classes/class-plugin-activator.php';
    Plugin_Activator::activate();
    Plugin_Activator::create_sync_sales_return_table();
    Plugin_Activator::create_sync_wasp_woo_orders_table();
    Plugin_Activator::create_sync_wasp_retry_items_table();
    Cleanup_Scheduler::get_instance()->maybe_schedule_weekly_cleanup();
}

// Register activation hook
register_activation_hook( __FILE__, 'wpb_plugin_activator' );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in inc/classes/class-plugin-deactivator.php file
 */
function wpb_plugin_deactivator() {
    require_once PLUGIN_BASE_PATH . '/inc/classes/class-plugin-deactivator.php';
    Plugin_Deactivator::deactivate();
    // Plugin_Deactivator::remove_sync_sales_return_table();
    // Plugin_Deactivator::remove_sync_wasp_woo_orders_table();
    // Plugin_Deactivator::remove_sync_wasp_retry_items_table();
    Cleanup_Scheduler::get_instance()->clear_scheduled_event();
}

// Register deactivation hook
register_deactivation_hook( __FILE__, 'wpb_plugin_deactivator' );


function get_plugin_instance() {
    \BOILERPLATE\Inc\Autoloader::get_instance();
}

// Load plugin
get_plugin_instance();