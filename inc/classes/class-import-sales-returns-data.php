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
        // check the nonce
        check_ajax_referer( 'wasp_cloud_nonce', 'nonce' );

        // check if the user has the required capability
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        // Basic validation
        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => 'No valid file was uploaded.' ] );
        }

        // check if the month and year are set
        if ( empty( $_POST['month'] ) || empty( $_POST['year'] ) ) {
            wp_send_json_error( [ 'message' => 'Month and Year are required.' ] );
        }

        // sanitize the year and month
        $year          = sanitize_text_field( $_POST['year'] );
        $month         = sanitize_text_field( $_POST['month'] );
        $date_acquired = date( 'Y-m-t 23:59:59', strtotime( "$year-$month-01" ) );

        $date_message = sprintf( 'Importing data for %s %s and date acquired: %s', $month, $year, $date_acquired );
        // log message
        $this->put_program_logs( $date_message );

        // Initialize counters
        $imported = 0;
        $skipped = 0;

        // Get database table name
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        // require the autoloader
        require_once PLUGIN_BASE_PATH . '/vendor/autoload.php';

        try {
            // load the spreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $_FILES['file']['tmp_name'] );
            $spreadsheet->setActiveSheetIndex( 0 );
        } catch (\Exception $e) {
            // send an error message
            $this->put_program_logs( 'Failed to read spreadsheet: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'Failed to read spreadsheet: ' . $e->getMessage() ] );
        }

        // get the active sheet
        $sheet      = $spreadsheet->getActiveSheet();
        $sheetTitle = $sheet->getTitle();

        // log active sheet
        $sheet_message = sprintf( 'Active sheet: %s', $sheetTitle );
        $this->put_program_logs( $sheet_message );

        // Detect file format by year and sheet name
        if ( $year === '2025' && $sheetTitle === 'Sheet1' ) {
            $map = [
                'item'        => 1,      // Product No. (Column B)
                'customer'    => 4,  // Customer (Column E)
                'quantity'    => 8,  // Quantity (Column I)
                'description' => 2 // Product Description (Column C)
            ];
        } elseif ( $year === '2023' && $sheetTitle === 'gwybodaeth' ) {
            $map = [
                'item'        => 1,
                'customer'    => 5,
                'quantity'    => 6,
                'description' => 2,
            ];
        } else {
            // prepare message
            $error_message = sprintf( 'Unsupported sheet/tab name: %s. Expected %s or %s', $sheetTitle, 'Sheet1', 'gwybodaeth' );
            // log message
            $this->put_program_logs( $error_message );
            wp_send_json_error( [ 'message' => $error_message ] );
        }

        $highestRow    = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // log highest row and column
        $highest_row_message = sprintf( 'Highest row: %s, Highest column: %s', $highestRow, $highestColumn );
        $this->put_program_logs( $highest_row_message );

        // Start from row 8 to skip headers and empty rows
        for ( $row = 8; $row <= $highestRow; $row++ ) {
            // Convert column indexes to letters
            $item_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['item'] + 1 );
            $customer_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['customer'] + 1 );
            $qty_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['quantity'] + 1 );
            $desc_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['description'] + 1 );

            // get the cell value
            $item_number = $sheet->getCell( $item_col . $row )->getValue();
            $customer    = $sheet->getCell( $customer_col . $row )->getValue();
            $qty         = $sheet->getCell( $qty_col . $row )->getValue();
            $description = $sheet->getCell( $desc_col . $row )->getValue();

            // Skip empty rows
            if ( empty( $item_number ) || empty( $customer ) || empty( $qty ) ) {
                continue;
            }

            // Convert quantity to float and determine transaction type
            $qty              = floatval( $qty );
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
            // prepare message
            $message = sprintf( '%s row(s) imported successfully. %s row(s) skipped.', $imported, $skipped );
            // log message
            $this->put_program_logs( $message );
            wp_send_json_success( [
                'message' => $message,
            ] );
        } else {
            // prepare message
            $message = sprintf( 'No rows were imported. %s row(s) were skipped due to missing or invalid data.', $skipped );
            // log message
            $this->put_program_logs( $message );
            wp_send_json_error( [
                'message' => $message,
            ] );
        }
    }

}