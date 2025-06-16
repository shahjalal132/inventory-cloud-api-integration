(function ($) {
  $(document).ready(function () {
    // update inventory cloud options
    $("#inv-cloud-save-btn").on("click", function () {
      let apiBaseUrl = $("#inv-cloud-base-url").val();
      let apiToken = $("#inv-cloud-token").val();
      let updateQuantity = $("#inv-cloud-update_quantity").val();
      let updateInventory = $("input[name='update-inventory']:checked").val();

      $.ajax({
        url: invCloudAjax.ajax_url,
        type: "POST",
        data: {
          action: "save_inventory_cloud_options",
          api_base_url: apiBaseUrl,
          api_token: apiToken,
          update_quantity: updateQuantity,
          update_inventory: updateInventory,
          nonce: invCloudAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            window.location.reload();
          } else {
            alert("There was an error saving the options.");
            window.location.reload();
          }
        },
        error: function () {
          alert("An error occurred while saving.");
          window.location.reload();
        },
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
  });
})(jQuery);
