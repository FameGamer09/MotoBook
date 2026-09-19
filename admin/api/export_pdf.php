<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pdo = getDBConnection();
$type = $_GET['type'] ?? 'collections';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$riderId = (int) ($_GET['rider_id'] ?? 0);

$title = 'Motobook Financial Report';
$headers = [];
$rows = [];

if ($type === 'collections') {
    $title = 'Rider Collections Report';
    $headers = ['Rider', 'Code', 'Date', 'Orders', 'Collected', 'Commission', 'Status'];
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
    $rows = $stmt->fetchAll();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 2rem; color: #0f172a; }
        h1 { color: #0891b2; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #ecfeff; }
        .meta { color: #64748b; font-size: 13px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Save as PDF</button>
    <h1><?= e($title) ?></h1>
    <p class="meta">Period: <?= e($dateFrom) ?> to <?= e($dateTo) ?> · Generated <?= date('M d, Y h:i A') ?></p>
    <table>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= e($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                        <td><?= e((string) $cell) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
