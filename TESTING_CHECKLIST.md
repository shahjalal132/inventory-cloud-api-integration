# Retry Functionality - Testing Checklist

## Pre-Testing Setup

### 1. Create the Retry Table
Choose one option:

**Option A: Using the Quick Script**
```
1. Navigate to: http://yoursite.com/wp-content/plugins/inventory-cloud-api-integration/create-retry-table.php
2. Verify success message
3. DELETE the create-retry-table.php file for security
```

**Option B: Deactivate/Reactivate Plugin**
```
1. Go to WordPress Admin → Plugins
2. Deactivate "Inventory Cloud API Integration"
3. Reactivate "Inventory Cloud API Integration"
```

**Option C: Via Database**
```sql
CREATE TABLE IF NOT EXISTS wp_sync_wasp_retry_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(50) NOT NULL COMMENT 'order or sales_return',
    item_number VARCHAR(255) NOT NULL,
    original_status VARCHAR(255) NOT NULL,
    retry_status VARCHAR(255) NOT NULL DEFAULT 'PENDING',
    retry_count INT DEFAULT 0,
    last_retry_at TIMESTAMP NULL,
    retry_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_item_type (item_type),
    INDEX idx_retry_status (retry_status),
    INDEX idx_original_id (original_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Verify Table Creation
```sql
SHOW TABLES LIKE '%retry_items%';
DESCRIBE wp_sync_wasp_retry_items;
```

### 3. Create Test Data (if needed)
```sql
-- Create some FAILED order items
INSERT INTO wp_sync_wasp_woo_orders_data 
(item_number, customer_number, cost, quantity, status, message) 
VALUES 
('TEST001', 'CUST001', 10.50, 2, 'FAILED', 'Test failed item'),
('TEST002', 'CUST002', 15.75, 1, 'IGNORED', 'Test ignored item');

-- Create some FAILED sales return items
INSERT INTO wp_sync_sales_returns_data 
(item_number, cost, quantity, type, status, message) 
VALUES 
('TEST003', 20.00, 3, 'RETURN', 'FAILED', 'Test failed return'),
('TEST004', 25.50, 1, 'SALE', 'IGNORED', 'Test ignored sale');
```

## Testing Checklist

### ✅ Visual Interface Tests

- [ ] Navigate to **Wasp Dashboard → Retry Settings**
- [ ] Page loads without errors
- [ ] Two cards displayed (Orders and Sales Returns)
- [ ] Each card has:
  - [ ] Title
  - [ ] Status badge (Enabled/Disabled)
  - [ ] Description
  - [ ] Toggle switch
  - [ ] "Instant Retry" button
  - [ ] Statistics section with 4 metrics

### ✅ Statistics Display Tests

- [ ] All stats show "0" initially (or correct counts)
- [ ] Stats section has proper styling
- [ ] Numbers are readable and formatted
- [ ] Labels are clear

### ✅ Toggle Switch Tests

#### Orders Toggle
- [ ] Click orders toggle to enable
  - [ ] Status badge changes to "Enabled" (green)
  - [ ] Success notification appears
  - [ ] Toggle remains enabled after page refresh
  
- [ ] Click orders toggle to disable
  - [ ] Status badge changes to "Disabled" (red)
  - [ ] Success notification appears
  - [ ] Toggle remains disabled after page refresh

#### Sales Returns Toggle
- [ ] Click sales returns toggle to enable
  - [ ] Status badge changes to "Enabled" (green)
  - [ ] Success notification appears
  - [ ] Toggle remains enabled after page refresh
  
- [ ] Click sales returns toggle to disable
  - [ ] Status badge changes to "Disabled" (red)
  - [ ] Success notification appears
  - [ ] Toggle remains disabled after page refresh

### ✅ Instant Retry Button Tests

#### Orders Instant Retry
- [ ] Click "Instant Retry" button on orders card
- [ ] Button text changes to "Processing..."
- [ ] Button is disabled during processing
- [ ] Card shows loading state (optional visual effect)
- [ ] Success/error notification appears
- [ ] Button returns to "Instant Retry" after completion
- [ ] Stats update automatically

#### Sales Returns Instant Retry
- [ ] Click "Instant Retry" button on sales returns card
- [ ] Button text changes to "Processing..."
- [ ] Button is disabled during processing
- [ ] Card shows loading state (optional visual effect)
- [ ] Success/error notification appears
- [ ] Button returns to "Instant Retry" after completion
- [ ] Stats update automatically

### ✅ Database Tests

After triggering retry, check database:

```sql
-- Check retry table has new records
SELECT * FROM wp_sync_wasp_retry_items ORDER BY created_at DESC LIMIT 10;

-- Verify item_type is correct
SELECT item_type, COUNT(*) as count 
FROM wp_sync_wasp_retry_items 
GROUP BY item_type;

-- Check retry counts
SELECT item_number, retry_count, retry_status 
FROM wp_sync_wasp_retry_items;

