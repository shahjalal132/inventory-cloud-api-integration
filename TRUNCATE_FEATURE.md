# Truncate Table Feature - Documentation

## Overview
A secure and user-friendly feature to truncate (permanently delete all records) from the Orders and Sales Returns tables. Includes strong confirmation dialogs, danger styling, and comprehensive logging.

## Features Implemented

### 1. Danger Zone UI
- ⚠️ Red danger styling with warning colors
- Clear warning messages about irreversibility
- Detailed information about what will be deleted
- Responsive design for all screen sizes
- Professional card-based layout

### 2. Dual Confirmation System
**First Confirmation (Alert):**
- Shows table name and full consequences
- Lists all data that will be lost
- Clear "cannot be undone" warning
- User can cancel at this stage

**Second Confirmation (Prompt):**
- Requires typing exact text: `DELETE ALL ORDERS` or `DELETE ALL SALES RETURNS`
- Prevents accidental clicks
- Validates input matches exactly
- Shows error if text doesn't match

### 3. Security Features
- ✅ AJAX nonce verification
- ✅ WordPress capability check (`manage_options`)
- ✅ Input sanitization
- ✅ Table name validation (whitelist only)
- ✅ Table existence verification

### 4. Logging
- Logs before truncation (with user and record count)
- Logs success/failure
- Logs to `program_logs.txt`
- Includes timestamps and user information

### 5. Auto-cleanup
- Resets retry tracking options after truncation
- Clears `wasp_order_retry_last_processed_id`
- Clears `wasp_sales_return_retry_last_processed_id`

## Files Modified

### 1. `templates/menus/wasp-retry.php`
Added danger zone section with two cards:
- Orders truncate card
- Sales Returns truncate card

Each card includes:
- Warning icon
- Detailed danger message
- List of consequences
- Danger-styled button

### 2. `assets/admin/css/admin-retry.css`
Added styles:
- `.wasp-danger-zone` - Container styling
- `.truncate-card` - Red-bordered cards
- `.danger-warning` - Yellow warning box
- `.danger-info` - Warning message styling
- `.button-danger` - Red danger button
- Loading states
- Responsive design

### 3. `assets/admin/js/admin-retry.js`
Added JavaScript:
- Click handler for truncate buttons
- Dual confirmation dialogs
- AJAX request to server
- Loading state management
- Success/error notifications
- Stats refresh after truncation

### 4. `inc/classes/class-retry.php`
Added PHP:
- `handle_truncate_table()` - AJAX handler
- Security checks (nonce, capabilities)
- Table validation
- Truncation logic
- Logging
- Cleanup operations

## User Flow

```
1. User clicks "Truncate Orders Table" button
   ↓
2. First Confirmation Dialog appears
   - Shows: Table name, consequences, warning
   - Options: OK or Cancel
   ↓
3. If user clicks OK → Second Confirmation Dialog
   - Requires typing: "DELETE ALL ORDERS"
   - Validates exact match
   ↓
4. If text matches → AJAX call to server
   - Button shows "Truncating..." with loading spinner
   - Card becomes semi-transparent (loading state)
   ↓
5. Server processes request
   - Verifies nonce
   - Checks capabilities
   - Validates table
   - Logs action
   - Truncates table
   - Resets tracking options
   ↓
6. Response to client
   - Success: Green notification with count of deleted records
   - Error: Red notification with error message
   ↓
7. Stats refresh automatically
   - Shows updated counts (should be 0)
```

## Confirmation Dialogs

### First Confirmation (Alert)
```
⚠️ DANGER: You are about to PERMANENTLY DELETE all records!

Table: wp_sync_wasp_woo_orders_data

This will remove:
• All orders records
• All statuses (PENDING, READY, FAILED, IGNORED, COMPLETED)
• All API responses and messages

❌ THIS ACTION CANNOT BE UNDONE! ❌

Are you absolutely sure you want to continue?
```

### Second Confirmation (Prompt)
```
⚠️ FINAL CONFIRMATION REQUIRED ⚠️

To confirm deletion, please type exactly:

DELETE ALL ORDERS

This will permanently delete all records from the orders table.
```

## Security Implementation

