<?php
// Get cron jobs status
$jobs = BOILERPLATE\Inc\Jobs::get_instance();
$cron_jobs_status = $jobs->get_all_cron_jobs_status();
$dev_info = $jobs->get_development_info();
$production_mode = get_option( 'wasp_cron_production_mode', 'disabled' );

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
    
    <!-- Production Mode Toggle -->
    <div class="wasp-production-mode">
        <h3>Production Mode</h3>
        <div class="wasp-production-toggle">
            <label class="switch">
                <input type="checkbox" 
                       id="production-mode-toggle" 
                       <?= $production_mode === 'enabled' ? 'checked' : '' ?>>
                <span class="slider round"></span>
            </label>
            <span class="toggle-label">
                <?= $production_mode === 'enabled' ? 'Production Mode: Enabled' : 'Production Mode: Disabled' ?>
            </span>
        </div>
        <p class="wasp-production-description">
            <strong>Disabled:</strong> Cron jobs will be skipped in development environments<br>
            <strong>Enabled:</strong> Cron jobs will run regardless of environment
        </p>
    </div>
    
    <?php if ( $dev_info['is_development'] ): ?>
        <div class="wasp-dev-notice" <?php if ( $production_mode === 'enabled' ) echo 'style="display: none;"'; ?>>
            <h3>🛠️ Development Environment Detected</h3>
            <p><strong>Site URL:</strong> <?= esc_html( $dev_info['site_url'] ) ?></p>
            <?php if ( $dev_info['wp_cron_disabled'] ): ?>
                <p><strong>⚠️ WP Cron is disabled</strong> - Automatic execution may not work</p>
            <?php endif; ?>
            
            <h4>For Local Development:</h4>
            <ul>
                <?php foreach ( $dev_info['recommendations'] as $recommendation ): ?>
                    <li><?= esc_html( $recommendation ) ?></li>
                <?php endforeach; ?>
            </ul>
            
            <div class="wasp-dev-actions">
                <button type="button" class="button button-secondary" id="test-cron-jobs-btn">
                    Test Cron Jobs Setup
                </button>
                <a href="<?= esc_url( $dev_info['test_endpoint'] ) ?>" target="_blank" class="button button-secondary">
                    Test Endpoint
                </a>
            </div>
        </div>
    <?php endif; ?>
    
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
            <?php if ( $dev_info['is_development'] ): ?>
                <li><strong>Development:</strong> Automatic execution may require external traffic or real cron setup</li>
            <?php endif; ?>
        </ul>
    </div>
</div> 