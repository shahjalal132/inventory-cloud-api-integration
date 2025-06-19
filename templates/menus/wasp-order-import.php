<div class="wasp-inv-container">
    <div class="wasp-inv-header">
        <h1>WooCommerce Order Import</h1>
        <p>Import your WooCommerce orders easily from a CSV file</p>
    </div>

    <form id="wasp-order-importForm">
        <div class="wasp-inv-form-group">
            <label>Import File</label>
            <div class="wasp-inv-file-input-wrapper">
                <div class="wasp-inv-file-input-custom" onclick="document.getElementById('wasp-order-fileInput').click()">
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