### 1. Nonce Verification
```php
check_ajax_referer( 'wasp-retry-nonce', 'nonce' );
```

### 2. Capability Check
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( [ 'message' => 'Access denied...' ] );
}
```

### 3. Input Validation
```php
$table_type = sanitize_text_field( $_POST['table'] );

if ( ! in_array( $table_type, [ 'orders', 'sales_returns' ] ) ) {
    wp_send_json_error( [ 'message' => 'Invalid table type...' ] );
}
```

### 4. Table Existence Check
```php
$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );

if ( ! $table_exists ) {
    wp_send_json_error( [ 'message' => "Table does not exist." ] );
}
```

## Logging Examples

### Before Truncation
```
[2025-10-25 10:30:15] [DANGER] User admin is truncating table: wp_sync_wasp_woo_orders_data (Records: 1523)
```

### After Success
```
[2025-10-25 10:30:16] [SUCCESS] Table wp_sync_wasp_woo_orders_data truncated successfully. Records before: 1523, Records after: 0
```

### On Error
```
[2025-10-25 10:30:16] [ERROR] Failed to truncate table: wp_sync_wasp_woo_orders_data. Error: Table doesn't exist
```

## CSS Classes

### Danger Zone
- `.wasp-danger-zone` - Main container
- `.truncate-table-section` - Grid container for cards
- `.truncate-card` - Individual card styling
- `.danger-card` - Red border and shadow
- `.truncate-card-header` - Card header section
- `.truncate-card-title` - Card title styling

### Warning Section
- `.danger-warning` - Yellow warning box
- `.danger-info` - Warning content container
- `.danger-info strong` - Bold warning text
- `.danger-info code` - Code styling for table names
- `.danger-info ul/li` - List styling

### Button Styling
- `.button-danger` - Red danger button
- `.button-danger:hover` - Hover effects
- `.button-danger:disabled` - Disabled state
- `.truncate-btn.loading` - Loading state with spinner

## JavaScript Functions

### Truncate Handler
```javascript
$('.truncate-btn').on('click', function () {
    // Get button and table info
    // Show first confirmation
    // Show second confirmation with typing
    // Validate input
    // Send AJAX request
    // Handle response
    // Update UI
});
```

### Confirmation Flow
1. `confirm()` - First alert dialog
2. `prompt()` - Second typing confirmation
3. Text validation
4. AJAX call if valid

## AJAX Request

### Request Data
```javascript
{
    action: 'truncate_table',
    table: 'orders' or 'sales_returns',
    nonce: wpRetryData.nonce
}
```

### Success Response
```json
{
    "success": true,
    "data": {
        "message": "✅ Orders table truncated successfully. 1523 records were permanently deleted.",
        "records_deleted": 1523,
        "table_name": "wp_sync_wasp_woo_orders_data"
    }
}
```

### Error Response
```json
{
    "success": false,
    "data": {
        "message": "Access denied. You do not have permission to perform this action."
    }
}
```

## Testing Guide

### Test 1: Cancel First Confirmation
1. Click "Truncate Orders Table"
2. Click "Cancel" on first dialog
3. **Expected**: No action taken, button remains enabled

### Test 2: Cancel Second Confirmation
1. Click "Truncate Orders Table"
2. Click "OK" on first dialog
3. Click "Cancel" on prompt dialog
4. **Expected**: No action taken, button remains enabled

### Test 3: Wrong Text in Second Confirmation
1. Click "Truncate Orders Table"
2. Click "OK" on first dialog
3. Type "delete all orders" (lowercase)
4. **Expected**: Error message, no action taken

### Test 4: Correct Flow
1. Click "Truncate Orders Table"
2. Click "OK" on first dialog
3. Type "DELETE ALL ORDERS" exactly
4. **Expected**: 
   - Button shows "Truncating..."
   - Success notification appears
   - Stats update to show 0 records

### Test 5: Non-Admin User
1. Login as editor or subscriber
2. Try to access retry settings page
3. **Expected**: Either can't access page or truncate fails with permission error

### Test 6: Network Error
1. Disable network in browser DevTools
2. Try to truncate
3. **Expected**: Error notification appears, button returns to normal

## Database Impact

### Before Truncation
```sql
SELECT COUNT(*) FROM wp_sync_wasp_woo_orders_data;
-- Result: 1523 records
```

### After Truncation
```sql
SELECT COUNT(*) FROM wp_sync_wasp_woo_orders_data;
-- Result: 0 records
```

### Table Structure Preserved
```sql
DESCRIBE wp_sync_wasp_woo_orders_data;
-- Table structure remains intact
-- Only data is removed
-- Auto-increment resets to 1
```

## Cleanup Operations

After successful truncation:

1. **Reset Tracking Options**
   ```php
   delete_option( 'wasp_order_retry_last_processed_id' );
   delete_option( 'wasp_sales_return_retry_last_processed_id' );
   ```

2. **Refresh Stats**
   - Stats automatically refresh after 1 second
   - Shows 0 for all counts

## Best Practices

### Before Truncating
1. ✅ Backup the database
2. ✅ Export data if needed
3. ✅ Verify you're truncating the correct table
4. ✅ Understand the consequences

### During Truncation
1. ✅ Read all warnings carefully
2. ✅ Type the confirmation text exactly
3. ✅ Wait for completion
4. ✅ Check success message

### After Truncation
1. ✅ Verify stats show 0 records
2. ✅ Check program logs
3. ✅ Document the action
4. ✅ Notify team if needed

## Styling

### Colors Used
- **Danger Red**: `#dc3545` (buttons, borders)
- **Warning Yellow**: `#fff3cd` (warning box background)
- **Warning Dark**: `#856404` (warning text)
- **Error Red**: `#721c24` (titles)
- **Success Green**: `#28a745` (success messages)

