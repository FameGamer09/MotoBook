<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requirePlatform();

$date = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null) && ($_POST['action'] ?? '') === 'collect') {
    $collectionId = (int) $_POST['collection_id'];
    $ok = markCollectionCollected($collectionId);
    flash($ok ? 'success' : 'error', $ok ? 'Cash remittance collected successfully.' : 'Collection failed.');
    header('Location: ' . APP_URL . '/remittance.php?date=' . rawurlencode($date));
    exit;
}

$collections = fetchRemittance($date);

$totalPending = 0;
$totalCollected = 0;
$totalAmount = 0;
foreach ($collections as $c) {
    $totalAmount += (float) $c['total_collected'];
    if (($c['remittance_status'] ?? '') === 'pending') { $totalPending++; }
    if (($c['remittance_status'] ?? '') === 'collected' || ($c['remittance_status'] ?? '') === 'remitted') { $totalCollected++; }
}

$pageTitle = 'Rider Cash Remittance Counter';
include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card"><div class="label">Collection date</div><div class="value" style="font-size:1.1rem"><?= e($date) ?></div></div>
    <div class="stat-card"><div class="label">Total riders</div><div class="value"><?= count($collections) ?></div></div>
    <div class="stat-card"><div class="label">Pending handover</div><div class="value" style="color:var(--danger)"><?= $totalPending ?></div></div>
    <div class="stat-card"><div class="label">Collected (Ops)</div><div class="value" style="color:var(--success)"><?= $totalCollected ?></div></div>
    <div class="stat-card"><div class="label">Total cash to verify</div><div class="value"><?= formatMoney($totalAmount) ?></div></div>
</div>

<div class="card">
    <div class="card-header">
        <h2>💰 Rider remittance POS counter</h2>
        <div>
            <label class="text-muted" style="font-size:.8rem;margin-right:.5rem">Change date:</label>
            <input type="date" class="form-control" style="width:auto;display:inline" value="<?= e($date) ?>" onchange="location.href='?date='+this.value">
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Operations workflow:</strong> Collect cash from returning riders, verify against the displayed total, then press <em>Confirm & Collect Cash Remittance</em>. Super Admin still performs final audit / reports.
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Rider</th><th>Code</th><th>Orders</th>
                        <th>Total collected</th><th>Commission</th><th>Status</th><th>Collected at</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $c): ?>
                        <tr>
                            <td><strong><?= e($c['full_name']) ?></strong></td>
                            <td><?= e($c['rider_code']) ?></td>
                            <td><strong><?= (int) $c['orders_completed'] ?></strong> orders</td>
                            <td><strong style="font-size:1rem"><?= formatMoney((float) $c['total_collected']) ?></strong></td>
                            <td><?= formatMoney((float) $c['commission_earned']) ?></td>
                            <td><?= statusBadge((string) $c['remittance_status']) ?></td>
                            <td><?= $c['collected_at'] ? formatDateTime($c['collected_at']) : '—' ?></td>
                            <td>
                                <?php if (($c['remittance_status'] ?? '') === 'pending'): ?>
                                    <form method="POST" onsubmit="return confirm('Confirm you have received <?= formatMoney((float) $c['total_collected']) ?> in cash from <?= e($c['full_name']) ?>?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="collect">
                                        <input type="hidden" name="collection_id" value="<?= (int) $c['id'] ?>">
                                        <button class="btn btn-success btn-sm" type="submit">✔ Confirm & Collect Cash</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($collections)): ?>
                        <tr><td colspan="8" class="text-muted">No rider collections scheduled for this date.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
