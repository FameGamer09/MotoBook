<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$pdo = getDBConnection();

$today = $pdo->query("SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total FROM orders WHERE DATE(created_at) = CURDATE() AND order_status = 'delivered'")->fetch()['total'];
$week = $pdo->query("SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND order_status = 'delivered'")->fetch()['total'];
$month = $pdo->query("SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND order_status = 'delivered'")->fetch()['total'];
$lifetime = $pdo->query("SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total FROM orders WHERE order_status = 'delivered'")->fetch()['total'];

$ridersActive = $pdo->query("SELECT COUNT(*) AS cnt FROM riders WHERE duty_status IN ('online','on_trip','idle') AND status IN ('active','on_duty')")->fetch()['cnt'];
$ridersOnTrip = $pdo->query("SELECT COUNT(*) AS cnt FROM riders WHERE duty_status = 'on_trip'")->fetch()['cnt'];
$ridersIdle = $pdo->query("SELECT COUNT(*) AS cnt FROM riders WHERE duty_status = 'idle'")->fetch()['cnt'];

$staffActive = $pdo->query("SELECT COUNT(*) AS cnt FROM staff WHERE is_active = 1 AND shift_status = 'on_shift'")->fetch()['cnt'];
$storesActive = $pdo->query("SELECT COUNT(*) AS cnt FROM partnership_stores WHERE status = 'open'")->fetch()['cnt'];

echo json_encode([
    'success' => true,
    'revenue' => [
        'today' => formatMoney((float) $today),
        'week' => formatMoney((float) $week),
        'month' => formatMoney((float) $month),
        'lifetime' => formatMoney((float) $lifetime),
    ],
    'riders' => [
        'active' => (int) $ridersActive,
        'on_trip' => (int) $ridersOnTrip,
        'idle' => (int) $ridersIdle,
    ],
    'staff' => ['active' => (int) $staffActive],
    'stores' => ['active' => (int) $storesActive],
]);
