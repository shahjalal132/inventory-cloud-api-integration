jQuery(document).ready(function($) {
    
    // Toggle cron job enable/disable
    $('.cron-toggle').on('change', function() {
        const endpoint = $(this).data('endpoint');
        const enabled = $(this).is(':checked') ? 'enabled' : 'disabled';
        const card = $(this).closest('.wasp-cron-job-card');
        
        // Add loading state
        card.addClass('loading');
        
        $.ajax({
            url: waspCronAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_cron_job',
                endpoint: endpoint,
                enabled: enabled,
                nonce: waspCronAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    updateCronJobStatus(card, enabled);
                    showNotification('Cron job updated successfully!', 'success');
                } else {
                    showNotification(response.data.message || 'Failed to update cron job', 'error');
                    // Revert toggle
                    $(this).prop('checked', !$(this).is(':checked'));
                }
            },
            error: function() {
                showNotification('Network error occurred', 'error');
                // Revert toggle
                $(this).prop('checked', !$(this).is(':checked'));
            },
            complete: function() {
                card.removeClass('loading');
            }
        });
    });
    
    // Run cron job manually
    $('.run-now-btn').on('click', function() {
        const endpoint = $(this).data('endpoint');
        const card = $(this).closest('.wasp-cron-job-card');
        const button = $(this);
        
        // Disable button and add loading state
        button.prop('disabled', true);
        card.addClass('loading');
        
        $.ajax({
            url: waspCronAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'run_cron_job_manually',
                endpoint: endpoint,
                nonce: waspCronAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Cron job executed successfully!', 'success');
                } else {
                    showNotification(response.data.message || 'Failed to execute cron job', 'error');
                }
            },
            error: function() {
                showNotification('Network error occurred', 'error');
            },
            complete: function() {
                button.prop('disabled', false);
                card.removeClass('loading');
            }
        });
    });
    
    // Update cron job status in UI
    function updateCronJobStatus(card, enabled) {
        const statusElement = card.find('.wasp-cron-job-status');
        const toggleLabel = card.find('.toggle-label');
        
        if (enabled === 'enabled') {
            statusElement.removeClass('disabled').addClass('enabled').text('Enabled');
            toggleLabel.text('Disable');
        } else {
            statusElement.removeClass('enabled').addClass('disabled').text('Disabled');
            toggleLabel.text('Enable');
        }
    }
    
    // Show notification
    function showNotification(message, type) {
        // Remove existing notifications
        $('.wasp-notification').remove();
        
        const notification = $('<div class="wasp-notification wasp-notification-' + type + '">' + message + '</div>');
        
        // Add notification styles if not already present
        if ($('#wasp-notification-styles').length === 0) {
            $('head').append(`
                <style id="wasp-notification-styles">
                    .wasp-notification {
                        position: fixed;
                        top: 32px;
                        right: 20px;
                        padding: 12px 20px;
                        border-radius: 4px;
                        color: white;
                        font-weight: 500;
                        z-index: 999999;
                        max-width: 300px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        animation: slideInRight 0.3s ease;
                    }
                    .wasp-notification-success {
                        background: #28a745;
                    }
                    .wasp-notification-error {
                        background: #dc3545;
                    }
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                </style>
            `);
        }
        
        $('body').append(notification);
        
        // Auto remove after 3 seconds
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Add hover effect for better UX
    $('.wasp-cron-job-card').hover(
        function() {
            $(this).find('.run-now-btn').addClass('hover');
        },
        function() {
            $(this).find('.run-now-btn').removeClass('hover');
        }
    );
    
    // Add keyboard support for accessibility
    $('.cron-toggle').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('change');
        }
    });
    
    $('.run-now-btn').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
    
    // Test cron jobs setup button
    $('#test-cron-jobs-btn').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: waspCronAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'test_cron_jobs',
                nonce: waspCronAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Cron jobs test completed! Check program logs for details.', 'success');
                } else {
                    showNotification(response.data.message || 'Test failed', 'error');
                }
            },
            error: function() {
                showNotification('Network error occurred during test', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Production mode toggle
    $('#production-mode-toggle').on('change', function() {
        const enabled = $(this).is(':checked') ? 'enabled' : 'disabled';
        const toggleLabel = $('.wasp-production-toggle .toggle-label');
        
        $.ajax({
            url: waspCronAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_production_mode',
                enabled: enabled,
                nonce: waspCronAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    toggleLabel.text('Production Mode: ' + (enabled === 'enabled' ? 'Enabled' : 'Disabled'));
                    showNotification(response.data.message, 'success');

                    // Show or hide the development notice
                    if (enabled === 'enabled') {
                        $('.wasp-dev-notice').slideUp();
                    } else {
                        // Only show the notice if it exists on the page
                        if ($('.wasp-dev-notice').length) {
                            $('.wasp-dev-notice').slideDown();
                        }
                    }
                } else {
                    showNotification(response.data.message || 'Failed to update production mode', 'error');
                    // Revert toggle on failure
                    $('#production-mode-toggle').prop('checked', !$('#production-mode-toggle').is(':checked'));
                }
            },
            error: function() {
                showNotification('Network error occurred', 'error');
                // Revert toggle on error
                $('#production-mode-toggle').prop('checked', !$('#production-mode-toggle').is(':checked'));
            }
        });
    });
    
}); 