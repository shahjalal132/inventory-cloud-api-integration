<?php
// Get current enable/disable status
$order_retry_enabled = get_option( 'wasp_order_retry_enable', false );
$sales_return_retry_enabled = get_option( 'wasp_sales_return_retry_enable', false );
?>

<div class="retry-header">
    <h1>Retry IGNORED and FAILED Items</h1>
</div>

<div class="wasp-retry-grid wasp-my-50">

    <!-- retry for woo orders -->
    <div class="wasp-retry-card" data-type="order">
        <div class="wasp-retry-header">
            <h3 class="wasp-retry-title">Enable/Disable Orders automatic retry</h3>
            <div class="wasp-retry-status <?php echo $order_retry_enabled ? 'enabled' : 'disabled'; ?>">
                <?php echo $order_retry_enabled ? 'Enabled' : 'Disabled'; ?>
            </div>
        </div>

        <div class="wasp-retry-details">
            <p class="wasp-retry-description"> Automatically retry FAILED and IGNORED items. </p>
        </div>

        <div class="wasp-retry-controls">
            <div class="wasp-retry-toggle">
                <label class="switch">
                    <input type="checkbox" class="retry-toggle" data-type="order" <?php checked( $order_retry_enabled ); ?>>
                    <span class="slider round"></span>
                </label>
                <span class="toggle-label">Enable</span>
            </div>

            <button type="button" class="button button-secondary instant-retry-btn" data-type="order">
                Instant Retry
            </button>
        </div>

        <!-- Stats Section for Orders -->
        <div class="wasp-retry-stats" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #333;">Statistics</h4>
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Ignored Items:</span>
                    <strong class="stat-value" data-stat="orders-ignored">0</strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Failed Items:</span>
                    <strong class="stat-value" data-stat="orders-failed">0</strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Total Issues:</span>
                    <strong class="stat-value" data-stat="orders-total">0</strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Successfully Retried:</span>
                    <strong class="stat-value" data-stat="orders-success" style="color: #28a745;">0</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- retry for sales returns -->
    <div class="wasp-retry-card" data-type="sales_return">
        <div class="wasp-retry-header">
            <h3 class="wasp-retry-title">Enable/Disable Sales Return automatic retry</h3>
            <div class="wasp-retry-status <?php echo $sales_return_retry_enabled ? 'enabled' : 'disabled'; ?>">
                <?php echo $sales_return_retry_enabled ? 'Enabled' : 'Disabled'; ?>
            </div>
        </div>

        <div class="wasp-retry-details">
            <p class="wasp-retry-description"> Automatically retry FAILED and IGNORED items. </p>
        </div>

        <div class="wasp-retry-controls">
            <div class="wasp-retry-toggle">
                <label class="switch">
                    <input type="checkbox" class="retry-toggle" data-type="sales_return" <?php checked( $sales_return_retry_enabled ); ?>>
                    <span class="slider round"></span>
                </label>
                <span class="toggle-label">Enable</span>
            </div>

            <button type="button" class="button button-secondary instant-retry-btn" data-type="sales_return">
                Instant Retry
            </button>
        </div>

        <!-- Stats Section for Sales Returns -->
        <div class="wasp-retry-stats" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #333;">Statistics</h4>
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Ignored Items:</span>
                    <strong class="stat-value" data-stat="sales-ignored">0</strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Failed Items:</span>
                    <strong class="stat-value" data-stat="sales-failed">0</strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Total Issues:</span>
                    <strong class="stat-value" data-stat="sales-total">0</strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label" style="font-size: 12px; color: #666;">Successfully Retried:</span>
                    <strong class="stat-value" data-stat="sales-success" style="color: #28a745;">0</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Danger Zone - Truncate Tables -->
<div class="wasp-danger-zone" style="margin-top: 100px;">
    <h2 style="color: #dc3545; margin-bottom: 20px; border-bottom: 2px solid #dc3545; padding-bottom: 10px;">
        ⚠️ Danger Zone
    </h2>

    <div class="truncate-table-section" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        
        <!-- Truncate Orders Table -->
        <div class="truncate-card danger-card">
            <div class="truncate-card-header">
                <h3 class="truncate-card-title">🗑️ Truncate Orders Table</h3>
            </div>
            
            <div class="danger-warning">
                <span class="dashicons dashicons-warning" style="color: #dc3545; font-size: 20px;"></span>
                <div class="danger-info">
                    <strong>⚠️ WARNING: This action is IRREVERSIBLE!</strong>
                    <p>This will permanently delete ALL records from the <code>wp_sync_wasp_woo_orders_data</code> table.</p>
                    <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
                        <li>All order items will be removed</li>
                        <li>All statuses (PENDING, READY, FAILED, IGNORED, COMPLETED) will be lost</li>
                        <li>This operation cannot be undone</li>
                        <li>Consider backing up the database first</li>
                    </ul>
                </div>
            </div>

            <button type="button" class="button button-danger truncate-btn" data-table="orders">
                <span class="dashicons dashicons-trash"></span>
                Truncate Orders Table
            </button>
        </div>

        <!-- Truncate Sales Returns Table -->
        <div class="truncate-card danger-card">
            <div class="truncate-card-header">
                <h3 class="truncate-card-title">🗑️ Truncate Sales Returns Table</h3>
            </div>
            
            <div class="danger-warning">
                <span class="dashicons dashicons-warning" style="color: #dc3545; font-size: 20px;"></span>
                <div class="danger-info">
                    <strong>⚠️ WARNING: This action is IRREVERSIBLE!</strong>
                    <p>This will permanently delete ALL records from the <code>wp_sync_sales_returns_data</code> table.</p>
                    <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
                        <li>All sales return items will be removed</li>
                        <li>All statuses (PENDING, READY, FAILED, IGNORED, COMPLETED) will be lost</li>
                        <li>This operation cannot be undone</li>
                        <li>Consider backing up the database first</li>
                    </ul>
                </div>
            </div>

            <button type="button" class="button button-danger truncate-btn" data-table="sales_returns">
                <span class="dashicons dashicons-trash"></span>
                Truncate Sales Returns Table
            </button>
        </div>

    </div>
</div>