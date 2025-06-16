const waspInvFileInput = document.getElementById("wasp-inv-fileInput");
const waspInvFileCustom = document.querySelector(".wasp-inv-file-input-custom");
const waspInvSelectedFile = document.getElementById("wasp-inv-selectedFile");
const waspInvImportBtn = document.getElementById("wasp-inv-importBtn");
const waspInvForm = document.getElementById("wasp-inv-importForm");

// File input handling
waspInvFileInput.addEventListener("change", function () {
  if (this.files.length > 0) {
    const file = this.files[0];
    const fileName = file.name;
    const fileSize = (file.size / 1024 / 1024).toFixed(2);

    waspInvSelectedFile.innerHTML = `📁 ${fileName} (${fileSize} MB)`;
    waspInvSelectedFile.style.display = "block";

    waspInvFileCustom.querySelector(".wasp-inv-file-text").textContent =
      "File selected";
    waspInvFileCustom.querySelector(".wasp-inv-file-types").style.display =
      "none";
  }
});

// Drag and drop functionality
waspInvFileCustom.addEventListener("dragover", function (e) {
  e.preventDefault();
  this.classList.add("wasp-inv-dragover");
});

waspInvFileCustom.addEventListener("dragleave", function (e) {
  e.preventDefault();
  this.classList.remove("wasp-inv-dragover");
});

waspInvFileCustom.addEventListener("drop", function (e) {
  e.preventDefault();
  this.classList.remove("wasp-inv-dragover");

  const files = e.dataTransfer.files;
  if (files.length > 0) {
    const file = files[0];
    const allowedTypes = [".csv", ".xls", ".xlsx"];
    const fileExtension = "." + file.name.split(".").pop().toLowerCase();

    if (allowedTypes.includes(fileExtension)) {
      waspInvFileInput.files = files;
      waspInvFileInput.dispatchEvent(new Event("change"));
    } else {
      alert("Please select a valid file format (CSV, XLS, XLSX)");
    }
  }
});

// Form submission
waspInvForm.addEventListener("submit", function (e) {
  e.preventDefault();

  const month = document.getElementById("wasp-inv-month").value;
  const year = document.getElementById("wasp-inv-year").value;
  const file = waspInvFileInput.files[0];

  if (!month || !year || !file) {
    alert("Please fill in all required fields");
    return;
  }

  // Simulate import process
  waspInvImportBtn.disabled = true;
  waspInvImportBtn.textContent = "Importing...";

  setTimeout(() => {
    alert(
      `Import completed successfully!\nMonth: ${
        document.getElementById("wasp-inv-month").options[
          document.getElementById("wasp-inv-month").selectedIndex
        ].text
      }\nYear: ${year}\nFile: ${file.name}`
    );

    // Reset form
    waspInvForm.reset();
    waspInvSelectedFile.style.display = "none";
    waspInvFileCustom.querySelector(".wasp-inv-file-text").textContent =
      "Click to select file or drag and drop";
    waspInvFileCustom.querySelector(".wasp-inv-file-types").style.display =
      "block";

    waspInvImportBtn.disabled = false;
    waspInvImportBtn.textContent = "Import Data";
  }, 2000);
});
