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
            wp_send_json_error( [ 'message' => 'Access denied.' ] );
        }

        if ( !isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => 'File upload error.' ] );
        }

        $file = $_FILES['file'];

        // Move the uploaded file to a temporary location
        $temp_path = $file['tmp_name'];

        $rows = [];

        // Open the file and read line by line
        if ( ( $handle = fopen( $temp_path, 'r' ) ) !== false ) {
            while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
                $rows[] = $data;
            }
            fclose( $handle );
        } else {
            wp_send_json_error( [ 'message' => 'Unable to read the file.' ] );
        }

        // Log the rows (optional: limit the amount logged)
        foreach ( $rows as $index => $row ) {
            $line = implode( ' | ', $row );
            $this->put_program_logs( "Row $index: $line" );
        }

        wp_send_json_success( [
            'message' => 'File processed and data logged successfully.',
            'rows'    => $rows, // Optional: send data back to frontend if needed
        ] );
    }

}