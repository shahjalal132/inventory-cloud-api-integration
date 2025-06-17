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
        $date_acquired = gmdate( 'Y-m-t\T23:59:59\Z', strtotime( "$year-$month-01" ) );
        // generate format
        $format = sprintf( "%s - %s", $month, $year );

        $date_message = sprintf( 'Importing data for %s %s and date acquired: %s', $month, $year, $date_acquired );
        // log message
        // $this->put_program_logs( $date_message );

        // Initialize counters
        $imported = 0;
        $skipped  = 0;

        // Get database table name
        global $wpdb;
        $table = $wpdb->prefix . 'sync_sales_returns_data';

        // Truncate the table before import (for testing)
        $this->truncate_table( $table );

        // require the autoloader
        require_once PLUGIN_BASE_PATH . '/vendor/autoload.php';

        try {
            // load the spreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $_FILES['file']['tmp_name'] );
            if ( $year === '2023' ) {
                $spreadsheet->setActiveSheetIndex( 2 );
            } else {
                $spreadsheet->setActiveSheetIndex( 0 );
            }
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
        // $this->put_program_logs( $sheet_message );

        // Get column mapping and initial row index based on year and sheet name
        $mapping_info  = $this->get_column_map_and_index( $year, $sheetTitle );
        $map           = $mapping_info['map'];
        $initial_index = $mapping_info['initial_index'];

        // If mapping is empty, unsupported format
        if ( empty( $map ) ) {
            $error_message = sprintf( 'Unsupported sheet/tab name: %s for year %s', $sheetTitle, $year );
            $this->put_program_logs( $error_message );
            wp_send_json_error( [ 'message' => $error_message ] );
        }

        $highestRow    = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Start from the initial index to skip headers and empty rows
        for ( $row = $initial_index; $row <= $highestRow; $row++ ) {
            // Convert column indexes to letters for each mapped column
            $item_col     = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['item'] + 1 );
            $customer_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['customer'] + 1 );
            $qty_col      = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['quantity'] + 1 );
            $cost_col     = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $map['cost'] + 1 );

            // Get the cell values for this row
            $item_number = $sheet->getCell( $item_col . $row )->getValue();
            $customer    = $sheet->getCell( $customer_col . $row )->getValue();
            $qty         = $sheet->getCell( $qty_col . $row )->getValue();
            $cost        = $sheet->getCell( $cost_col . $row )->getValue();

            // Skip empty or invalid rows
            if ( empty( $item_number ) || empty( $customer ) || empty( $qty ) ) {
                $skipped++;
                continue;
            }

            // Clean up Excel string values
            $item_number = $this->clean_excel_value( $item_number );
            $customer    = $this->clean_excel_value( $customer );

            // Convert quantity to float and determine transaction type
            $qty              = floatval( $qty );
            $transaction_type = ( $qty < 0 ) ? 'RETURN' : 'SALE';
            $customer_number  = ( $customer === 'AMAZON' ) ? 'AZ 11' : 'CLLC 01';

            // Prepare data for DB insert
            $data = [
                'item_number'     => $item_number,
                'cost'            => $cost ?? 0,
                'date_acquired'   => $date_acquired,
                'customer_number' => $customer_number,
                'site_name'       => '',
                'location_code'   => '',
                'quantity'        => abs( $qty ),
                'type'            => $transaction_type,
                'format'          => $format,
                'status'          => 'PENDING',
            ];

            // Insert row into DB using helper
            $result = $this->insert_sales_return_row( $table, $data );

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

    public function truncate_table( $table_name ) {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `$table_name`" );
    }

    /**
     * Get the column mapping and initial row index based on year and sheet name.
     *
     * @param string $year
     * @param string $sheetTitle
     * @return array [ 'map' => array, 'initial_index' => int ]
     */
    private function get_column_map_and_index( $year, $sheetTitle ) {
        // Default values
        $map           = [];
        $initial_index = 0;

        // Add new formats here as needed
        if ( $year === '2025' && $sheetTitle === 'Sheet1' ) {
            $map           = [
                'item'     => 1,  // Product No. (Column B)
                'customer' => 4,  // Customer (Column E)
                'quantity' => 8,  // Quantity (Column I)
                'cost'     => 6   // Cost (Column G)
            ];
            $initial_index = 8; // Data starts from row 8
        } elseif ( $year === '2023' && $sheetTitle === 'gwybodaeth' ) {
            $map           = [
                'item'     => 1, // Product No. (Column B)
                'customer' => 5, // Customer (Column F)
                'quantity' => 6, // Quantity (Column G)
                'cost'     => 3  // Cost (Column D)
            ];
            $initial_index = 8; // Data starts from row 8
        }
        // Add more formats as needed

        return [ 'map' => $map, 'initial_index' => $initial_index ];
    }

    /**
     * Insert a row into the sync_sales_returns_data table.
     *
     * @param string $table
     * @param array $data
     * @return bool|int Insert result
     */
    private function insert_sales_return_row( $table, $data ) {
        global $wpdb;
        return $wpdb->insert( $table, $data, [
            '%s', // item_number
            '%f', // cost
            '%s', // date_acquired
            '%s', // customer_number
            '%s', // site_name
            '%s', // location_code
            '%f', // quantity
            '%s', // type
            '%s', // format
            '%s', // status
        ] );
    }

    /**
     * Clean Excel string values like ="9781905255191" to 9781905255191
     */
    private function clean_excel_value( $value ) {
        if ( is_string( $value ) && preg_match( '/^="(.*)"$/', $value, $matches ) ) {
            return $matches[1];
        }
        return $value;
    }

}