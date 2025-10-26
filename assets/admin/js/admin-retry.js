(function ($) {
  $(document).ready(function () {
    // Load stats on page load
    loadRetryStats();

    // Handle toggle switches for enable/disable
    $(".retry-toggle").on("change", function () {
      const $toggle = $(this);
      const type = $toggle.data("type");
      const enabled = $toggle.is(":checked");
      const $card = $toggle.closest(".wasp-retry-card");
      const $statusBadge = $card.find(".wasp-retry-status");

      // Determine the AJAX action
      const action =
        type === "order" ? "toggle_order_retry" : "toggle_sales_return_retry";

      // Disable toggle while processing
      $toggle.prop("disabled", true);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: action,
          enabled: enabled,
          nonce: wpRetryData.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Update status badge
            if (enabled) {
              $statusBadge
                .removeClass("disabled")
                .addClass("enabled")
                .text("Enabled");
            } else {
              $statusBadge
                .removeClass("enabled")
                .addClass("disabled")
                .text("Disabled");
            }

            // Show success message
            showNotice("success", response.data.message);
          } else {
            // Revert toggle
            $toggle.prop("checked", !enabled);
            showNotice(
              "error",
              response.data.message || "Failed to update setting."
            );
          }
        },
        error: function (xhr, status, error) {
          // Revert toggle
          $toggle.prop("checked", !enabled);
          showNotice("error", "An error occurred: " + error);
        },
        complete: function () {
          $toggle.prop("disabled", false);
        },
      });
    });

    // Handle instant retries
    $(".instant-retry-btn").on("click", function () {
      const $btn = $(this);
      const type = $btn.data("type");
      const $card = $btn.closest(".wasp-retry-card");
      const $progressBar = $card.find(".progress-bar");

      // Determine the AJAX action
      const action =
        type === "order" ? "instant_order_retry" : "instant_sales_return_retry";

      // Reset progress bar
      setProgress($progressBar, 0);

      // Disable button and show loading state
      $btn.prop("disabled", true).text("Processing...");
      $card.addClass("loading");

      // Animate fake progress while waiting for AJAX
      let progress = 0;
      const interval = setInterval(() => {
        if (progress < 90) {
          // cap at 90% until complete
          progress += Math.random() * 5;
          setProgress($progressBar, progress);
        }
      }, 500);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: action,
          nonce: wpRetryData.nonce,
        },
        success: function (response) {
          clearInterval(interval);
          if (response.success) {
            setProgress($progressBar, 100);
            const data = response.data;
            const message = data.message || "Retry process completed.";
            showNotice("success", message);

            // Reload stats after retry
            setTimeout(() => {
              loadRetryStats();
              resetProgress($progressBar);
            }, 1000);
          } else {
            showNotice(
              "error",
              response.data.message || "Retry process failed."
            );
            resetProgress($progressBar);
          }
        },
        error: function (xhr, status, error) {
          clearInterval(interval);
          showNotice("error", "An error occurred: " + error);
          resetProgress($progressBar);
        },
        complete: function () {
          clearInterval(interval);
          $btn.prop("disabled", false).text("Instant Retry");
          $card.removeClass("loading");
        },
      });
    });

    // Helper: set progress % dynamically
    function setProgress($bar, percent) {
      $bar.find("::after"); // pseudo-element, so we simulate width
      $bar.css("--progress", percent + "%");
      $bar[0].style.setProperty("--width", percent + "%");
      $bar.find("::after");
      $bar[0].style.setProperty("--width", percent + "%");
      $bar[0].style.setProperty("--progress-width", percent + "%");
      $bar[0].style.setProperty(
        "background",
        `linear-gradient(to right, #218838 ${percent}%, #e0e0e0 ${percent}%)`
      );
    }

    // Helper: reset progress bar
    function resetProgress($bar) {
      setTimeout(() => setProgress($bar, 0), 1500);
    }

    // Load retry statistics
    function loadRetryStats() {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "get_retry_stats",
          nonce: wpRetryData.nonce,
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
              $('[data-stat="sales-total"]').text(
                data.sales_returns.total_issues
              );
              $('[data-stat="sales-success"]').text(
                data.sales_returns.retry_success
              );
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("Failed to load retry stats:", error);
        },
      });
    }

    // Show notice message
    function showNotice(type, message) {
      // Remove existing notices
      $(".wasp-retry-notice").remove();

      // Create notice element
      const noticeClass =
        type === "success" ? "notice-success" : "notice-error";
      const $notice = $(
        '<div class="notice wasp-retry-notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          message +
          "</p></div>"
      );

      // Insert after header
      $(".retry-header").after($notice);

      // Make it dismissible
      $notice.on("click", ".notice-dismiss", function () {
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
      $("html, body").animate(
        {
          scrollTop: $notice.offset().top - 100,
        },
        300
      );
    }

    // Refresh stats every 30 seconds
    setInterval(function () {
      loadRetryStats();
    }, 30000);

    // Handle truncate table buttons
    $(".truncate-btn").on("click", function () {
      const $btn = $(this);
      const table = $btn.data("table");
      const $card = $btn.closest(".truncate-card");

      // Determine table name for display
      const tableName = table === "orders" ? "Orders" : "Sales Returns";
      const tableFullName =
        table === "orders"
          ? "wp_sync_wasp_woo_orders_data"
          : "wp_sync_sales_returns_data";

      // First confirmation
      const firstConfirm = confirm(
        "⚠️ DANGER: You are about to PERMANENTLY DELETE all records!\n\n" +
          "Table: " +
          tableFullName +
          "\n\n" +
          "This will remove:\n" +
          "• All " +
          tableName.toLowerCase() +
          " records\n" +
          "• All statuses (PENDING, READY, FAILED, IGNORED, COMPLETED)\n" +
          "• All API responses and messages\n\n" +
          "❌ THIS ACTION CANNOT BE UNDONE! ❌\n\n" +
          "Are you absolutely sure you want to continue?"
      );

      if (!firstConfirm) {
        return; // User cancelled
      }

      // Second confirmation with typing requirement
      const confirmText = "DELETE ALL " + tableName.toUpperCase();
      const userInput = prompt(
        "⚠️ FINAL CONFIRMATION REQUIRED ⚠️\n\n" +
          "To confirm deletion, please type exactly:\n\n" +
          confirmText +
          "\n\n" +
          "This will permanently delete all records from the " +
          tableName.toLowerCase() +
          " table."
      );

      if (userInput !== confirmText) {
        if (userInput !== null) {
          // User didn't cancel but typed wrong text
          alert(
            "❌ Confirmation text did not match. Operation cancelled for your safety."
          );
        }
        return; // User cancelled or typed wrong text
      }

      // User confirmed twice, proceed with truncation
      $btn.prop("disabled", true).addClass("loading");
      $btn.html(
        '<span class="dashicons dashicons-update-alt"></span> Truncating...'
      );
      $card.addClass("loading");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "truncate_table",
          table: table,
          nonce: wpRetryData.nonce,
        },
        success: function (response) {
          if (response.success) {
            showNotice("success", response.data.message);

            // Reload stats after truncation
            setTimeout(function () {
              loadRetryStats();
            }, 1000);
          } else {
            showNotice(
              "error",
              response.data.message || "Failed to truncate table."
            );
          }
        },
        error: function (xhr, status, error) {
          showNotice("error", "An error occurred: " + error);
        },
        complete: function () {
          $btn.prop("disabled", false).removeClass("loading");
          $btn.html(
            '<span class="dashicons dashicons-trash"></span> Truncate ' +
              tableName +
              " Table"
          );
          $card.removeClass("loading");
        },
      });
    });
  });
})(jQuery);
