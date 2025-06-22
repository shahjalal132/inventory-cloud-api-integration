(function ($) {
  $(document).ready(function () {
    // Element references
    const $fileInput = $("#wasp-inv-fileInput"); // Hidden native file input
    const $fileCustom = $(".wasp-inv-file-input-custom"); // Custom styled drop zone
    const $selectedFile = $("#wasp-inv-selectedFile"); // Area to display selected file name
    const $importBtn = $("#wasp-inv-importBtn"); // Submit/Import button
    const $form = $("#wasp-inv-importForm"); // The form element

    // ==============================
    // Handle File Selection (Browse)
    // ==============================
    $fileInput.on("change", function () {
      if (this.files.length > 0) {
        const file = this.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert size to MB

        // Show selected file info
        $selectedFile.html(`📁 ${fileName} (${fileSize} MB)`).show();

        // Update custom file box UI
        $fileCustom.find(".wasp-inv-file-text").text("File selected");
        $fileCustom.find(".wasp-inv-file-types").hide();
      }
    });

    // ==============================
    // Drag & Drop Functionality
    // ==============================

    // Highlight drop zone on drag over
    $fileCustom.on("dragover", function (e) {
      e.preventDefault();
      $(this).addClass("wasp-inv-dragover");
    });

    // Remove highlight when dragging leaves the drop zone
    $fileCustom.on("dragleave", function (e) {
      e.preventDefault();
      $(this).removeClass("wasp-inv-dragover");
    });

    // Handle file drop
    $fileCustom.on("drop", function (e) {
      e.preventDefault();
      $(this).removeClass("wasp-inv-dragover");

      const files = e.originalEvent.dataTransfer.files;
      if (files.length > 0) {
        const file = files[0];
        const allowedTypes = [".xls", ".xlsx"];
        const fileExtension = "." + file.name.split(".").pop().toLowerCase();

        // Validate file type
        if (allowedTypes.includes(fileExtension)) {
          // Assign dropped file to native file input using DataTransfer
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          $fileInput[0].files = dataTransfer.files;

          // Trigger 'change' to update UI
          $fileInput.trigger("change");
        } else {
          alert("Please select a valid file format (XLS, XLSX)");
        }
      }
    });

    // ==============================
    // Form Submission Handling
    // ==============================
    $form.on("submit", function (e) {
      e.preventDefault();

      const month = $("#wasp-inv-month").val();
      const year = $("#wasp-inv-year").val();
      const file = $fileInput[0].files[0];

      // Validate required fields
      if (!month || !year || !file) {
        alert("Please fill in all required fields");
        return;
      }

      // Simulate import process
      $importBtn.prop("disabled", true).text("Importing...");

      // Prepare FormData object to send file + other fields
      const formData = new FormData();
      formData.append("action", "import_sales_returns_data");
      formData.append("nonce", waspInvAjax.nonce);
      formData.append("month", month);
      formData.append("year", year);
      formData.append("file", file); // Attach the actual file

      // Send AJAX request with FormData
      $.ajax({
        url: waspInvAjax.ajax_url,
        type: "POST",
        data: formData,
        processData: false, // Important for file upload
        contentType: false, // Important for file upload
        success: function (response) {
          if (response.success) {
            alert(response.data.message);

            // Reset form and UI
            $form[0].reset();
            $selectedFile.hide();
            $fileCustom
              .find(".wasp-inv-file-text")
              .text("Click to select file or drag and drop");
            $fileCustom.find(".wasp-inv-file-types").show();
          } else {
            alert(response.data.message);
          }

          $importBtn.prop("disabled", false).text("Import Data");
        },
        error: function (response) {
          alert(response.responseJSON.data.message);
          $importBtn.prop("disabled", false).text("Import Data");
        },
      });
    });
  });
})(jQuery);
