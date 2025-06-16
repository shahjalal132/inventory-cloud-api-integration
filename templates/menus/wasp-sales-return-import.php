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
                    <option value="2023">2023</option>
                    <option value="2025">2025</option>
                </select>
            </div>
        </div>

        <div class="wasp-inv-form-group">
            <label>Import File</label>
            <div class="wasp-inv-file-input-wrapper">
                <div class="wasp-inv-file-input-custom" onclick="document.getElementById('wasp-inv-fileInput').click()">
                    <div class="wasp-inv-file-icon">📄</div>
                    <div class="wasp-inv-file-text">Click to select file or drag and drop</div>
                    <div class="wasp-inv-file-types">Supported formats: CSV, XLS, XLSX</div>
                </div>
                <input type="file" id="wasp-inv-fileInput" name="file" accept=".csv,.xls,.xlsx" required>
                <div class="wasp-inv-selected-file" id="wasp-inv-selectedFile"></div>
            </div>
        </div>

        <button type="submit" class="wasp-inv-import-btn" id="wasp-inv-importBtn">
            Import Data
        </button>
    </form>
</div>