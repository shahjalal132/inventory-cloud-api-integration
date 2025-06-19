<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Order_Import {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        add_action('wp_ajax_wasp_import_woocommerce_orders', [ $this, 'handle_order_import' ]);
    }

    public function handle_order_import() {
        check_ajax_referer('wasp_cloud_nonce', 'nonce');

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error([ 'message' => 'No file uploaded or upload error.' ]);
        }

        $file = $_FILES['file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            wp_send_json_error([ 'message' => 'Only CSV files are supported.' ]);
        }

        // You can add further CSV validation/processing here
        // For now, just return success
        wp_send_json_success([ 'message' => 'CSV file uploaded successfully!' ]);
    }

}