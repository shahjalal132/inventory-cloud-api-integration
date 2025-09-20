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
</div>

<div class="wasp-my-20">
    <div class="wasp-data-table-container">
        <!-- Header with Filter and Search -->
        <div class="wasp-data-table-header">
            <div class="wasp-data-table-filter-section">
                <span class="wasp-data-table-filter-label">Filter by Status:</span>
                <select class="wasp-data-table-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="pending">Pending</option>
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
                            <th class="wasp-data-table-th">Cost</th>
                            <th class="wasp-data-table-th">Date Acquired</th>
                            <th class="wasp-data-table-th">Customer Number</th>
                            <th class="wasp-data-table-th">Site Name</th>
                            <th class="wasp-data-table-th">Location Code</th>
                            <th class="wasp-data-table-th">Quantity</th>
                            <th class="wasp-data-table-th">Type</th>
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
                            <td class="wasp-data-table-td">Equipment</td>
                            <td class="wasp-data-table-td">
                                <span class="wasp-data-table-status wasp-data-table-status-active">Active</span>
                            </td>
                        </tr>
                        <tr class="wasp-data-table-tr">
                            <td class="wasp-data-table-td">002</td>
                            <td class="wasp-data-table-td">ITM-2024-002</td>
                            <td class="wasp-data-table-td">$850.50</td>
                            <td class="wasp-data-table-td">2024-02-20</td>
                            <td class="wasp-data-table-td">CUST-10002</td>
                            <td class="wasp-data-table-td">Warehouse Central</td>
                            <td class="wasp-data-table-td">WC-205</td>
                            <td class="wasp-data-table-td">15</td>
                            <td class="wasp-data-table-td">Supplies</td>
                            <td class="wasp-data-table-td">
                                <span
                                    class="wasp-data-table-status wasp-data-table-status-maintenance">Maintenance</span>
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