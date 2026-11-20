-- file: sql/install.sql
-- MIMA Database Installation Script
-- Run this script on your MySQL/MariaDB server

CREATE DATABASE IF NOT EXISTS mima;
USE mima;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone VARCHAR(15) UNIQUE NOT NULL,
    business_name VARCHAR(100) NOT NULL,
    tax_id VARCHAR(50),
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner', 'admin') DEFAULT 'owner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    barcode VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    unit VARCHAR(20) DEFAULT 'piece',
    reorder_threshold INT DEFAULT 10,
    current_stock INT DEFAULT 0,
    selling_price DECIMAL(12,2),
    cost_price DECIMAL(12,2),
    is_vat_exempt BOOLEAN DEFAULT FALSE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_barcode (user_id, barcode),
    INDEX idx_stock (current_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory transactions table
CREATE TABLE inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12,2),
    selling_price DECIMAL(12,2),
    unit_price DECIMAL(12,2),
    vat_amount DECIMAL(12,2),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    offline_id VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_sync_status (sync_status),
    INDEX idx_offline_id (offline_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchase orders table
CREATE TABLE purchase_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NULL,
    items JSON NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft', 'submitted', 'consolidated', 'in_transit', 'delivered', 'closed') DEFAULT 'draft',
    supply_chain_ref VARCHAR(100),
    submitted_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_po_number (po_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tax ledger table
CREATE TABLE tax_ledger (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_id INT,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    taxable_value DECIMAL(12,2) NOT NULL,
    vat_rate DECIMAL(5,2) DEFAULT 7.5,
    vat_amount DECIMAL(12,2) NOT NULL,
    transaction_type ENUM('input', 'output') NOT NULL,
    firs_submission_id VARCHAR(100),
    submission_status ENUM('pending', 'submitted', 'failed') DEFAULT 'pending',
    submitted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES inventory_transactions(id) ON DELETE SET NULL,
    INDEX idx_period (period_start, period_end),
    INDEX idx_submission_status (submission_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tax rules table
CREATE TABLE tax_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_from DATE NOT NULL,
    vat_rate DECIMAL(5,2) DEFAULT 7.5,
    inventory_threshold_ngn DECIMAL(15,2),
    exempt_categories JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suppliers table (stub for CBSCSC)
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    default_lead_time_days INT DEFAULT 3,
    lead_time_days INT DEFAULT 3,
    location VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample data
INSERT INTO users (phone, business_name, tax_id, password_hash, role) VALUES
('08012345678', 'StockWise Enterprises', '01234567-0001', '$2y$10$YourHashHere', 'owner'),
('08087654321', 'Lagos Mart', '01234567-0002', '$2y$10$YourHashHere', 'owner');

-- Note: Password hash placeholder - use password_hash('password123', PASSWORD_DEFAULT) to generate

INSERT INTO tax_rules (effective_from, vat_rate, inventory_threshold_ngn, exempt_categories) VALUES
('2024-01-01', 7.5, 25000000, '["Basic Food Items", "Educational Materials", "Medical Supplies"]');

INSERT INTO suppliers (user_id, name, phone, default_lead_time_days, location) VALUES
(1, 'Lagos Electronics Hub', '08011111111', 2, 'Ikeja, Lagos'),
(1, 'Beauty Distributors Ltd', '08022222222', 3, 'Victoria Island, Lagos'),
(1, 'Timepieces Nigeria', '08033333333', 5, 'Ajah, Lagos');

INSERT INTO products (user_id, barcode, name, category, reorder_threshold, current_stock, selling_price, cost_price) VALUES
(1, 'PWRB001', 'Power Bank 10000mAh', 'Electronics', 20, 100, 25000, 15000),
(1, 'IPHC002', 'iPhone Charger', 'Electronics', 20, 100, 10000, 6000),
(1, 'RECL003', 'Rechargeable Lamp', 'Electronics', 10, 50, 10500, 6300),
(1, 'ALMC004', 'Alarm Clock', 'Electronics', 10, 50, 100, 60),
(1, 'WRTW005', 'Wristwatch', 'Electronics', 10, 50, 20000, 12000),
(1, 'FCMK006', 'Face Mask (50pcs)', 'Beauty', 20, 100, 2000, 1200);

INSERT INTO inventory_transactions (product_id, user_id, type, quantity, unit_cost, selling_price, sync_status) VALUES
(1, 1, 'in', 100, 15000, 25000, 'synced'),
(2, 1, 'in', 100, 6000, 10000, 'synced'),
(3, 1, 'in', 50, 6300, 10500, 'synced'),
(4, 1, 'in', 50, 60, 100, 'synced'),
(5, 1, 'in', 50, 12000, 20000, 'synced'),
(6, 1, 'in', 100, 1200, 2000, 'synced');

-- Offline queue (used by test_sync.php and test_sync_process.php)
CREATE TABLE IF NOT EXISTS offline_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    operation VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    data LONGTEXT,
    sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    INDEX idx_queue_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment transactions (used by test_payment.php)
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    email VARCHAR(100),
    status VARCHAR(30) DEFAULT 'pending',
    metadata LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
