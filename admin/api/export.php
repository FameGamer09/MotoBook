<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="motobook_export_' . date('Ymd_His') . '.csv"');

$pdo = getDBConnection();
$type = $_GET['type'] ?? 'collections';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$riderId = (int) ($_GET['rider_id'] ?? 0);
$paymentMethod = $_GET['payment_method'] ?? '';

$output = fopen('php://output', 'w');

if ($type === 'collections') {
    fputcsv($output, ['Rider Name', 'Rider Code', 'Date', 'Orders Completed', 'Total Collected', 'Commission Earned', 'Remittance Status']);

    $sql = '
        SELECT r.full_name, r.rider_code, rdc.collection_date, rdc.orders_completed,
               rdc.total_collected, rdc.commission_earned, rdc.remittance_status
        FROM rider_daily_collections rdc
        JOIN riders r ON r.id = rdc.rider_id
        WHERE rdc.collection_date BETWEEN ? AND ?
    ';
    $params = [$dateFrom, $dateTo];

    if ($riderId > 0) {
        $sql .= ' AND rdc.rider_id = ?';
        $params[] = $riderId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }
} elseif ($type === 'payments') {
    fputcsv($output, ['Order Number', 'Rider', 'Payment Method', 'Amount', 'Reference', 'Status', 'Processed At']);

    $sql = '
        SELECT o.order_number, r.full_name, pl.payment_method, pl.amount,
               pl.reference_number, pl.status, pl.processed_at
        FROM payment_logs pl
        JOIN orders o ON o.id = pl.order_id
        LEFT JOIN riders r ON r.id = pl.rider_id
        WHERE DATE(pl.processed_at) BETWEEN ? AND ?
    ';
    $params = [$dateFrom, $dateTo];

    if ($riderId > 0) {
        $sql .= ' AND pl.rider_id = ?';
        $params[] = $riderId;
    }

    if ($paymentMethod !== '') {
        $sql .= ' AND pl.payment_method = ?';
        $params[] = $paymentMethod;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }
} elseif ($type === 'riders') {
    fputcsv($output, ['Code', 'Name', 'Email', 'Phone', 'Status', 'Duty Status', 'Deliveries', 'Rating']);

    $stmt = $pdo->query('SELECT rider_code, full_name, email, phone, status, duty_status, total_deliveries, avg_rating FROM riders ORDER BY full_name');
    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }
} elseif ($type === 'staff') {
    fputcsv($output, ['Staff ID', 'Name', 'Email', 'Store', 'Role', 'Shift Status', 'Active', 'Last Active']);

    $stmt = $pdo->query('
        SELECT s.staff_code, s.full_name, s.email, ps.store_name, s.role, s.shift_status, s.is_active, s.last_active_at
        FROM staff s
        LEFT JOIN partnership_stores ps ON ps.id = s.store_id
        ORDER BY s.full_name
    ');
    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }
} elseif ($type === 'stores') {
    fputcsv($output, ['Store Name', 'Category', 'Address', 'Status', 'Hours', 'Commission %', 'Total Orders']);

    $stmt = $pdo->query('
        SELECT ps.store_name, sc.name, ps.branch_address, ps.status, ps.operating_hours,
               ps.commission_rate, ps.total_orders
        FROM partnership_stores ps
        LEFT JOIN store_categories sc ON sc.id = ps.category_id
        ORDER BY ps.store_name
    ');
    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
