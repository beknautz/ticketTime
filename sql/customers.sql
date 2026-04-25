-- Customer accounts table
ALTER TABLE orders ADD COLUMN customer_id INT UNSIGNED NULL AFTER order_id,
    ADD KEY idx_order_customer (customer_id);

CREATE TABLE IF NOT EXISTS customers (
    customer_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(255) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(50) NULL,
    address         VARCHAR(255) NULL,
    city            VARCHAR(100) NULL,
    state           VARCHAR(50) NULL,
    zip             VARCHAR(20) NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    reset_token     VARCHAR(128) NULL,
    reset_expires   DATETIME NULL,
    last_login      DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    UNIQUE KEY uq_customer_email (email),
    KEY idx_customer_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD CONSTRAINT fk_order_customer
    FOREIGN KEY (customer_id) REFERENCES customers (customer_id) ON DELETE SET NULL;