### Typography
- **Titles**: 18px, Bold
- **Warnings**: 14px, Bold
- **Messages**: 13px, Regular
- **Code**: 12px, Monospace

## Responsive Design

### Desktop (> 768px)
- Two-column grid layout
- Full padding and spacing
- Side-by-side cards

### Mobile (< 768px)
- Single column layout
- Reduced padding
- Stacked cards
- Touch-friendly buttons

## Error Handling

### Client-Side
- Validates table type before sending
- Shows user-friendly error messages
- Maintains button state on error
- Allows retry after error

### Server-Side
- Validates nonce and capabilities
- Checks table existence
- Handles database errors gracefully
- Logs all errors
- Returns detailed error messages

## Performance

### Truncation Speed
- TRUNCATE is faster than DELETE
- Doesn't scan table
- Resets auto-increment
- Returns instantly for most tables

### Resource Usage
- Minimal memory usage
- No row-by-row processing
- Single SQL command
- Lightweight AJAX call

## Limitations

1. **Cannot be undone** - Permanent deletion
2. **No selective deletion** - All or nothing
3. **No soft delete** - Physical removal
4. **No recycle bin** - No recovery option

## Future Enhancements

Possible improvements:
1. **Backup before truncate** - Automatic backup creation
2. **Scheduled truncation** - Cron-based cleanup
3. **Selective deletion** - Delete by date range or status
4. **Export before truncate** - Auto-export to CSV
5. **Confirmation via email** - Email verification code
6. **Audit trail** - Separate audit log table
7. **Restore from backup** - One-click restore

## Troubleshooting

### Button doesn't work
- Check browser console for JavaScript errors
- Verify jQuery is loaded
- Check nonce is being passed

### Permission denied error
- Verify user has `manage_options` capability
- Check user role is Administrator
- Review security plugins

### Table doesn't exist error
- Verify table name is correct
- Check database prefix
- Run plugin activation again

### Truncation fails silently
- Check WordPress debug log
- Review program_logs.txt
- Check database error logs
- Verify database permissions

## Summary

✅ **Implemented:**
- Danger zone UI with warning styling
- Dual confirmation system (alert + typed confirmation)
- AJAX handler with full security
- Comprehensive logging
- Auto-cleanup of tracking options
- Loading states and notifications
- Responsive design
- Error handling

✅ **Security:**
- Nonce verification
- Capability checks
- Input validation
- Table whitelist
- Existence verification

✅ **User Experience:**
- Clear warnings
- Multiple confirmations
- Loading feedback
- Success/error messages
- Stats refresh

The truncate feature is now fully functional, secure, and user-friendly! ⚠️

