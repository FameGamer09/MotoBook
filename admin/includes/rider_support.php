<?php

declare(strict_types=1);

function ensureRiderSupportRequestTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS rider_support_requests (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // CREATE TABLE IF NOT EXISTS does not update a table made by an older
    // rider migration. Add the reply field in place so the management inbox
    // can safely query older installations too.
    $column = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'rider_support_requests'
          AND COLUMN_NAME = 'staff_reply'");
    $column->execute();
    if ((int) $column->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE rider_support_requests ADD COLUMN staff_reply TEXT NULL AFTER message');
    }
}
