-- Motobook operational tables (Management + Super Admin API)
-- Safe to re-run: CREATE IF NOT EXISTS / INSERT IGNORE
USE motobook_admin;

CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_label VARCHAR(150) NOT NULL,
    is_locked_for_staff TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    actor_type ENUM('super_admin', 'platform_staff', 'store_staff', 'store_owner') NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    actor_email VARCHAR(150) NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_type, actor_id)
);

CREATE TABLE IF NOT EXISTS rider_shifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    clocked_in_at DATETIME NULL,
    clocked_out_at DATETIME NULL,
    shift_status ENUM('clocked_in', 'active', 'on_break', 'clocked_out') DEFAULT 'clocked_in',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rider_shift (rider_id, shift_date),
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rider_incidents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    incident_type ENUM('late_arrival', 'broken_bag', 'vehicle_issue', 'safety_briefing', 'other') NOT NULL,
    notes TEXT NOT NULL,
    logged_by_staff_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rider_location_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    rider_order_id INT UNSIGNED NULL,
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(11, 7) NOT NULL,
    heading DECIMAL(7, 2) NULL,
    speed_kmh DECIMAL(7, 2) NULL,
    accuracy_m DECIMAL(9, 2) NULL,
    motion_state VARCHAR(16) NOT NULL DEFAULT 'IDLE',
    is_offline_batch TINYINT(1) NOT NULL DEFAULT 0,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX rider_location_recorded_index (rider_id, recorded_at),
    INDEX rider_order_location_recorded_index (rider_order_id, recorded_at),
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rider_support_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'Other',
    subject VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
    staff_reply TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX rider_support_status_created_index (status, created_at),
    INDEX rider_support_rider_created_index (rider_id, created_at),
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS complaint_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    order_id INT UNSIGNED NULL,
    store_id INT UNSIGNED NULL,
    rider_id INT UNSIGNED NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(30) NULL,
    category ENUM('missing_item', 'wrong_order', 'damaged_item', 'cold_food', 'rider_behavior', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_review', 'resolved', 'rejected') DEFAULT 'open',
    resolution_notes TEXT NULL,
    assigned_staff_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE SET NULL,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ticket_evidence (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    evidence_type ENUM('photo', 'chat', 'gps', 'note') NOT NULL,
    file_path VARCHAR(255) NULL,
    content TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES complaint_tickets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS order_chats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    sender_role ENUM('customer', 'rider', 'store', 'staff') NOT NULL,
    sender_name VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS gps_tracks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS refunds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    refund_type ENUM('refund', 'replacement') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    processed_by_staff_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES complaint_tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS store_menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(80) DEFAULT 'Main',
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS merchant_help_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    store_id INT UNSIGNED NOT NULL,
    subject VARCHAR(200) NOT NULL,
    category ENUM('order_delay', 'app_glitch', 'menu_help', 'account', 'other') DEFAULT 'other',
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved') DEFAULT 'open',
    staff_reply TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS promo_banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    caption VARCHAR(255) NULL,
    image_path VARCHAR(255) NULL,
    hex_color VARCHAR(7) DEFAULT '#06b6d4',
    store_id INT UNSIGNED NULL,
    is_active TINYINT(1) DEFAULT 1,
    uploaded_by_staff_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS store_promos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    discount_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_by VARCHAR(150) NULL,
    reviewed_by_staff_id INT UNSIGNED NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES partnership_stores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS order_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    notes TEXT NULL,
    actor_email VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_label, is_locked_for_staff) VALUES
('global_delivery_fee', '49.00', 'Global Delivery Fee (PHP)', 1),
('bulk_min_orders', '3', 'Bulking Rule: Minimum Orders', 1),
('bulk_discount_percent', '10', 'Bulking Rule: Discount Percent', 1),
('max_refund_amount', '500.00', 'Max Staff Refund Amount (PHP)', 1),
('delay_threshold_minutes', '35', 'Order Delay Alert Threshold (minutes)', 1),
('api_service_name', 'Motobook Super Admin API v1', 'API Service Name', 1);
