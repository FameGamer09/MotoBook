<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 30,
]);

$pdo->exec('CREATE DATABASE IF NOT EXISTS motobook_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE motobook_admin');
echo "DB ready\n";
flush();

foreach ([__DIR__ . '/database/schema.sql', __DIR__ . '/database/seed.sql', __DIR__ . '/database/operations.sql'] as $file) {
    $sql = file_get_contents($file);
    $sql = preg_replace('/^USE motobook_admin;\s*/m', '', $sql);
    $sql = preg_replace('/^CREATE DATABASE.*?;\s*/m', '', $sql);
    $lines = array_filter(array_map('trim', explode("\n", $sql)));
    $statement = '';
    foreach ($lines as $line) {
        if ($line === '' || str_starts_with($line, '--') || str_starts_with($line, '/*')) {
            continue;
        }
        $statement .= ' ' . $line;
        if (str_ends_with(rtrim($statement), ';')) {
            try {
                $pdo->exec(trim($statement));
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Duplicate') === false
                    && str_contains($msg, 'already exists') === false
                    && str_contains($msg, '1061') === false
                    && str_contains($msg, 'Cannot add foreign key') === false
                    && str_contains($msg, 'Unknown column') === false
                    && str_contains($msg, 'Duplicate entry') === false
                ) {
                    echo "WARN [" . basename($file) . "]: " . $msg . "\n";
                }
            }
            $statement = '';
        }
    }
    echo "Loaded: " . basename($file) . "\n";
    flush();
}

require_once __DIR__ . '/includes/migrate_ops.php';
runOperationsMigration($pdo);
echo "Migration + seed completed\n\n";

$counts = [
    'super_admins' => 'Super admins',
    'staff' => 'Staff accounts',
    'partnership_stores' => 'Partner stores',
    'orders' => 'Orders total',
    'riders' => 'Riders',
    'complaint_tickets' => 'Complaint tickets',
    'promo_banners' => 'Promo banners',
    'store_promos' => 'Store promos',
    'merchant_help_tickets' => 'Helpdesk tickets',
    'platform_settings' => 'Platform settings',
    'rider_incidents' => 'Rider incidents',
    'rider_daily_collections' => 'Daily rider collections',
];
foreach ($counts as $table => $label) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $table);
    echo $label . ': ' . $stmt->fetchColumn() . "\n";
}
