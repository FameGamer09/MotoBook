CREATE TABLE IF NOT EXISTS rider_account_settings (
    rider_id INT UNSIGNED PRIMARY KEY,
    address VARCHAR(255) DEFAULT NULL,
    vehicle_or_number VARCHAR(64) DEFAULT NULL,
    vehicle_cr_number VARCHAR(64) DEFAULT NULL,
    notification_preferences JSON DEFAULT NULL,
    quick_pin_hash VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rider_account_settings_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rider_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    document_type ENUM('license', 'insurance', 'permit') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    status ENUM('PENDING', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rider_document_type (rider_id, document_type),
    INDEX idx_rider_documents_status (status),
    CONSTRAINT fk_rider_documents_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rider_support_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL,
    subject VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED') NOT NULL DEFAULT 'OPEN',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rider_support_requests_rider (rider_id, created_at),
    CONSTRAINT fk_rider_support_requests_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;