-- Verify original items status changed to PENDING
SELECT id, item_number, status 
FROM wp_sync_wasp_woo_orders_data 
WHERE item_number IN (SELECT item_number FROM wp_sync_wasp_retry_items WHERE item_type = 'order');
```

### ✅ REST API Tests

#### Test Order Retry Endpoint
```bash
# With retry enabled
curl -X GET "http://yoursite.com/wp-json/atebol/v1/order-retry"

# Expected response when enabled and items found:
{
  "message": "Added 5 order items to retry queue.",
  "added_count": 5,
  "total_found": 5,
  "errors": []
}

# Expected response when disabled:
{
  "message": "Order retry is disabled.",
  "enabled": false
}
```

#### Test Sales Return Retry Endpoint
```bash
curl -X GET "http://yoursite.com/wp-json/atebol/v1/sales-return-retry"
```

#### Test Stats Endpoint
```bash
curl -X GET "http://yoursite.com/wp-json/atebol/v1/retry-stats"

# Expected response:
{
  "orders": {
    "ignored": 2,
    "failed": 3,
    "total_issues": 5,
    "retried": 5,
    "retry_success": 0
  },
  "sales_returns": {
    "ignored": 1,
    "failed": 2,
    "total_issues": 3,
    "retried": 3,
    "retry_success": 0
  }
}
```

### ✅ JavaScript Console Tests

- [ ] Open browser DevTools (F12)
- [ ] Navigate to Retry Settings page
- [ ] Check Console tab for any errors
- [ ] Toggle switches and verify AJAX calls in Network tab
- [ ] Click instant retry and verify AJAX calls
- [ ] Verify stats are refreshed every 30 seconds

### ✅ WordPress Options Tests

Check options in database:

```sql
SELECT * FROM wp_options WHERE option_name LIKE '%retry%';

-- Should see:
-- wasp_order_retry_enable (value: 1 or 0)
-- wasp_sales_return_retry_enable (value: 1 or 0)
```

### ✅ Auto-Refresh Tests

- [ ] Stay on page for 30+ seconds
- [ ] Observe stats refresh automatically
- [ ] No errors in console
- [ ] Stats update without page reload

### ✅ Integration Tests

#### Full Order Retry Flow
1. [ ] Create order items with FAILED status
2. [ ] Enable order retry toggle
3. [ ] Click instant retry
4. [ ] Verify items added to retry table
5. [ ] Verify original items status changed to PENDING
6. [ ] Run prepare-woo-orders endpoint
7. [ ] Verify items processed
8. [ ] Check stats updated

#### Full Sales Return Retry Flow
1. [ ] Create sales return items with IGNORED status
2. [ ] Enable sales return retry toggle
3. [ ] Click instant retry
4. [ ] Verify items added to retry table
5. [ ] Verify original items status changed to PENDING
6. [ ] Run prepare-sales-returns endpoint
7. [ ] Verify items processed
8. [ ] Check stats updated

### ✅ Error Handling Tests

- [ ] Test with no FAILED/IGNORED items (should show "No items found")
- [ ] Test with retry disabled (toggle off, call endpoint)
- [ ] Test with invalid nonce (should fail gracefully)
- [ ] Test with non-admin user (should deny access)
- [ ] Test instant retry with network offline (should show error)

### ✅ Performance Tests

- [ ] Test with 100+ FAILED items
- [ ] Verify batch processing works (50 items at a time)
- [ ] Check page load time is acceptable
- [ ] Stats refresh doesn't slow down page

### ✅ Cross-Browser Tests

Test in:
- [ ] Chrome/Edge
- [ ] Firefox
- [ ] Safari (if available)

### ✅ Responsive Design Tests

- [ ] Test on mobile viewport (320px)
- [ ] Test on tablet viewport (768px)
- [ ] Test on desktop (1920px)
- [ ] Cards stack properly on small screens
- [ ] Buttons remain accessible

## Common Issues & Solutions

### Issue: Stats showing all zeros
**Solution**: Create some FAILED/IGNORED items in source tables

### Issue: Toggle not saving
**Solution**: 
- Check browser console for JS errors
- Verify nonce is being passed
- Check AJAX handler is registered

### Issue: Instant retry does nothing
**Solution**:
- Check if there are FAILED/IGNORED items
- Check browser console for errors
- Verify REST API endpoints are accessible

### Issue: Table not created
**Solution**: 
- Run create-retry-table.php script
- Or deactivate/reactivate plugin
- Check database permissions

### Issue: Items not changing to PENDING
**Solution**:
- Check retry logic in class-retry.php
- Verify database update queries are executing
- Check for SQL errors in WordPress debug log

## Success Criteria

✅ All functionality works as expected:
- Toggle switches save and restore state
- Instant retry processes items
- Stats display accurate counts
- Database records created correctly
- REST API endpoints respond properly
- No JavaScript errors
- No PHP errors
- Security nonces work
- Auto-refresh functions

## Post-Testing Cleanup

- [ ] Delete create-retry-table.php file
- [ ] Remove test data if needed
- [ ] Document any issues found
- [ ] Create tickets for future enhancements

## Need Help?

Check:
1. WordPress debug.log
2. Browser console
3. Network tab in DevTools
4. Database error logs
5. RETRY_FUNCTIONALITY.md documentation

