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

    }

}