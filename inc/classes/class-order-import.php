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

        // require the autoloader for PhpSpreadsheet
        require_once PLUGIN_BASE_PATH . '/vendor/autoload.php';

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $spreadsheet = $reader->load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Log the first 20 records (skip header)
            $max = min(20, count($rows) - 1);
            for ($i = 1; $i <= $max; $i++) {
                $row = $rows[$i];
                $this->put_program_logs(json_encode($row));
            }
        } catch (\Exception $e) {
            $this->put_program_logs('Failed to read CSV: ' . $e->getMessage());
            wp_send_json_error([ 'message' => 'Failed to read CSV: ' . $e->getMessage() ]);
        }

        wp_send_json_success([ 'message' => 'CSV file uploaded successfully!' ]);
    }

}