<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'MySQL connection OK' . PHP_EOL;
    $pdo->exec('CREATE DATABASE IF NOT EXISTS motobook_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE motobook_admin');
    echo 'Database created/verified' . PHP_EOL;

    foreach ([__DIR__ . '/database/schema.sql', __DIR__ . '/database/seed.sql', __DIR__ . '/database/operations.sql'] as $file) {
        $sql = preg_replace('/^USE motobook_admin;\s*/m', '', file_get_contents($file));
        $sql = preg_replace('/^CREATE DATABASE.*?;\s*/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') === false
                        && strpos($e->getMessage(), 'already exists') === false
                        && strpos($e->getMessage(), '1061') === false
                        && strpos($e->getMessage(), 'Cannot add foreign key') === false
                        && strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo 'WARN: ' . $e->getMessage() . PHP_EOL;
                    }
                }
            }
        }
        echo 'Loaded: ' . basename($file) . PHP_EOL;
    }

    require_once __DIR__ . '/includes/migrate_ops.php';
    runOperationsMigration($pdo);
    echo 'Operations migration + seed data complete' . PHP_EOL;

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
        echo $label . ': ' . $stmt->fetchColumn() . PHP_EOL;
    }

    echo PHP_EOL . '========== TRIAL ACCOUNTS (ALL USE PASSWORD: password) ==========' . PHP_EOL;
    echo PHP_EOL . 'SUPER ADMIN PANEL  ->  http://localhost/IM-101/motobook/admin/login.php' . PHP_EOL;
    echo '  [Super Admin]       admin@motobook.com            (Global control: fees, staff, rules, audit)' . PHP_EOL;
    echo PHP_EOL . 'MANAGEMENT / STORE PANEL  ->  http://localhost/IM-101/motobook/A-management/login.php' . PHP_EOL;
    echo '  [Motobook Ops Mgr]  ops@motobook.com              (Orders, riders, remittance, tickets)' . PHP_EOL;
    echo '  [Motobook Support]  support@motobook.com          (Complaints, helpdesk, promos, banners)' . PHP_EOL;
    echo '  [Jollibee Manager]  jollibee.manager@motobook.com (Store: menu, helpdesk, promos, pause)' . PHP_EOL;
    echo '  [McDonalds Mgr]     mcdo.manager@motobook.com     (Store: menu, helpdesk, promos, pause)' . PHP_EOL;
    echo '  [Jollibee Owner]    owner.jollibee@email.com      (Store panel via owner login)' . PHP_EOL;
    echo PHP_EOL . 'API HEALTHCHECK  ->  http://localhost/IM-101/motobook/admin/api/v1/index.php?route=health' . PHP_EOL;
    echo PHP_EOL . 'Setup complete! Both panels are connected via the API v1 layer.' . PHP_EOL;
    echo 'Single logout is panel-scoped: each has its own session so roles stay separate.' . PHP_EOL;
} catch (PDOException $e) {
    echo 'MySQL ERROR: ' . $e->getMessage() . PHP_EOL;
    echo 'Start XAMPP MySQL service first, then visit: /admin/install.php' . PHP_EOL;
    exit(1);
}
