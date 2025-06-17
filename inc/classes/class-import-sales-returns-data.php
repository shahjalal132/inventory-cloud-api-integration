<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Import_Sales_Returns_Data {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        add_action( 'wp_ajax_import_sales_returns_data', [ $this, 'save_sales_returns_data' ] );
    }

    public function save_sales_returns_data() {
        check_ajax_referer( 'wasp_cloud_nonce', 'nonce' );

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        // Basic validation
        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => 'No valid file was uploaded.' ] );
        }

        if ( empty( $_POST['month'] ) || empty( $_POST['year'] ) ) {
            wp_send_json_error( [ 'message' => 'Month and Year are required.' ] );
        }

        $year          = sanitize_text_field( $_POST['year'] );
        $month         = sanitize_text_field( $_POST['month'] );
        $date_acquired = date( 'Y-m-t 23:59:59', strtotime( "$year-$month-01" ) );

        require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $_FILES['file']['tmp_name'] );
        } catch (Exception $e) {
            wp_send_json_error( [ 'message' => 'Failed to read spreadsheet: ' . $e->getMessage() ] );
        }

        $sheet      = $spreadsheet->getActiveSheet();
        $sheetTitle = $sheet->getTitle();

        // Detect file format by year and sheet name
        if ( $year === '2025' && $sheetTitle === 'Sheet1' ) {
            $map = [ 'item' => 1, 'customer' => 4, 'quantity' => 8 ]; // B, E, I
        } elseif ( $year === '2023' && $sheetTitle === 'gwybodaeth' ) {
            $map = [ 'item' => 1, 'customer' => 5, 'quantity' => 6 ]; // B, F, G
        } else {
            wp_send_json_error( [ 'message' => "Unsupported sheet/tab name: '$sheetTitle'. Expected 'Sheet1' or 'gwybodaeth'" ] );
        }

        $data = $sheet->toArray( null, true, true, false );
        if ( empty( $data ) || count( $data ) < 2 ) {
            wp_send_json_error( [ 'message' => 'The spreadsheet appears to be empty or improperly formatted.' ] );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'sync_sales_returns_data';
        $imported = 0;
        $skipped  = 0;

        foreach ( $data as $i => $row ) {
            if ( $i === 0 )
                continue; // skip header

            $item_number = trim( $row[$map['item']] ?? '' );
            $customer    = strtoupper( trim( $row[$map['customer']] ?? '' ) );
            $qty_raw     = $row[$map['quantity']] ?? null;

            // Skip if quantity is empty or non-numeric
            if ( $item_number === '' || !is_numeric( $qty_raw ) ) {
                $skipped++;
                continue;
            }

            $qty = (float) $qty_raw;
            if ( $qty == 0 ) {
                $skipped++;
                continue;
            }

            $transaction_type = ( $qty < 0 ) ? 'RETURN' : 'SALE';
            $customer_number  = ( $customer === 'AMAZON' ) ? 'AZ 11' : 'CLLC 01';

            $result = $wpdb->insert( $table, [
                'item_number'     => $item_number,
                'cost'            => 0,
                'date_acquired'   => $date_acquired,
                'customer_number' => $customer_number,
                'site_name'       => '',
                'location_code'   => '',
                'quantity'        => abs( $qty ),
                'type'            => $transaction_type,
                'status'          => 'PENDING',
            ], [
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%s',
                '%s',
            ] );

            if ( $result !== false ) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        // Final response
        if ( $imported > 0 ) {
            wp_send_json_success( [
                'message' => "$imported row(s) imported successfully. $skipped row(s) skipped.",
            ] );
        } else {
            wp_send_json_error( [
                'message' => "No rows were imported. $skipped row(s) were skipped due to missing or invalid data.",
            ] );
        }
    }

}