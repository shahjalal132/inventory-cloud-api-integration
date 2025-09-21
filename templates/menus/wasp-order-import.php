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
                    <option value="active"><?php Status_Enums::PENDING->value ?></option>
                    <option value="maintenance"><?php Status_Enums::READY->value ?></option>
                    <option value="pending"><?php Status_Enums::FAILED->value ?></option>
                    <option value="pending"><?php Status_Enums::COMPLETED->value ?></option>
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
                        <tr class="wasp-data-table-tr">
                            <td class="wasp-data-table-td">001</td>
                            <td class="wasp-data-table-td">ITM-2024-001</td>
                            <td class="wasp-data-table-td">$1,250.00</td>
                            <td class="wasp-data-table-td">2024-01-15</td>
                            <td class="wasp-data-table-td">CUST-10001</td>
                            <td class="wasp-data-table-td">Manufacturing Plant A</td>
                            <td class="wasp-data-table-td">MPA-001</td>
                            <td class="wasp-data-table-td">25</td>
                            <td class="wasp-data-table-td">
                                <span class="wasp-data-table-status wasp-data-table-status-active">Active</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer with Info and Pagination -->
        <div class="wasp-data-table-footer">
            <div class="wasp-data-table-info">
                Showing 1 - 100 of 1,000
            </div>
            <div class="wasp-data-table-pagination">
                <a href="#" class="wasp-data-table-page-btn wasp-data-table-page-btn-disabled">‹‹</a>
                <a href="#" class="wasp-data-table-page-btn wasp-data-table-page-btn-disabled">‹</a>
                <a href="#" class="wasp-data-table-page-btn wasp-data-table-page-btn-active">1</a>
                <a href="#" class="wasp-data-table-page-btn">2</a>
                <a href="#" class="wasp-data-table-page-btn">3</a>
                <a href="#" class="wasp-data-table-page-btn">4</a>
                <a href="#" class="wasp-data-table-page-btn">5</a>
                <span class="wasp-data-table-ellipsis">...</span>
                <a href="#" class="wasp-data-table-page-btn">10</a>
                <a href="#" class="wasp-data-table-page-btn">›</a>
                <a href="#" class="wasp-data-table-page-btn">››</a>
            </div>
        </div>
    </div>
</div>