<div class="wasp-inv-container">
    <div class="wasp-inv-header">
        <h1>Wasp Sales/Return Import</h1>
        <p>Import your sales and return data</p>
    </div>

    <form id="wasp-inv-importForm">
        <div class="wasp-inv-form-row">
            <div class="wasp-inv-form-group">
                <label for="wasp-inv-month">Month</label>
                <select id="wasp-inv-month" name="month" required>
                    <option value="">Select Month</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>

            <div class="wasp-inv-form-group">
                <label for="wasp-inv-year">Year</label>
                <select id="wasp-inv-year" name="year" required>
                    <option value="">Select Year</option>
                    <?php for ( $y = 2020; $y <= 2030; $y++ ) : ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="wasp-inv-form-group">
            <label>Import File</label>
            <div class="wasp-inv-file-input-wrapper">
                <div class="wasp-inv-file-input-custom" onclick="document.getElementById('wasp-inv-fileInput').click()">
                    <div class="wasp-inv-file-icon">📄</div>
                    <div class="wasp-inv-file-text">Click to select file or drag and drop</div>
                    <div class="wasp-inv-file-types">Supported formats: XLS, XLSX</div>
                </div>
                <input type="file" id="wasp-inv-fileInput" name="file" accept=".xls,.xlsx" required>
                <div class="wasp-inv-selected-file" id="wasp-inv-selectedFile"></div>
            </div>
        </div>

        <button type="submit" class="wasp-inv-import-btn" id="wasp-inv-importBtn">
            Import Data
        </button>
    </form>

    <!-- Progress Bar -->
    <div class="wasp-progress-container" id="wasp-progress-container" style="display: none;">
        <div class="wasp-progress-header">
            <h3>Importing Data...</h3>
            <span class="wasp-progress-percentage" id="wasp-progress-percentage">0%</span>
        </div>
        <div class="wasp-progress-bar">
            <div class="wasp-progress-fill" id="wasp-progress-fill"></div>
        </div>
        <div class="wasp-progress-status" id="wasp-progress-status">Preparing import...</div>
    </div>
</div>

<?php 
// include the status enum
use BOILERPLATE\Inc\Enums\Status_Enums;
?>

<!-- sales return search filter table -->
<div class="wasp-my-20">
    <div class="wasp-data-table-container">
        <!-- Header with Filter and Search -->
        <div class="wasp-data-table-header">
            <div class="wasp-data-table-filter-section">
                <span class="wasp-data-table-filter-label">Filter by Status:</span>
                <select class="wasp-data-table-select" id="statusFilter">
                    <option value="">All</option>
                    <option value="<?php echo Status_Enums::PENDING->value ?>"><?php echo Status_Enums::PENDING->value ?></option>
                    <option value="<?php echo Status_Enums::READY->value ?>"><?php echo Status_Enums::READY->value ?></option>
                    <option value="<?php echo Status_Enums::FAILED->value ?>"><?php echo Status_Enums::FAILED->value ?></option>
                    <option value="<?php echo Status_Enums::COMPLETED->value ?>"><?php echo Status_Enums::COMPLETED->value ?></option>
                    <option value="<?php echo Status_Enums::IGNORED->value ?>"><?php echo Status_Enums::IGNORED->value ?></option>
                </select>
            </div>
            <div>
                <button class="wasp-export-btn" id="wasp-sales-return-export-btn">Export CSV</button>
            </div>
            <div>
                <button class="wasp-delete-btn" id="wasp-sales-return-delete-btn">Delete</button>
            </div>
            <div class="wasp-data-table-search-section">
                <input type="text" class="wasp-data-table-search-input"
                    placeholder="Search items, customers, locations..." id="searchInput">
            </div>
        </div>

        <!-- Table -->
        <div class="wasp-data-table-main">
            <div class="wasp-data-table-wrapper">
                <table class="wasp-data-table">
                    <thead class="wasp-data-table-thead">
                        <tr class="wasp-data-table-tr">
                            <th class="wasp-data-table-th">ID</th>
                            <th class="wasp-data-table-th">Item Number</th>
                            <th class="wasp-data-table-th">Cost</th>
                            <th class="wasp-data-table-th">Date Acquired</th>
                            <th class="wasp-data-table-th">Shop</th>
                            <th class="wasp-data-table-th">Customer Number</th>
                            <th class="wasp-data-table-th">Site Name</th>
                            <th class="wasp-data-table-th">Location Code</th>
                            <th class="wasp-data-table-th">Quantity</th>
                            <th class="wasp-data-table-th">Type</th>
                            <th class="wasp-data-table-th">Status</th>
                            <th class="wasp-data-table-th">Message</th>
                        </tr>
                    </thead>
                    <tbody class="wasp-data-table-tbody">
                        <!-- Dynamic content will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer with Info and Pagination -->
        <div class="wasp-data-table-footer">
            <div class="wasp-data-table-info">
                <!-- Dynamic info will be loaded here -->
            </div>
            <div class="wasp-data-table-pagination">
                <!-- Dynamic pagination will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="wasp-delete-modal" id="wasp-delete-modal" style="display: none;">
    <div class="wasp-delete-modal-overlay" id="wasp-delete-modal-overlay"></div>
    <div class="wasp-delete-modal-content">
        <div class="wasp-delete-modal-header">
            <h2>Delete Completed Items</h2>
            <button class="wasp-delete-modal-close" id="wasp-delete-modal-close">&times;</button>
        </div>
        <div class="wasp-delete-modal-actions">
            <button class="wasp-delete-modal-btn wasp-delete-modal-select-all" id="wasp-delete-select-all">
                Select All
            </button>
            <button class="wasp-delete-modal-btn wasp-delete-modal-delete-all" id="wasp-delete-all-btn">
                Delete All
            </button>
            <button class="wasp-delete-modal-btn wasp-delete-modal-delete-selected" id="wasp-delete-selected-btn">
                Delete Selected
            </button>
        </div>
        <div class="wasp-delete-modal-body">
            <div class="wasp-delete-modal-table-wrapper">
                <table class="wasp-delete-modal-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="wasp-delete-checkbox-all"></th>
                            <th>ID</th>
                            <th>Item Number</th>
                            <th>Cost</th>
                            <th>Date Acquired</th>
                            <th>Shop</th>
                            <th>Customer Number</th>
                            <th>Site Name</th>
                            <th>Location Code</th>
                            <th>Quantity</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody id="wasp-delete-modal-tbody">
                        <!-- Dynamic content will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wasp-delete-modal-footer">
            <p class="wasp-delete-modal-info" id="wasp-delete-modal-info">Loading...</p>
        </div>
    </div>
</div>