(function ($) {
  $(document).ready(function () {
    // Element references
    const $fileInput = $("#wasp-order-fileInput");
    const $fileCustom = $(".wasp-inv-file-input-custom");
    const $selectedFile = $("#wasp-order-selectedFile");
    const $importBtn = $("#wasp-order-importBtn");
    const $form = $("#wasp-order-importForm");

    // Handle File Selection (Browse)
    $fileInput.on("change", function () {
      if (this.files.length > 0) {
        const file = this.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        $selectedFile.html(`📁 ${fileName} (${fileSize} MB)`).show();
        $fileCustom.find(".wasp-inv-file-text").text("File selected");
        $fileCustom.find(".wasp-inv-file-types").hide();
      }
    });

    // Drag & Drop Functionality
    $fileCustom.on("dragover", function (e) {
      e.preventDefault();
      $(this).addClass("wasp-inv-dragover");
    });
    $fileCustom.on("dragleave", function (e) {
      e.preventDefault();
      $(this).removeClass("wasp-inv-dragover");
    });
    $fileCustom.on("drop", function (e) {
      e.preventDefault();
      $(this).removeClass("wasp-inv-dragover");
      const files = e.originalEvent.dataTransfer.files;
      if (files.length > 0) {
        const file = files[0];
        const allowedTypes = [".csv"];
        const fileExtension = "." + file.name.split(".").pop().toLowerCase();
        if (allowedTypes.includes(fileExtension)) {
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          $fileInput[0].files = dataTransfer.files;
          $fileInput.trigger("change");
        } else {
          alert("Please select a valid CSV file");
        }
      }
    });

    // Form Submission Handling
    $form.on("submit", function (e) {
      e.preventDefault();
      const file = $fileInput[0].files[0];
      if (!file) {
        alert("Please select a CSV file to import");
        return;
      }
      $importBtn.prop("disabled", true).text("Importing...");
      const formData = new FormData();
      formData.append("action", "wasp_import_woocommerce_orders");
      formData.append("nonce", waspOrderImportAjax.nonce);
      formData.append("file", file);
      $.ajax({
        url: waspOrderImportAjax.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            $form[0].reset();
            $selectedFile.hide();
            $fileCustom.find(".wasp-inv-file-text").text("Click to select file or drag and drop");
            $fileCustom.find(".wasp-inv-file-types").show();
          } else {
            alert(response.data.message);
          }
          $importBtn.prop("disabled", false).text("Import Orders");
        },
        error: function (response) {
          alert(response.responseJSON.data.message);
          $importBtn.prop("disabled", false).text("Import Orders");
        },
      });
    });
  });
})(jQuery);
