## sales/Return Database Design.

sync_sales_returns_data

```SQL
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
item_number VARCHAR(255) NOT NULL,
cost DECIMAL(10,2) NOT NULL,
date_acquired DATETIME NULL,
customer_number VARCHAR(255) NULL,
site_name VARCHAR(255) NULL,
location_code VARCHAR(255) NULL,
quantity DECIMAL(10,2) NOT NULL,
status VARCHAR(255) NOT NULL DEFAULT 'PENDING',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (id)
```

sales returns import page fields

select: month, year
input file: csv, xls, xlsx
button: import