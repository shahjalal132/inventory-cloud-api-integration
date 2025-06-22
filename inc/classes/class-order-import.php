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
        add_action( 'wp_ajax_wasp_import_woocommerce_orders', [ $this, 'handle_order_import' ] );
    }

    public function handle_order_import() {
        check_ajax_referer( 'wasp_cloud_nonce', 'nonce' );

        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => 'No file uploaded or upload error.' ] );
        }

        $file     = $_FILES['file'];
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $file_ext !== 'csv' ) {
            wp_send_json_error( [ 'message' => 'Only CSV files are supported.' ] );
        }

        require_once PLUGIN_BASE_PATH . '/vendor/autoload.php';

        global $wpdb;
        $table = $wpdb->prefix . 'sync_wasp_woo_orders_data';

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setDelimiter( ',' ); // Optional: Set delimiter if not comma
            $spreadsheet = $reader->load( $file['tmp_name'] );
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();

            // Skip header row
            for ( $i = 1; $i < count( $rows ); $i++ ) {
                $row = $rows[$i];

                $item_number     = isset( $row[3] ) ? trim( $row[3] ) : '';
                $customer_number = 'GA 10';
                $site_name       = '';
                $location_code   = '';
                $cost            = isset( $row[7] ) ? abs( (float) $row[7] ) : 0;
                $quantity        = isset( $row[6] ) ? (float) $row[6] : 0;
                $remove_date_raw = isset( $row[1] ) ? trim( $row[1] ) : '';

                // Convert remove_date to ISO 8601 format
                $remove_date = '';
                if ( !empty( $remove_date_raw ) ) {
                    $date_obj = \DateTime::createFromFormat( 'd/m/Y H:i', $remove_date_raw );
                    if ( $date_obj !== false ) {
                        $remove_date = $date_obj->format( 'Y-m-d\TH:i:s\Z' );
                    }
                }

                // Insert into database
                $wpdb->insert( $table, [
                    'item_number'     => $item_number,
                    'customer_number' => $customer_number,
                    'site_name'       => $site_name,
                    'location_code'   => $location_code,
                    'cost'            => $cost,
                    'quantity'        => $quantity,
                    'remove_date'     => $remove_date,
                    'status'          => 'PENDING',
                ] );
            }

            wp_send_json_success( [ 'message' => 'CSV file processed and inserted successfully!' ] );

        } catch (\Exception $e) {
            $this->put_program_logs( 'Failed to read or process CSV: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'Failed to process CSV: ' . $e->getMessage() ] );
        }
    }


}