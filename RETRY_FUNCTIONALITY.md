# Retry Functionality - Implementation Summary

## Overview
The retry functionality allows automatic and manual retrying of FAILED and IGNORED items from both the WooCommerce Orders and Sales Returns import processes.

## Features Implemented

### 1. Database Table
**Table Name**: `wp_sync_wasp_retry_items`

**Columns**:
- `id` - Primary key
- `original_id` - ID from the original source table
- `item_type` - Either 'order' or 'sales_return'
- `item_number` - The item number being retried
- `original_status` - The status when it was added (FAILED or IGNORED)
- `retry_status` - Current retry status (PENDING, PROCESSING, COMPLETED)
- `retry_count` - Number of times this item has been retried
- `last_retry_at` - Timestamp of last retry attempt
- `retry_message` - Any messages from retry attempts
- `created_at` - When the retry record was created
- `updated_at` - Last update timestamp

### 2. REST API Endpoints

#### `/wp-json/atebol/v1/order-retry` (GET)
- Fetches FAILED and IGNORED items from orders table
- Checks if retry is enabled via `wasp_order_retry_enable` option
- Adds items to retry queue
- Automatically changes status back to PENDING for reprocessing
- Returns summary of items added

#### `/wp-json/atebol/v1/sales-return-retry` (GET)
- Fetches FAILED and IGNORED items from sales returns table
- Checks if retry is enabled via `wasp_sales_return_retry_enable` option
- Adds items to retry queue
- Automatically changes status back to PENDING for reprocessing
- Returns summary of items added

#### `/wp-json/atebol/v1/retry-stats` (GET)
- Returns statistics for both orders and sales returns
- Shows counts of: ignored, failed, total issues, retried, and successfully retried items

### 3. Admin Interface Features

#### Toggle Switches
- **Orders Retry Toggle**: Enable/disable automatic retry for orders
- **Sales Returns Retry Toggle**: Enable/disable automatic retry for sales returns
- Both toggles save settings to WordPress options table
- Real-time status badge updates (Enabled/Disabled)

#### Instant Retry Buttons
- **Orders Instant Retry**: Manually trigger retry process for orders without checking enable/disable status
- **Sales Returns Instant Retry**: Manually trigger retry process for sales returns without checking enable/disable status
- Shows loading state during processing
- Displays success/error notifications

#### Statistics Display
Each card shows:
- **Ignored Items**: Count of items with IGNORED status
- **Failed Items**: Count of items with FAILED status
- **Total Issues**: Sum of ignored and failed
- **Successfully Retried**: Count of items successfully processed through retry

Stats auto-refresh every 30 seconds.

### 4. AJAX Actions

**Implemented AJAX Handlers**:
- `toggle_order_retry` - Enable/disable order retry
- `toggle_sales_return_retry` - Enable/disable sales return retry
- `instant_order_retry` - Manually trigger order retry
- `instant_sales_return_retry` - Manually trigger sales return retry
- `get_retry_stats` - Fetch current statistics

All AJAX calls are secured with nonces (`wasp-retry-nonce`).

## How It Works

### Automatic Retry Flow
1. Cron job calls the REST API endpoint (e.g., `/order-retry`)
2. Endpoint checks if retry is enabled
3. If enabled, fetches up to 50 FAILED/IGNORED items
4. For each item:
   - Checks if already in retry queue (skip if exists)
   - Inserts into retry table
   - Updates original item status to PENDING
   - Increments retry count
5. Items are automatically picked up by the normal processing endpoints

### Manual Retry Flow
1. Admin clicks "Instant Retry" button
2. AJAX call bypasses enable/disable check
3. Immediately fetches and processes FAILED/IGNORED items
4. Updates stats in real-time
5. Shows notification with results

### Retry Logic
When an item is retried:
1. Original item status is changed from FAILED/IGNORED → PENDING
2. Retry record is created/updated in retry table
3. Retry count is incremented
4. Item is picked up by next run of:
   - `prepare-woo-orders` endpoint (for orders)
   - `prepare-sales-returns` endpoint (for sales returns)
5. If successful, item status becomes READY → COMPLETED
6. If failed again, item stays FAILED and can be retried again

## Settings

### WordPress Options
- `wasp_order_retry_enable` - Boolean, controls if order retry is active
- `wasp_sales_return_retry_enable` - Boolean, controls if sales return retry is active

## Usage

### For End Users
1. Navigate to **Wasp Dashboard → Retry Settings**
2. Toggle on/off automatic retry for each type
3. View current statistics
4. Click "Instant Retry" to manually trigger retry process

### For Developers

#### Calling Retry Endpoints Programmatically
```php
// Enable order retry
update_option('wasp_order_retry_enable', true);

// Trigger retry
$response = wp_remote_get(site_url('/wp-json/atebol/v1/order-retry'));
```

#### Getting Retry Statistics
```php
$response = wp_remote_get(site_url('/wp-json/atebol/v1/retry-stats'));
$data = json_decode(wp_remote_retrieve_body($response), true);

// Access stats
$order_stats = $data['orders'];
$sales_stats = $data['sales_returns'];
```

## Files Modified/Created

### Modified Files
1. `inc/classes/class-plugin-activator.php` - Added table creation method
2. `inc/classes/class-retry.php` - Implemented all retry logic
3. `inc/classes/class-enqueue-assets.php` - Updated nonce localization
4. `templates/menus/wasp-retry.php` - Added stats display and proper attributes
5. `assets/admin/js/admin-retry.js` - Implemented all AJAX handlers
6. `inventory-cloud-api-integration.php` - Added table creation to activation hook

### Files Already Existing
1. `assets/admin/css/admin-retry.css` - Styling (no changes needed)

## Activation

The retry table will be automatically created when you:
1. Deactivate and reactivate the plugin
2. Or manually run: `Plugin_Activator::create_sync_wasp_retry_items_table();`

## Testing

### Test Automatic Retry
1. Ensure you have some FAILED or IGNORED items in your tables
2. Enable retry toggle in admin
3. Set up a cron job to call `/order-retry` or `/sales-return-retry` endpoints
4. Check retry table for new records
5. Verify original items status changed to PENDING

### Test Manual Retry
1. Ensure you have some FAILED or IGNORED items
2. Click "Instant Retry" button
3. Observe notification message
4. Check stats update
5. Verify items status changed in database

### Test Statistics
1. View Retry Settings page
2. Stats should load automatically
3. Trigger a retry and watch stats update
4. Wait 30 seconds to see auto-refresh

## Security

- All AJAX calls require valid nonces
- Admin capabilities checked on backend
- SQL queries use prepared statements
- Input sanitization on all user inputs

## Performance

- Retry process handles 50 items per batch to avoid timeouts
- Stats queries use indexed columns
- Duplicate retry records prevented by checking existing entries
- Auto-refresh limited to 30-second intervals

## Future Enhancements

Potential improvements:
1. Add max retry count limit per item
2. Add retry scheduling options
3. Add email notifications for failed retries
4. Add detailed retry history/logs
5. Add bulk retry selection
6. Add retry priority queue

## Support

For issues or questions, check:
1. WordPress error logs
2. Browser console for JavaScript errors
3. Network tab for AJAX call failures
4. Database for table creation status

