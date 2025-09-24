<?php

/**
 * Helper Functions
 * 
 * @package WP Plugin Boilerplate
 */

/**
 * Validate item number to ensure it's not empty, blank, and contains only numeric characters
 *
 * @param string $item_number The item number to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_item_number( $item_number ) {
    // Check if item number is empty or null
    if ( empty( $item_number ) || is_null( $item_number ) ) {
        return false;
    }
    
    // Trim whitespace and check if empty after trimming
    $item_number = trim( $item_number );
    if ( empty( $item_number ) ) {
        return false;
    }
    
    // Check if item number contains only numeric characters (including decimals if needed)
    // This regex allows positive integers and positive decimals
    if ( !preg_match( '/^[0-9]+(\.[0-9]+)?$/', $item_number ) ) {
        return false;
    }
    
    // Additional check: ensure it's not just dots or special characters
    if ( !is_numeric( $item_number ) ) {
        return false;
    }
    
    return true;
}