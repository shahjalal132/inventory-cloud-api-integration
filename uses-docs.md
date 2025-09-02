
# Wasp Plugin — Setup & Usage Guide

## ✅ Plugin Installation & Setup
1. Install and activate the plugin.  
   Make sure to deactivate any previous versions of this plugin before activating the latest one.

### 🔧 Configure API Settings
[API Settings Screenshot](https://prnt.sc/N_3bg0bBVQeq)  
Go to **Wasp Settings → Wasp Options** and save the API Base URL, API Token, and other credentials.

### 📦 Import Orders
[Import Orders Screenshot](https://prnt.sc/LmpSEB5_lXLI)  
Navigate to **Wasp Settings → Wasp Order Import** to import your orders.

### 🔁 Import Sales/Returns
[Sales/Returns Import Screenshot](https://prnt.sc/dYGeTNaJbSTv)  
Navigate to **Wasp Settings → Wasp Sales/Returns Import** to import your sales returns.

### ⚠️ Important Rules for Sales/Returns Import
[Rules Screenshot](https://prnt.sc/gx6IOLa3lEfa)  
- If you select the format ≤ 2023, make sure the main sheet name is **`Gwybodaeth`**.  
- If you select the format ≥ 2025, the sheet name must be **`Sheet1`**.  
- If the sheet name does not match these, the import will fail, as the backend logic relies on these exact names.

---

## 🔄 Scheduled Jobs (Cron Setup)
Go to **Wasp Settings → Wasp Options**, then scroll to the **API Endpoints** section.  
` **Set the following jobs (run every minute):**`

### 1. Prepare Sales Returns
```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/prepare-sales-returns'
```

### 2. Prepare Woo Orders
```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/prepare-woo-orders'
```

### 3. Import Sales Returns
```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/import-sales-returns'
```

### 4. Import Woo Orders
```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/import-woo-orders'
```

### 5. Remove Completed Sales Returns
```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/remove-completed-sales-returns'
```

### 6. Remove Completed Woo Orders
```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/remove-completed-woo-orders'
```

---

## 📊 Status Check

There are two endpoints to check the status — one for **sales returns** and another for **orders**.

To view the status:
1. First, log in to your site.  
2. Navigate to **Wasp Options**.  
3. Run the endpoint.  

[Status Endpoint Screenshot](https://prnt.sc/hquOj4mjTgyt)  
It will display a summary of the sales returns status.  

[Status Result Screenshot](https://prnt.sc/zfsVUyQyR6BQ)  

You can follow the same process for checking the **order status** using its endpoint.
