<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'confirm_remittance') {
        $collectionId = (int) ($_POST['collection_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE rider_daily_collections SET remittance_status = ?, remitted_at = NOW(), remitted_by = ? WHERE id = ? AND remittance_status IN ('pending','collected')");
        $stmt->execute(['remitted', $_SESSION['admin_id'], $collectionId]);
        flash('success', 'Final remittance audit completed.');
        redirect('/pos.php');
    }
}

$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$riderFilter = (int) ($_GET['rider_id'] ?? 0);
$paymentFilter = $_GET['payment_method'] ?? '';

$pageTitle = 'POS & Remittance';
include __DIR__ . '/includes/header.php';

$sql = '
    SELECT rdc.*, r.full_name AS rider_name, r.rider_code
    FROM rider_daily_collections rdc
    JOIN riders r ON r.id = rdc.rider_id
    WHERE rdc.collection_date BETWEEN ? AND ?
';
$params = [$dateFrom, $dateTo];

if ($riderFilter > 0) {
    $sql .= ' AND rdc.rider_id = ?';
    $params[] = $riderFilter;
}

$sql .= ' ORDER BY rdc.collection_date DESC, r.full_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$collections = $stmt->fetchAll();

$paymentSql = '
    SELECT pl.*, o.order_number, r.full_name AS rider_name
    FROM payment_logs pl
    JOIN orders o ON o.id = pl.order_id
    LEFT JOIN riders r ON r.id = pl.rider_id
    WHERE DATE(pl.processed_at) BETWEEN ? AND ?
';
$paymentParams = [$dateFrom, $dateTo];

if ($riderFilter > 0) {
    $paymentSql .= ' AND pl.rider_id = ?';
    $paymentParams[] = $riderFilter;
}

if ($paymentFilter !== '') {
    $paymentSql .= ' AND pl.payment_method = ?';
    $paymentParams[] = $paymentFilter;
}

$paymentSql .= ' ORDER BY pl.processed_at DESC';
$paymentStmt = $pdo->prepare($paymentSql);
$paymentStmt->execute($paymentParams);
$payments = $paymentStmt->fetchAll();

$riders = $pdo->query('SELECT id, full_name FROM riders ORDER BY full_name')->fetchAll();
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Rider Daily Income & Collection Ledger</h2>
        <div class="btn-group">
            <a href="<?= APP_URL ?>/api/export.php?type=collections&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&rider_id=<?= $riderFilter ?>" class="btn btn-outline btn-sm">Export CSV</a>
            <a href="<?= APP_URL ?>/api/export_pdf.php?type=collections&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&rider_id=<?= $riderFilter ?>" class="btn btn-outline btn-sm" target="_blank">Export PDF</a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div class="form-group">
                <label>Rider</label>
                <select name="rider_id" class="form-control">
                    <option value="0">All Riders</option>
                    <?php foreach ($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $riderFilter === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Rider Name</th>
                        <th>Date</th>
                        <th>Orders Completed</th>
                        <th>Total Collected Cash</th>
                        <th>Commission Earned</th>
                        <th>Remittance Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($collections)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No collection records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($collections as $row): ?>
                    <tr>
                        <td><strong><?= e($row['rider_name']) ?></strong><br><small class="text-muted"><?= e($row['rider_code']) ?></small></td>
                        <td><?= formatDate($row['collection_date']) ?></td>
                        <td><?= (int) $row['orders_completed'] ?> Orders</td>
                        <td><?= formatMoney((float) $row['total_collected']) ?></td>
                        <td><?= formatMoney((float) $row['commission_earned']) ?></td>
                        <td><?= statusBadge($row['remittance_status']) ?></td>
                        <td>
                            <?php if (in_array($row['remittance_status'], ['pending', 'collected'], true)): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmAction('Mark final Super Admin remittance audit?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="confirm_remittance">
                                <input type="hidden" name="collection_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Final Audit / Remit</button>
                            </form>
                            <?php else: ?>
                                <small class="text-muted"><?= $row['remitted_at'] ? 'Remitted ' . formatDateTime($row['remitted_at']) : '—' ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Digital Payment Logs</h2>
        <a href="<?= APP_URL ?>/api/export.php?type=payments&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&rider_id=<?= $riderFilter ?>&payment_method=<?= e($paymentFilter) ?>" class="btn btn-outline btn-sm">Export CSV</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= e($dateTo) ?>">
            <input type="hidden" name="rider_id" value="<?= $riderFilter ?>">
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="">All Methods</option>
                    <option value="gcash" <?= $paymentFilter === 'gcash' ? 'selected' : '' ?>>GCash</option>
                    <option value="maya" <?= $paymentFilter === 'maya' ? 'selected' : '' ?>>Maya</option>
                    <option value="card" <?= $paymentFilter === 'card' ? 'selected' : '' ?>>Card</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Rider</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Processed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No digital payments found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><?= e($pay['order_number']) ?></td>
                        <td><?= e($pay['rider_name'] ?? 'N/A') ?></td>
                        <td><?= statusBadge($pay['payment_method']) ?></td>
                        <td><?= formatMoney((float) $pay['amount']) ?></td>
                        <td><?= e($pay['reference_number'] ?? '—') ?></td>
                        <td><?= statusBadge($pay['status']) ?></td>
                        <td><?= formatDateTime($pay['processed_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
