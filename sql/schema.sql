-- TicketTime Event Ticketing Platform
-- Schema v1.0

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';


-- --------------------------------------------------------
-- admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    admin_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role        ENUM('admin','scanner','box_office') NOT NULL DEFAULT 'scanner',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login  DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- events
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    event_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_name      VARCHAR(255) NOT NULL,
    event_slug      VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_location  VARCHAR(255),
    event_image     VARCHAR(500) NULL,
    event_start     DATETIME NOT NULL,
    event_end       DATETIME NOT NULL,
    sale_start      DATETIME NULL,
    sale_end        DATETIME NULL,
    status          ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    UNIQUE KEY uq_event_slug (event_slug),
    KEY idx_event_status (status),
    KEY idx_event_start (event_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- ticket_types
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_types (
    ticket_type_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id            INT UNSIGNED NOT NULL,
    ticket_name         VARCHAR(255) NOT NULL,
    ticket_description  TEXT,
    price               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_fee         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity_available  INT UNSIGNED NOT NULL DEFAULT 0,
    quantity_sold       INT UNSIGNED NOT NULL DEFAULT 0,
    max_per_order       INT UNSIGNED NOT NULL DEFAULT 10,
    status              ENUM('active','inactive','sold_out') NOT NULL DEFAULT 'active',
    sort_order          INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ticket_type_id),
    KEY idx_tt_event (event_id),
    KEY idx_tt_status (status),
    CONSTRAINT fk_tt_event FOREIGN KEY (event_id) REFERENCES events (event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- orders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    order_id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_order_id     VARCHAR(64) NOT NULL,
    event_id            INT UNSIGNED NOT NULL,
    customer_first_name VARCHAR(100) NOT NULL,
    customer_last_name  VARCHAR(100) NOT NULL,
    customer_email      VARCHAR(255) NOT NULL,
    customer_phone      VARCHAR(50),
    subtotal            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_fee_total   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status              ENUM('pending','paid','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    payment_provider    ENUM('stripe','braintree','manual') NOT NULL DEFAULT 'stripe',
    payment_intent_id   VARCHAR(255) NULL,
    payment_status      VARCHAR(100) NULL,
    order_barcode_token VARCHAR(128) NOT NULL,
    willcall_status     ENUM('not_needed','pending','picked_up') NOT NULL DEFAULT 'not_needed',
    picked_up_at        DATETIME NULL,
    picked_up_by        VARCHAR(255) NULL,
    ip_address          VARCHAR(45) NULL,
    user_agent          VARCHAR(500) NULL,
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at             DATETIME NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_public_order_id (public_order_id),
    UNIQUE KEY uq_order_barcode_token (order_barcode_token),
    KEY idx_order_payment_intent (payment_intent_id),
    KEY idx_order_event (event_id),
    KEY idx_order_email (customer_email),
    KEY idx_order_status (status),
    KEY idx_order_created (created_at),
    CONSTRAINT fk_order_event FOREIGN KEY (event_id) REFERENCES events (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- order_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    ticket_type_id  INT UNSIGNED NOT NULL,
    ticket_name     VARCHAR(255) NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit_fee        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_item_id),
    KEY idx_oi_order (order_id),
    KEY idx_oi_ticket_type (ticket_type_id),
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
    CONSTRAINT fk_oi_ticket_type FOREIGN KEY (ticket_type_id) REFERENCES ticket_types (ticket_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- tickets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    ticket_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    order_item_id   INT UNSIGNED NOT NULL,
    event_id        INT UNSIGNED NOT NULL,
    ticket_type_id  INT UNSIGNED NOT NULL,
    ticket_code     VARCHAR(128) NOT NULL,
    qr_token        VARCHAR(128) NOT NULL,
    ticket_name     VARCHAR(255) NOT NULL,
    holder_name     VARCHAR(255) NULL,
    status          ENUM('valid','used','void','refunded') NOT NULL DEFAULT 'valid',
    scanned_at      DATETIME NULL,
    scanned_by      INT UNSIGNED NULL,
    scan_location   VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ticket_id),
    UNIQUE KEY uq_ticket_code (ticket_code),
    UNIQUE KEY uq_qr_token (qr_token),
    KEY idx_ticket_order (order_id),
    KEY idx_ticket_event (event_id),
    KEY idx_ticket_status (status),
    CONSTRAINT fk_ticket_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_order_item FOREIGN KEY (order_item_id) REFERENCES order_items (order_item_id),
    CONSTRAINT fk_ticket_event FOREIGN KEY (event_id) REFERENCES events (event_id),
    CONSTRAINT fk_ticket_type FOREIGN KEY (ticket_type_id) REFERENCES ticket_types (ticket_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- ticket_scans
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_scans (
    scan_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id       INT UNSIGNED NULL,
    qr_token        VARCHAR(128) NOT NULL,
    scan_result     ENUM('valid','already_used','invalid','void','refunded','wrong_event') NOT NULL,
    scan_message    VARCHAR(255) NOT NULL,
    scanned_by      INT UNSIGNED NULL,
    scan_location   VARCHAR(255) NULL,
    scanned_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    PRIMARY KEY (scan_id),
    KEY idx_scan_ticket (ticket_id),
    KEY idx_scan_token (qr_token),
    KEY idx_scan_at (scanned_at),
    KEY idx_scan_by (scanned_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key     VARCHAR(100) NOT NULL,
    setting_value   TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_id),
    UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
