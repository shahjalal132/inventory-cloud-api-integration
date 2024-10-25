(function ($) {
  $(document).ready(function () {
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
          } else {
            alert("There was an error saving the options.");
          }
        },
        error: function () {
          alert("An error occurred while saving.");
        },
      });
    });
  });
})(jQuery);
