<?php

declare(strict_types=1);

function ensureRiderLocationLogsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS rider_location_logs (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
