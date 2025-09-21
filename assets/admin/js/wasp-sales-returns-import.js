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
        xhr: function() {
          const xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener("progress", function(evt) {
            if (evt.lengthComputable) {
              const percentComplete = Math.round((evt.loaded / evt.total) * 50) + 10; // 10-60%
              updateProgress(percentComplete, "Uploading file...");
            }
          }, false);
          return xhr;
        },
        success: function (response) {
          updateProgress(100, "Import completed!");
          
          setTimeout(function() {
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
          
          setTimeout(function() {
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
    let currentSearch = '';
    let currentStatusFilter = '';
    let searchTimeout;
    
    // Progress bar management
    let progressInterval;
    let currentProgress = 0;

    // Progress bar functions
    function showProgressBar() {
      $progressContainer.show();
      $progressFill.css('width', '0%');
      $progressPercentage.text('0%');
      $progressStatus.text('Preparing import...');
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
      $progressFill.css('width', currentProgress + '%');
      $progressPercentage.text(Math.round(currentProgress) + '%');
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
      const $tableBody = $('.wasp-data-table-tbody');
      const $pagination = $('.wasp-data-table-pagination');
      const $info = $('.wasp-data-table-info');
      
      // Show loading state
      $tableBody.html('<tr><td colspan="10" style="text-align: center; padding: 20px;">Loading...</td></tr>');

      $.ajax({
        url: waspInvAjax.ajax_url,
        type: 'POST',
        data: {
          action: 'fetch_sales_returns_data',
          nonce: waspInvAjax.nonce,
          page: currentPage,
          per_page: perPage,
          search: currentSearch,
          status_filter: currentStatusFilter
        },
        success: function(response) {
          if (response.success) {
            renderTableData(response.data.data);
            renderPagination(response.data.pagination);
            renderTableInfo(response.data.pagination);
          } else {
            $tableBody.html('<tr><td colspan="10" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>');
          }
        },
        error: function() {
          $tableBody.html('<tr><td colspan="10" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>');
        }
      });
    }

    // Render table data
    function renderTableData(data) {
      const $tableBody = $('.wasp-data-table-tbody');
      
      if (data.length === 0) {
        $tableBody.html('<tr><td colspan="10" style="text-align: center; padding: 20px;">No data found</td></tr>');
        return;
      }

      let html = '';
      data.forEach(function(row) {
        const statusClass = getStatusClass(row.status);
        html += `
          <tr class="wasp-data-table-tr">
            <td class="wasp-data-table-td">${row.id}</td>
            <td class="wasp-data-table-td">${row.item_number || ''}</td>
            <td class="wasp-data-table-td">$${parseFloat(row.cost || 0).toFixed(2)}</td>
            <td class="wasp-data-table-td">${row.date_acquired || ''}</td>
            <td class="wasp-data-table-td">${row.customer_number || ''}</td>
            <td class="wasp-data-table-td">${row.site_name || ''}</td>
            <td class="wasp-data-table-td">${row.location_code || ''}</td>
            <td class="wasp-data-table-td">${row.quantity || ''}</td>
            <td class="wasp-data-table-td">${row.type || ''}</td>
            <td class="wasp-data-table-td">
              <span class="wasp-data-table-status ${statusClass}">${row.status || ''}</span>
            </td>
          </tr>
        `;
      });
      
      $tableBody.html(html);
    }

    // Get status CSS class
    function getStatusClass(status) {
      switch(status) {
        case 'PENDING': return 'wasp-data-table-status-pending';
        case 'READY': return 'wasp-data-table-status-ready';
        case 'FAILED': return 'wasp-data-table-status-failed';
        case 'COMPLETED': return 'wasp-data-table-status-completed';
        case 'IGNORED': return 'wasp-data-table-status-ignored';
        default: return 'wasp-data-table-status-active';
      }
    }

    // Render pagination
    function renderPagination(pagination) {
      const $pagination = $('.wasp-data-table-pagination');
      let html = '';

      // Previous buttons
      html += `<a href="#" class="wasp-data-table-page-btn ${!pagination.has_prev ? 'wasp-data-table-page-btn-disabled' : ''}" data-page="1">‹‹</a>`;
      html += `<a href="#" class="wasp-data-table-page-btn ${!pagination.has_prev ? 'wasp-data-table-page-btn-disabled' : ''}" data-page="${pagination.current_page - 1}">‹</a>`;

      // Page numbers
      const startPage = Math.max(1, pagination.current_page - 2);
      const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

      if (startPage > 1) {
        html += `<a href="#" class="wasp-data-table-page-btn" data-page="1">1</a>`;
        if (startPage > 2) {
          html += `<span class="wasp-data-table-ellipsis">...</span>`;
        }
      }

      for (let i = startPage; i <= endPage; i++) {
        html += `<a href="#" class="wasp-data-table-page-btn ${i === pagination.current_page ? 'wasp-data-table-page-btn-active' : ''}" data-page="${i}">${i}</a>`;
      }

      if (endPage < pagination.total_pages) {
        if (endPage < pagination.total_pages - 1) {
          html += `<span class="wasp-data-table-ellipsis">...</span>`;
        }
        html += `<a href="#" class="wasp-data-table-page-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
      }

      // Next buttons
      html += `<a href="#" class="wasp-data-table-page-btn ${!pagination.has_next ? 'wasp-data-table-page-btn-disabled' : ''}" data-page="${pagination.current_page + 1}">›</a>`;
      html += `<a href="#" class="wasp-data-table-page-btn ${!pagination.has_next ? 'wasp-data-table-page-btn-disabled' : ''}" data-page="${pagination.total_pages}">››</a>`;

      $pagination.html(html);
    }

    // Render table info
    function renderTableInfo(pagination) {
      const $info = $('.wasp-data-table-info');
      const start = (pagination.current_page - 1) * pagination.per_page + 1;
      const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
      $info.text(`Showing ${start} - ${end} of ${pagination.total}`);
    }

    // Search functionality with debouncer
    document.getElementById("searchInput").addEventListener("input", function (e) {
      currentSearch = e.target.value;
      currentPage = 1; // Reset to first page
      
      // Add loading indicator
      e.target.classList.add('loading');
      
      // Clear existing timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }
      
      // Set new timeout for debounced search
      searchTimeout = setTimeout(function() {
        loadSalesReturnsData();
        // Remove loading indicator after search completes
        e.target.classList.remove('loading');
      }, 500); // 500ms delay
    });

    // Filter functionality
    document.getElementById("statusFilter").addEventListener("change", function (e) {
      currentStatusFilter = e.target.value;
      currentPage = 1; // Reset to first page
      loadSalesReturnsData();
    });

    // Pagination click handlers
    $(document).on('click', '.wasp-data-table-page-btn', function(e) {
      e.preventDefault();
      if (!$(this).hasClass('wasp-data-table-page-btn-disabled')) {
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage) {
          currentPage = page;
          loadSalesReturnsData();
        }
      }
    });

    // Load initial data
    loadSalesReturnsData();
  });
})(jQuery);
