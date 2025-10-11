(function ($) {
  $(document).ready(function () {
    // Element references
    const $fileInput = $("#wasp-inv-fileInput"); // Hidden native file input
    const $fileCustom = $(".wasp-inv-file-input-custom"); // Custom styled drop zone
    const $selectedFile = $("#wasp-inv-selectedFile"); // Area to display selected file name
    const $importBtn = $("#wasp-inv-importBtn"); // Submit/Import button
    const $form = $("#wasp-inv-importForm"); // The form element

    // Progress bar elements
    const $progressContainer = $("#wasp-progress-container");
    const $progressFill = $("#wasp-progress-fill");
    const $progressPercentage = $("#wasp-progress-percentage");
    const $progressStatus = $("#wasp-progress-status");

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

      // Show progress bar and start simulation
      showProgressBar();
      startProgressSimulation();
      updateProgress(10, "Uploading file...");

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
        xhr: function () {
          const xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener(
            "progress",
            function (evt) {
              if (evt.lengthComputable) {
                const percentComplete =
                  Math.round((evt.loaded / evt.total) * 50) + 10; // 10-60%
                updateProgress(percentComplete, "Uploading file...");
              }
            },
            false
          );
          return xhr;
        },
        success: function (response) {
          updateProgress(100, "Import completed!");

          setTimeout(function () {
            hideProgressBar();

            if (response.success) {
              // Reset form and UI
              $form[0].reset();
              $selectedFile.hide();
              $fileCustom
                .find(".wasp-inv-file-text")
                .text("Click to select file or drag and drop");
              $fileCustom.find(".wasp-inv-file-types").show();

              // Refresh table data
              loadSalesReturnsData();
            } else {
              alert(response.data.message);
            }

            $importBtn.prop("disabled", false).text("Import Data");
          }, 1000);
        },
        error: function (response) {
          updateProgress(100, "Import failed!");

          setTimeout(function () {
            hideProgressBar();
            alert(response.responseJSON.data.message);
            $importBtn.prop("disabled", false).text("Import Data");
          }, 1000);
        },
      });
    });

    // Table data management
    let currentPage = 1;
    const perPage = 20;
    let currentSearch = "";
    let currentStatusFilter = "";
    let searchTimeout;

    // Progress bar management
    let progressInterval;
    let currentProgress = 0;

    // Progress bar functions
    function showProgressBar() {
      $progressContainer.show();
      $progressFill.css("width", "0%");
      $progressPercentage.text("0%");
      $progressStatus.text("Preparing import...");
      currentProgress = 0;
    }

    function hideProgressBar() {
      $progressContainer.hide();
      if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
      }
    }

    function updateProgress(percentage, status) {
      currentProgress = Math.min(100, Math.max(0, percentage));
      $progressFill.css("width", currentProgress + "%");
      $progressPercentage.text(Math.round(currentProgress) + "%");
      if (status) {
        $progressStatus.text(status);
      }
    }

    function simulateProgress() {
      if (currentProgress < 90) {
        currentProgress += Math.random() * 10;
        updateProgress(currentProgress);
      }
    }

    function startProgressSimulation() {
      if (progressInterval) {
        clearInterval(progressInterval);
      }
      progressInterval = setInterval(simulateProgress, 500);
    }

    // Load table data
    function loadSalesReturnsData() {
      const $tableBody = $(".wasp-data-table-tbody");
      const $pagination = $(".wasp-data-table-pagination");
      const $info = $(".wasp-data-table-info");

      // Show loading state
      $tableBody.html(
        '<tr><td colspan="12" style="text-align: center; padding: 20px;">Loading...</td></tr>'
      );

      $.ajax({
        url: waspInvAjax.ajax_url,
        type: "POST",
        data: {
          action: "fetch_sales_returns_data",
          nonce: waspInvAjax.nonce,
          page: currentPage,
          per_page: perPage,
          search: currentSearch,
          status_filter: currentStatusFilter,
        },
        success: function (response) {
          if (response.success) {
            renderTableData(response.data.data);
            renderPagination(response.data.pagination);
            renderTableInfo(response.data.pagination);
          } else {
            $tableBody.html(
              '<tr><td colspan="12" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>'
            );
          }
        },
        error: function () {
          $tableBody.html(
            '<tr><td colspan="12" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>'
          );
        },
      });
    }

    function formatDate(dateString) {
      if (!dateString) return "";
      const date = new Date(dateString);

      const day = String(date.getUTCDate()).padStart(2, "0");
      const month = String(date.getUTCMonth() + 1).padStart(2, "0"); // months are 0-based
      const year = date.getUTCFullYear();

      let hours = date.getUTCHours();
      const minutes = String(date.getUTCMinutes()).padStart(2, "0");
      const ampm = hours >= 12 ? "pm" : "am";
      hours = hours % 12 || 12; // convert 0 -> 12 for 12-hour clock

      return `${day}-${month}-${year} ${hours}:${minutes}${ampm}`;
    }

    // Render table data
    function renderTableData(data) {
      const $tableBody = $(".wasp-data-table-tbody");

      if (data.length === 0) {
        $tableBody.html(
          '<tr><td colspan="12" style="text-align: center; padding: 20px;">No data found</td></tr>'
        );
        return;
      }

      let html = "";
      data.forEach(function (row) {
        const statusClass = getStatusClass(row.status);
        const apiMessage = extractErrorMessage(row.api_response);
        const message = row.message || "";
        const errorMessage = apiMessage || message;
        const tooltipAttr = errorMessage
          ? `data-tooltip="${errorMessage}"`
          : "";

        html += `
          <tr class="wasp-data-table-tr" ${tooltipAttr}>
            <td class="wasp-data-table-td">${row.id}</td>
            <td class="wasp-data-table-td">${row.item_number || ""}</td>
            <td class="wasp-data-table-td">£${parseFloat(row.cost || 0).toFixed(
              2
            )}</td>
            <td class="wasp-data-table-td">${formatDate(row.date_acquired)}</td>
            <td class="wasp-data-table-td">${row.shop || ""}</td>
            <td class="wasp-data-table-td">${row.customer_number || ""}</td>
            <td class="wasp-data-table-td">${row.site_name || ""}</td>
            <td class="wasp-data-table-td">${row.location_code || ""}</td>
            <td class="wasp-data-table-td">${
              row.type === "RETURN"
                ? "-" + (row.quantity || "")
                : row.quantity || ""
            }</td>
            <td class="wasp-data-table-td">${row.type || ""}</td>
            <td class="wasp-data-table-td">
              <span title="${
                errorMessage || ""
              }" class="wasp-data-table-status ${statusClass}" ${tooltipAttr}>${
          row.status || ""
        }</span>
            </td>
            <td class="wasp-data-table-td">${errorMessage || ""}</td>
          </tr>
        `;
      });

      $tableBody.html(html);
    }

    // Extract error message from API response
    function extractErrorMessage(apiResponse) {
      if (!apiResponse) return "";

      try {
        const response = JSON.parse(apiResponse);

        // Check for error messages in the response
        if (response.Messages && Array.isArray(response.Messages)) {
          for (const message of response.Messages) {
            if (message.Message && message.HttpStatusCode !== 200) {
              return message.Message;
            }
          }
        }

        // Check Data.ResultList for errors
        if (
          response.Data &&
          response.Data.ResultList &&
          Array.isArray(response.Data.ResultList)
        ) {
          for (const result of response.Data.ResultList) {
            if (result.Message && result.HttpStatusCode !== 200) {
              return result.Message;
            }
          }
        }

        return "";
      } catch (e) {
        return "";
      }
    }

    // Get status CSS class
    function getStatusClass(status) {
      switch (status) {
        case "PENDING":
          return "wasp-data-table-status-pending";
        case "READY":
          return "wasp-data-table-status-ready";
        case "FAILED":
          return "wasp-data-table-status-failed";
        case "COMPLETED":
          return "wasp-data-table-status-completed";
        case "IGNORED":
          return "wasp-data-table-status-ignored";
        default:
          return "wasp-data-table-status-active";
      }
    }

    // Render pagination
    function renderPagination(pagination) {
      const $pagination = $(".wasp-data-table-pagination");
      let html = "";

      // Previous buttons
      html += `<a href="#" class="wasp-data-table-page-btn ${
        !pagination.has_prev ? "wasp-data-table-page-btn-disabled" : ""
      }" data-page="1">‹‹</a>`;
      html += `<a href="#" class="wasp-data-table-page-btn ${
        !pagination.has_prev ? "wasp-data-table-page-btn-disabled" : ""
      }" data-page="${pagination.current_page - 1}">‹</a>`;

      // Page numbers
      const startPage = Math.max(1, pagination.current_page - 2);
      const endPage = Math.min(
        pagination.total_pages,
        pagination.current_page + 2
      );

      if (startPage > 1) {
        html += `<a href="#" class="wasp-data-table-page-btn" data-page="1">1</a>`;
        if (startPage > 2) {
          html += `<span class="wasp-data-table-ellipsis">...</span>`;
        }
      }

      for (let i = startPage; i <= endPage; i++) {
        html += `<a href="#" class="wasp-data-table-page-btn ${
          i === pagination.current_page ? "wasp-data-table-page-btn-active" : ""
        }" data-page="${i}">${i}</a>`;
      }

      if (endPage < pagination.total_pages) {
        if (endPage < pagination.total_pages - 1) {
          html += `<span class="wasp-data-table-ellipsis">...</span>`;
        }
        html += `<a href="#" class="wasp-data-table-page-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
      }

      // Next buttons
      html += `<a href="#" class="wasp-data-table-page-btn ${
        !pagination.has_next ? "wasp-data-table-page-btn-disabled" : ""
      }" data-page="${pagination.current_page + 1}">›</a>`;
      html += `<a href="#" class="wasp-data-table-page-btn ${
        !pagination.has_next ? "wasp-data-table-page-btn-disabled" : ""
      }" data-page="${pagination.total_pages}">››</a>`;

      $pagination.html(html);
    }

    // Render table info
    function renderTableInfo(pagination) {
      const $info = $(".wasp-data-table-info");
      const start = (pagination.current_page - 1) * pagination.per_page + 1;
      const end = Math.min(
        pagination.current_page * pagination.per_page,
        pagination.total
      );
      $info.text(`Showing ${start} - ${end} of ${pagination.total}`);
    }

    // Search functionality with debouncer
    document
      .getElementById("searchInput")
      .addEventListener("input", function (e) {
        currentSearch = e.target.value;
        currentPage = 1; // Reset to first page

        // Add loading indicator
        e.target.classList.add("loading");

        // Clear existing timeout
        if (searchTimeout) {
          clearTimeout(searchTimeout);
        }

        // Set new timeout for debounced search
        searchTimeout = setTimeout(function () {
          loadSalesReturnsData();
          // Remove loading indicator after search completes
          e.target.classList.remove("loading");
        }, 500); // 500ms delay
      });

    // Filter functionality
    document
      .getElementById("statusFilter")
      .addEventListener("change", function (e) {
        currentStatusFilter = e.target.value;
        currentPage = 1; // Reset to first page
        loadSalesReturnsData();
      });

    // Pagination click handlers
    $(document).on("click", ".wasp-data-table-page-btn", function (e) {
      e.preventDefault();
      if (!$(this).hasClass("wasp-data-table-page-btn-disabled")) {
        const page = parseInt($(this).data("page"));
        if (page && page !== currentPage) {
          currentPage = page;
          loadSalesReturnsData();
        }
      }
    });

    // Export CSV button handler
    $("#wasp-sales-return-export-btn").on("click", function (e) {
      e.preventDefault();

      // Disable button during export
      const $btn = $(this);
      const originalText = $btn.text();
      $btn.prop("disabled", true).text("Exporting...");

      // Create a form dynamically to submit the export request
      const form = document.createElement("form");
      form.method = "POST";
      form.action = waspInvAjax.ajax_url;
      form.style.display = "none";

      // Add form fields
      const fields = {
        action: "export_sales_returns_csv",
        nonce: waspInvAjax.nonce,
        search: currentSearch,
        status_filter: currentStatusFilter,
      };

      for (const key in fields) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
      }

      document.body.appendChild(form);
      form.submit();
      document.body.removeChild(form);

      // Re-enable button after a short delay
      setTimeout(function () {
        $btn.prop("disabled", false).text(originalText);
      }, 1000);
    });

    // ==============================
    // Delete Modal Functionality
    // ==============================

    const $deleteModal = $("#wasp-delete-modal");
    const $deleteModalOverlay = $("#wasp-delete-modal-overlay");
    const $deleteModalClose = $("#wasp-delete-modal-close");
    const $deleteBtn = $("#wasp-sales-return-delete-btn");
    const $deleteModalTbody = $("#wasp-delete-modal-tbody");
    const $deleteModalInfo = $("#wasp-delete-modal-info");
    const $deleteCheckboxAll = $("#wasp-delete-checkbox-all");
    const $deleteSelectAllBtn = $("#wasp-delete-select-all");
    const $deleteAllBtn = $("#wasp-delete-all-btn");
    const $deleteSelectedBtn = $("#wasp-delete-selected-btn");

    let completedItems = [];

    // Open delete modal
    $deleteBtn.on("click", function () {
      openDeleteModal();
    });

    // Close modal handlers
    $deleteModalClose.on("click", closeDeleteModal);
    $deleteModalOverlay.on("click", closeDeleteModal);

    // Open modal and load completed items
    function openDeleteModal() {
      $deleteModal.fadeIn(300);
      $deleteModalInfo.text("Loading completed items...");
      $deleteModalTbody.html(
        '<tr><td colspan="13" style="text-align: center; padding: 20px;">Loading...</td></tr>'
      );
      loadCompletedItems();
    }

    // Close modal
    function closeDeleteModal() {
      $deleteModal.fadeOut(300);
      completedItems = [];
      $deleteCheckboxAll.prop("checked", false);
    }

    // Load completed items from server
    function loadCompletedItems() {
      $.ajax({
        url: waspInvAjax.ajax_url,
        type: "POST",
        data: {
          action: "fetch_completed_sales_returns",
          nonce: waspInvAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            completedItems = response.data.items;
            renderDeleteModalItems(completedItems);
            updateDeleteModalInfo();
          } else {
            $deleteModalTbody.html(
              '<tr><td colspan="13" style="text-align: center; padding: 20px; color: red;">Error loading items</td></tr>'
            );
            $deleteModalInfo.text("Error loading items");
          }
        },
        error: function () {
          $deleteModalTbody.html(
            '<tr><td colspan="13" style="text-align: center; padding: 20px; color: red;">Error loading items</td></tr>'
          );
          $deleteModalInfo.text("Error loading items");
        },
      });
    }

    // Render items in modal
    function renderDeleteModalItems(items) {
      if (items.length === 0) {
        $deleteModalTbody.html(
          '<tr><td colspan="13" style="text-align: center; padding: 20px;">No completed items found</td></tr>'
        );
        return;
      }

      let html = "";
      items.forEach(function (row) {
        const statusClass = getStatusClass(row.status);
        const apiMessage = extractErrorMessage(row.api_response);
        const message = row.message || "";
        const errorMessage = apiMessage || message;

        html += `
          <tr data-id="${row.id}">
            <td><input type="checkbox" class="delete-item-checkbox" value="${
              row.id
            }"></td>
            <td>${row.id}</td>
            <td>${row.item_number || ""}</td>
            <td>£${parseFloat(row.cost || 0).toFixed(2)}</td>
            <td>${formatDate(row.date_acquired)}</td>
            <td>${row.shop || ""}</td>
            <td>${row.customer_number || ""}</td>
            <td>${row.site_name || ""}</td>
            <td>${row.location_code || ""}</td>
            <td>${
              row.type === "RETURN"
                ? "-" + (row.quantity || "")
                : row.quantity || ""
            }</td>
            <td>${row.type || ""}</td>
            <td>
              <span class="wasp-data-table-status ${statusClass}">${
          row.status || ""
        }</span>
            </td>
            <td>${errorMessage || ""}</td>
          </tr>
        `;
      });

      $deleteModalTbody.html(html);
    }

    // Update modal info
    function updateDeleteModalInfo() {
      const total = completedItems.length;
      const selected = $(".delete-item-checkbox:checked").length;

      if (selected > 0) {
        $deleteModalInfo.text(
          `${selected} of ${total} items selected for deletion`
        );
      } else {
        $deleteModalInfo.text(`Total completed items: ${total}`);
      }
    }

    // Select all checkbox in header
    $deleteCheckboxAll.on("change", function () {
      const isChecked = $(this).prop("checked");
      $(".delete-item-checkbox").prop("checked", isChecked);
      $(".wasp-delete-modal-table tbody tr").toggleClass("selected", isChecked);
      updateDeleteModalInfo();
    });

    // Individual checkbox change
    $(document).on("change", ".delete-item-checkbox", function () {
      const $row = $(this).closest("tr");
      $row.toggleClass("selected", $(this).prop("checked"));

      // Update "select all" checkbox state
      const totalCheckboxes = $(".delete-item-checkbox").length;
      const checkedCheckboxes = $(".delete-item-checkbox:checked").length;
      $deleteCheckboxAll.prop(
        "checked",
        totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0
      );

      updateDeleteModalInfo();
    });

    // Select All button
    $deleteSelectAllBtn.on("click", function () {
      $deleteCheckboxAll.prop("checked", true).trigger("change");
    });

    // Delete All button
    $deleteAllBtn.on("click", function () {
      if (completedItems.length === 0) {
        alert("No items to delete");
        return;
      }

      if (
        !confirm(
          `Are you sure you want to delete ALL ${completedItems.length} completed items? This action cannot be undone.`
        )
      ) {
        return;
      }

      const allIds = completedItems.map((item) => item.id);
      deleteItems(allIds);
    });

    // Delete Selected button
    $deleteSelectedBtn.on("click", function () {
      const selectedIds = $(".delete-item-checkbox:checked")
        .map(function () {
          return $(this).val();
        })
        .get();

      if (selectedIds.length === 0) {
        alert("Please select at least one item to delete");
        return;
      }

      if (
        !confirm(
          `Are you sure you want to delete ${selectedIds.length} selected item(s)? This action cannot be undone.`
        )
      ) {
        return;
      }

      deleteItems(selectedIds);
    });

    // Delete items via AJAX
    function deleteItems(ids) {
      // Disable buttons
      $deleteAllBtn.prop("disabled", true).text("Deleting...");
      $deleteSelectedBtn.prop("disabled", true).text("Deleting...");
      $deleteSelectAllBtn.prop("disabled", true);

      $.ajax({
        url: waspInvAjax.ajax_url,
        type: "POST",
        data: {
          action: "delete_sales_returns_items",
          nonce: waspInvAjax.nonce,
          ids: ids,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            closeDeleteModal();
            loadSalesReturnsData(); // Refresh main table
          } else {
            alert("Error: " + response.data.message);
          }
        },
        error: function () {
          alert("Error deleting items. Please try again.");
        },
        complete: function () {
          // Re-enable buttons
          $deleteAllBtn.prop("disabled", false).text("Delete All");
          $deleteSelectedBtn.prop("disabled", false).text("Delete Selected");
          $deleteSelectAllBtn.prop("disabled", false);
        },
      });
    }

    // Load initial data
    loadSalesReturnsData();
  });
})(jQuery);
