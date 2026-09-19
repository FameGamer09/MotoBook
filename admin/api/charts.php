<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$pdo = getDBConnection();

$orderVolumeLabels = [];
$orderVolumeData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $orderVolumeLabels[] = date('M d', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE DATE(created_at) = ? AND order_status = 'delivered'");
    $stmt->execute([$date]);
    $orderVolumeData[] = (int) $stmt->fetch()['cnt'];
}

$peakLabels = [];
$peakData = [];
for ($h = 6; $h <= 22; $h++) {
    $peakLabels[] = sprintf('%02d:00', $h);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE HOUR(created_at) = ? AND order_status = 'delivered'");
    $stmt->execute([$h]);
    $peakData[] = (int) $stmt->fetch()['cnt'];
}

$salesLabels = $orderVolumeLabels;
$salesData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total FROM orders WHERE DATE(created_at) = ? AND order_status = 'delivered'");
    $stmt->execute([$date]);
    $salesData[] = (float) $stmt->fetch()['total'];
}

echo json_encode([
    'success' => true,
    'order_volume' => ['labels' => $orderVolumeLabels, 'data' => $orderVolumeData],
    'peak_hours' => ['labels' => $peakLabels, 'data' => $peakData],
    'daily_sales' => ['labels' => $salesLabels, 'data' => $salesData],
]);
