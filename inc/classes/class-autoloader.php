<?php
/**
 * Bootstraps the plugin. load class.
 */

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Singleton;

class Autoloader {
    use Singleton;

    protected function __construct() {

        // load class.
        I18n::get_instance();
        Enqueue_Assets::get_instance();
        Update_Inventory::get_instance();
        Admin_Menu::get_instance();
        Import_Sales_Returns_Data::get_instance();
        Wasp_Rest_Api::get_instance();
        Order_Import::get_instance();
        Jobs::get_instance();
        Retry::get_instance();
    }
}