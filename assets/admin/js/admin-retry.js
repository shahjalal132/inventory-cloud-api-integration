(function ($) {
  $(document).ready(function () {
    
    // Load stats on page load
    loadRetryStats();

    // Handle toggle switches for enable/disable
    $('.retry-toggle').on('change', function () {
      const $toggle = $(this);
      const type = $toggle.data('type');
      const enabled = $toggle.is(':checked');
      const $card = $toggle.closest('.wasp-retry-card');
      const $statusBadge = $card.find('.wasp-retry-status');

      // Determine the AJAX action
      const action = type === 'order' ? 'toggle_order_retry' : 'toggle_sales_return_retry';

      // Disable toggle while processing
      $toggle.prop('disabled', true);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: action,
          enabled: enabled,
          nonce: wpRetryData.nonce
        },
        success: function (response) {
          if (response.success) {
            // Update status badge
            if (enabled) {
              $statusBadge.removeClass('disabled').addClass('enabled').text('Enabled');
            } else {
              $statusBadge.removeClass('enabled').addClass('disabled').text('Disabled');
            }
            
            // Show success message
            showNotice('success', response.data.message);
          } else {
            // Revert toggle
            $toggle.prop('checked', !enabled);
            showNotice('error', response.data.message || 'Failed to update setting.');
          }
        },
        error: function (xhr, status, error) {
          // Revert toggle
          $toggle.prop('checked', !enabled);
          showNotice('error', 'An error occurred: ' + error);
        },
        complete: function () {
          $toggle.prop('disabled', false);
        }
      });
    });

    // Handle instant retry buttons
    $('.instant-retry-btn').on('click', function () {
      const $btn = $(this);
      const type = $btn.data('type');
      const $card = $btn.closest('.wasp-retry-card');

      // Determine the AJAX action
      const action = type === 'order' ? 'instant_order_retry' : 'instant_sales_return_retry';

      // Disable button and show loading state
      $btn.prop('disabled', true).text('Processing...');
      $card.addClass('loading');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: action,
          nonce: wpRetryData.nonce
        },
        success: function (response) {
          if (response.success) {
            const data = response.data;
            const message = data.message || 'Retry process completed.';
            showNotice('success', message);

            // Reload stats after retry
            setTimeout(function () {
              loadRetryStats();
            }, 1000);
          } else {
            showNotice('error', response.data.message || 'Retry process failed.');
          }
        },
        error: function (xhr, status, error) {
          showNotice('error', 'An error occurred: ' + error);
        },
        complete: function () {
          $btn.prop('disabled', false).text('Instant Retry');
          $card.removeClass('loading');
        }
      });
    });

    // Load retry statistics
    function loadRetryStats() {
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'get_retry_stats',
          nonce: wpRetryData.nonce
        },
        success: function (response) {
          if (response.success) {
            const data = response.data;

            // Update orders stats
            if (data.orders) {
              $('[data-stat="orders-ignored"]').text(data.orders.ignored);
              $('[data-stat="orders-failed"]').text(data.orders.failed);
              $('[data-stat="orders-total"]').text(data.orders.total_issues);
              $('[data-stat="orders-success"]').text(data.orders.retry_success);
            }

            // Update sales returns stats
            if (data.sales_returns) {
              $('[data-stat="sales-ignored"]').text(data.sales_returns.ignored);
              $('[data-stat="sales-failed"]').text(data.sales_returns.failed);
              $('[data-stat="sales-total"]').text(data.sales_returns.total_issues);
              $('[data-stat="sales-success"]').text(data.sales_returns.retry_success);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error('Failed to load retry stats:', error);
        }
      });
    }

    // Show notice message
    function showNotice(type, message) {
      // Remove existing notices
      $('.wasp-retry-notice').remove();

      // Create notice element
      const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
      const $notice = $('<div class="notice wasp-retry-notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

      // Insert after header
      $('.retry-header').after($notice);

      // Make it dismissible
      $notice.on('click', '.notice-dismiss', function () {
        $notice.fadeOut(300, function () {
          $(this).remove();
        });
      });

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        $notice.fadeOut(300, function () {
          $(this).remove();
        });
      }, 5000);

      // Scroll to notice
      $('html, body').animate({
        scrollTop: $notice.offset().top - 100
      }, 300);
    }

    // Refresh stats every 30 seconds
    setInterval(function () {
      loadRetryStats();
    }, 30000);

  });
})(jQuery);
