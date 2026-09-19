-- Motobook Super Admin Database Schema
-- Import via phpMyAdmin or: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS motobook_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE motobook_admin;

-- Super Admins
CREATE TABLE IF NOT EXISTS super_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Store Categories
CREATE TABLE IF NOT EXISTS store_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Partnership Stores
CREATE TABLE IF NOT EXISTS partnership_stores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NULL,
    branch_address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    contact_phone VARCHAR(30) NULL,
    contact_email VARCHAR(150) NULL,
    operating_hours VARCHAR(100) DEFAULT '08:00 - 22:00',
    commission_rate DECIMAL(5, 2) DEFAULT 10.00,
    status ENUM('open', 'closed', 'paused', 'offline') DEFAULT 'open',
    owner_name VARCHAR(100) NULL,
    owner_email VARCHAR(150) NULL,
    owner_password VARCHAR(255) NULL,
    business_permit VARCHAR(255) NULL,
    tax_id VARCHAR(100) NULL,
    total_orders INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE SET NULL
);

-- Riders
CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    password VARCHAR(255) NOT NULL,
    vehicle_type VARCHAR(50) DEFAULT 'Motorcycle',
    vehicle_plate VARCHAR(30) NULL,
    license_number VARCHAR(50) NULL,
    status ENUM('active', 'inactive', 'on_duty', 'suspended') DEFAULT 'active',
    duty_status ENUM('online', 'on_trip', 'idle', 'offline') DEFAULT 'offline',
    total_deliveries INT UNSIGNED DEFAULT 0,
    avg_rating DECIMAL(3, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Staff
CREATE TABLE IF NOT EXISTS staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password VARCHAR(255) NOT NULL,
    store_id INT UNSIGNED NULL,
    role ENUM('super_admin', 'order_approver', 'inventory_manager', 'support_representative', 'store_operator') DEFAULT 'store_operator',
    shift_status ENUM('on_shift', 'off_shift', 'break') DEFAULT 'off_shift',
    is_active TINYINT(1) DEFAULT 1,
    last_active_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE SET NULL
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    store_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED NULL,
    customer_name VARCHAR(150) NOT NULL,
    order_total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    delivery_fee DECIMAL(10, 2) DEFAULT 0.00,
    commission_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_method ENUM('cash', 'gcash', 'maya', 'card') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'paid',
    order_status ENUM('pending', 'preparing', 'picked_up', 'delivered', 'cancelled') DEFAULT 'delivered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
);

-- Rider Daily Collections (POS Ledger)
CREATE TABLE IF NOT EXISTS rider_daily_collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    collection_date DATE NOT NULL,
    orders_completed INT UNSIGNED DEFAULT 0,
    total_collected DECIMAL(12, 2) DEFAULT 0.00,
    commission_earned DECIMAL(12, 2) DEFAULT 0.00,
    remittance_status ENUM('pending', 'remitted', 'partial') DEFAULT 'pending',
    remitted_at DATETIME NULL,
    remitted_by INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rider_date (rider_id, collection_date),
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    FOREIGN KEY (remitted_by) REFERENCES super_admins(id) ON DELETE SET NULL
);

-- Digital Payment Logs
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED NULL,
    payment_method ENUM('gcash', 'maya', 'card') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    reference_number VARCHAR(100) NULL,
    status ENUM('success', 'failed', 'pending') DEFAULT 'success',
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
);

-- Customer Reviews
CREATE TABLE IF NOT EXISTS customer_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NULL,
    rider_id INT UNSIGNED NULL,
    store_id INT UNSIGNED NULL,
    customer_name VARCHAR(150) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NULL,
    is_flagged TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE SET NULL
);

-- Staff Activity Log
CREATE TABLE IF NOT EXISTS staff_activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);
