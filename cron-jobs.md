# Cron Commands for API Endpoints

Below are the cron commands to run each endpoint every minute using `curl`, with all output discarded by redirecting to `/dev/null`.

---

### 1. prepare-sales-returns

```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/prepare-sales-returns?limit=30' \
--header 'Authorization: Basic YXRlYm9sOkF0ZWJvbDEh' > /dev/null 2>&1
```

### 2. prepare-woo-orders

```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/prepare-woo-orders?limit=30' \
--header 'Authorization: Basic YXRlYm9sOkF0ZWJvbDEh' > /dev/null 2>&1
```

### 3. import-sales-returns

```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/import-sales-returns?limit=30' \
--header 'Authorization: Basic YXRlYm9sOkF0ZWJvbDEh' > /dev/null 2>&1
```

### 4. import-woo-orders

```bash
curl --location 'https://royalties.6435cea4309176ad6a8aebb69ac8f99e-12591.sites.k-hosting.co.uk/wp-json/atebol/v1/import-woo-orders?limit=30' \
--header 'Authorization: Basic YXRlYm9sOkF0ZWJvbDEh' > /dev/null 2>&1
```

---
