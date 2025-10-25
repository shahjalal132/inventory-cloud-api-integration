<div class="retry-header">
    <h1>Retry IGNORED and FAILED Items</h1>
</div>

<div class="wasp-retry-grid wasp-my-50">

    <!-- retry for woo orders -->
    <div class="wasp-retry-card">
        <div class="wasp-retry-header">
            <h3 class="wasp-retry-title">Enable/Disable Orders automatic retry</h3>
            <div class="wasp-retry-status disabled">
                Disabled </div>
        </div>

        <div class="wasp-retry-details">
            <p class="wasp-retry-description"> Automatically retry FAILED and IGNORED items. </p>

        </div>

        <div class="wasp-retry-controls">
            <div class="wasp-retry-toggle">
                <label class="switch">
                    <input type="checkbox" class="cron-toggle" data-endpoint="prepare-sales-returns">
                    <span class="slider round"></span>
                </label>
                <span class="toggle-label">Enable</span>
            </div>

            <button type="button" class="button button-secondary run-now-btn" data-endpoint="prepare-sales-returns">
                Instant Retry
            </button>
        </div>
    </div>

    <!-- retry for sales returns -->
    <div class="wasp-retry-card">
        <div class="wasp-retry-header">
            <h3 class="wasp-retry-title">Enable/Disable Sales Return automatic retry</h3>
            <div class="wasp-retry-status disabled">
                Disabled </div>
        </div>

        <div class="wasp-retry-details">
            <p class="wasp-retry-description"> Automatically retry FAILED and IGNORED items. </p>

        </div>

        <div class="wasp-retry-controls">
            <div class="wasp-retry-toggle">
                <label class="switch">
                    <input type="checkbox" class="cron-toggle" data-endpoint="prepare-sales-returns">
                    <span class="slider round"></span>
                </label>
                <span class="toggle-label">Enable</span>
            </div>

            <button type="button" class="button button-secondary run-now-btn" data-endpoint="prepare-sales-returns">
                Instant Retry
            </button>
        </div>
    </div>
</div>