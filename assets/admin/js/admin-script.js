(function ($) {
  $(document).ready(function () {
    // update inventory cloud options
    $('#inv-cloud-save-btn').on('click', function () {
      let apiBaseUrl = $("#inv-cloud-base-url").val();
      let apiToken = $("#inv-cloud-token").val();
      let updateQuantity = $("#inv-cloud-update_quantity").val();
      let updateInventory = $("input[name='update-inventory']:checked").val();
      let apiUsername = $("#inv-cloud-api-username").val();
      let apiPassword = $("#inv-cloud-api-password").val();

      // Use the new toast notification for feedback
      const $button = $(this);
      $button.prop('disabled', true).text('Saving...');

      $.ajax({
        url: invCloudAjax.ajax_url,
        type: "POST",
        data: {
          action: "save_inventory_cloud_options",
          api_base_url: apiBaseUrl,
          api_token: apiToken,
          update_quantity: updateQuantity,
          update_inventory: updateInventory,
          api_username: apiUsername,
          api_password: apiPassword,
          nonce: invCloudAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            showToast('Settings saved successfully!', 'success');
          } else {
            showToast('Error: ' + response.data.message, 'error');
          }
        },
        error: function () {
          showToast('An unknown error occurred while saving.', 'error');
        },
        complete: function() {
          $button.prop('disabled', false).text('Save Changes');
        }
      });
    });

    // instant update inventory handler
    $("#instant-update-inventory").on("click", function (e) {
      e.preventDefault();

      const loaderWrapper = $(".loader-wrapper");
      loaderWrapper.addClass("inv-loader");

      $.ajax({
        url: invCloudAjax.ajax_url,
        type: "POST",
        data: {
          action: "instant_update_inventory",
          nonce: invCloudAjax.nonce,
        },
        success: function (response) {
          loaderWrapper.removeClass("inv-loader");
          if (response.success) {
            alert(response.data.message);
            window.location.reload();
          } else {
            alert(response.data.message);
            window.location.reload();
          }
        },
        error: function () {
          loaderWrapper.removeClass("inv-loader");
          alert("An error occurred while fetching inventory data.");
          window.location.reload();
        },
      });
    });

    function instantUpdateInventory() {
      // ... existing code ...
    }

    // Run API Endpoint
    $('.run-endpoint-btn').on('click', function (e) {
      e.preventDefault();
      const $button = $(this);
      const url = $button.data('url');
      const $responseWrapper = $('#wasp-api-response-wrapper');
      const $responsePre = $('#wasp-api-response');

      // Add a loading spinner to the icon
      $button.find('.dashicons').addClass('dashicons-update-alt').css('animation', 'spin 1s linear infinite');
      $button.prop('disabled', true);

      $.ajax({
        url: invCloudAjax.ajax_url,
        type: 'POST',
        data: {
          action: 'run_wasp_endpoint',
          nonce: invCloudAjax.nonce,
          url: url,
        },
        success: function (response) {
          if (response.success) {
            const responseData = response.data;
            let formattedResponse = '';

            if (responseData.is_json) {
              formattedResponse = JSON.stringify(responseData.data, null, 2);
            } else {
              formattedResponse = responseData.data; // Treat as plain text
            }
            
            $responsePre.text(formattedResponse);
            $responseWrapper.slideDown();
            showToast('Endpoint executed successfully.', 'success');
          } else {
            $responsePre.text('Error: ' + response.data.message);
            $responseWrapper.slideDown();
            showToast('Error: ' + response.data.message, 'error');
          }
        },
        error: function (xhr) {
          const errorMsg = xhr.responseJSON ? xhr.responseJSON.data.message : 'An unknown error occurred.';
          $responsePre.text('AJAX Error: ' + errorMsg);
          $responseWrapper.slideDown();
          showToast('AJAX Error: ' + errorMsg, 'error');
        },
        complete: function () {
          // Restore the original icon
          $button.find('.dashicons').removeClass('dashicons-update-alt').css('animation', '');
          $button.prop('disabled', false);
        }
      });
    });

    // Copy API Endpoint URL
    $('.copy-endpoint-btn').on('click', function (e) {
      e.preventDefault();
      const url = $(this).data('url');
      navigator.clipboard.writeText(url).then(function () {
        showToast('Endpoint URL copied!', 'success');
      }, function (err) {
        showToast('Failed to copy URL.', 'error');
      });
    });

    // Clear API Response
    $('#clear-response-btn').on('click', function () {
      $('#wasp-api-response').text('');
      $('#wasp-api-response-wrapper').slideUp();
    });

  });

  // --- Helper Functions ---

  // Toast Notification (globally accessible)
  function showToast(message, type = 'success') {
    // Remove any existing toasts
    $('.wasp-toast').remove();

    const $toast = $('<div class="wasp-toast"></div>').text(message);
    $toast.addClass(type);
    $('body').append($toast);

    // Trigger the animation
    setTimeout(function () {
      $toast.addClass('show');
    }, 10); // A small delay to allow the element to be appended before animating

    // Hide and remove the toast
    setTimeout(function () {
      $toast.removeClass('show');
      $toast.on('transitionend', function() {
        $toast.remove();
      });
    }, 3000);
  }

})(jQuery);
