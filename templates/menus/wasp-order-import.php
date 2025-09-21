<div class="wasp-inv-container">
    <div class="wasp-inv-header">
        <h1>WooCommerce Order Import</h1>
        <p>Import your WooCommerce orders easily from a CSV file</p>
    </div>

    <form id="wasp-order-importForm">
        <div class="wasp-inv-form-group">
            <label>Import File</label>
            <div class="wasp-inv-file-input-wrapper">
                <div class="wasp-inv-file-input-custom"
                    onclick="document.getElementById('wasp-order-fileInput').click()">
                    <div class="wasp-inv-file-icon">📄</div>
                    <div class="wasp-inv-file-text">Click to select file or drag and drop</div>
                    <div class="wasp-inv-file-types">Supported format: CSV</div>
                </div>
                <input type="file" id="wasp-order-fileInput" name="file" accept=".csv" required>
                <div class="wasp-inv-selected-file" id="wasp-order-selectedFile"></div>
            </div>
        </div>
        <button type="submit" class="wasp-inv-import-btn" id="wasp-order-importBtn">
            Import Orders
        </button>
    </form>

    <!-- Progress Bar -->
    <div class="wasp-progress-container" id="wasp-progress-container" style="display: none;">
        <div class="wasp-progress-header">
            <h3>Importing Orders...</h3>
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
                            <th class="wasp-data-table-th">Customer Number</th>
                            <th class="wasp-data-table-th">Site Name</th>
                            <th class="wasp-data-table-th">Location Code</th>
                            <th class="wasp-data-table-th">Cost</th>
                            <th class="wasp-data-table-th">Quantity</th>
                            <th class="wasp-data-table-th">Remove Date</th>
                            <th class="wasp-data-table-th">Status</th>
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