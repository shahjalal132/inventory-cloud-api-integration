<?php
// Get cron jobs status
$jobs = BOILERPLATE\Inc\Jobs::get_instance();
$cron_jobs_status = $jobs->get_all_cron_jobs_status();

// Helper function to get endpoint descriptions
function get_endpoint_description( $endpoint ) {
    $descriptions = [
        'prepare-sales-returns' => 'Prepares sales returns data by fetching item details and updating site/location information.',
        'prepare-woo-orders' => 'Prepares WooCommerce orders data by fetching item details and updating site/location information.',
        'import-sales-returns' => 'Imports prepared sales returns data to the WASP inventory system.',
        'import-woo-orders' => 'Imports prepared WooCommerce orders data to the WASP inventory system.',
        'remove-completed-woo-orders' => 'Removes completed WooCommerce orders from the previous month.',
        'remove-completed-sales-returns' => 'Removes completed sales returns from the previous month.'
    ];
    
    return $descriptions[$endpoint] ?? 'No description available.';
}
?>

<div class="wasp-inv-container">
    <h1 class="wasp-inv-container-title">WASP Cron Jobs Controller</h1>
    
    <div class="wasp-cron-jobs-grid">
        <?php foreach ( $cron_jobs_status as $endpoint => $status ): ?>
            <div class="wasp-cron-job-card">
                <div class="wasp-cron-job-header">
                    <h3 class="wasp-cron-job-title"><?= esc_html( ucwords( str_replace( '-', ' ', $endpoint ) ) ) ?></h3>
                    <div class="wasp-cron-job-status <?= $status['enabled'] ? 'enabled' : 'disabled' ?>">
                        <?= $status['enabled'] ? 'Enabled' : 'Disabled' ?>
                    </div>
                </div>
                
                <div class="wasp-cron-job-details">
                    <p class="wasp-cron-job-description">
                        <?= esc_html( get_endpoint_description( $endpoint ) ) ?>
                    </p>
                    
                    <?php if ( $status['next_run'] ): ?>
                        <p class="wasp-cron-job-next-run">
                            <strong>Next Run:</strong> <?= esc_html( $status['next_run'] ) ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="wasp-cron-job-controls">
                    <div class="wasp-cron-job-toggle">
                        <label class="switch">
                            <input type="checkbox" 
                                   class="cron-toggle" 
                                   data-endpoint="<?= esc_attr( $endpoint ) ?>"
                                   <?= $status['enabled'] ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                        <span class="toggle-label"><?= $status['enabled'] ? 'Disable' : 'Enable' ?></span>
                    </div>
                    
                    <button type="button" 
                            class="button button-secondary run-now-btn" 
                            data-endpoint="<?= esc_attr( $endpoint ) ?>">
                        Run Now
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="wasp-cron-jobs-info">
        <h3>Cron Jobs Information</h3>
        <ul>
            <li><strong>Frequency:</strong> Every minute (60 seconds)</li>
            <li><strong>Status:</strong> Jobs will only run when enabled</li>
            <li><strong>Manual Execution:</strong> Use "Run Now" button to execute jobs immediately</li>
            <li><strong>Logs:</strong> Check program logs for execution details</li>
        </ul>
    </div>
</div> 