<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();
runOperationsMigration($pdo);

$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT o.*, ps.store_name, r.full_name AS rider_name, r.rider_code
        FROM orders o
        LEFT JOIN partnership_stores ps ON ps.id = o.store_id
        LEFT JOIN riders r ON r.id = o.rider_id
        WHERE 1=1';
$params = [];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'delayed') {
        $threshold = 35;
        $sql .= " AND o.order_status NOT IN ('delivered','cancelled') AND (o.order_status = 'delayed' OR TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > ?)";
        $params[] = $threshold;
    } else {
        $sql .= ' AND o.order_status = ?';
        $params[] = $statusFilter;
    }
}

if ($searchQuery !== '') {
    $sql .= ' AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY o.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusCountsStmt = $pdo->query("SELECT order_status, COUNT(*) AS cnt FROM orders GROUP BY order_status")->fetchAll();
$countMap = [];
foreach ($statusCountsStmt as $sc) {
    $countMap[$sc['order_status']] = (int)$sc['cnt'];
}
$totalCount = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$pageTitle = 'Orders &amp; Deliveries';
$activePage = 'orders.php';
include __DIR__ . '/includes/header.php';
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <div style="display:flex;flex-wrap:wrap;gap:0.3rem;align-items:center;">
            <a class="nav-tab<?= $statusFilter === 'all' ? ' active' : '' ?>" href="?status=all">All (<?= $totalCount ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'pending' ? ' active' : '' ?>" href="?status=pending">Pending (<?= $countMap['pending'] ?? 0 ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'placed' ? ' active' : '' ?>" href="?status=placed">Placed (<?= $countMap['placed'] ?? 0 ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'preparing' ? ' active' : '' ?>" href="?status=preparing">Preparing (<?= $countMap['preparing'] ?? 0 ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'driver_assigned' ? ' active' : '' ?>" href="?status=driver_assigned">Assigned (<?= $countMap['driver_assigned'] ?? 0 ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'out_for_delivery' ? ' active' : '' ?>" href="?status=out_for_delivery">In Transit (<?= $countMap['out_for_delivery'] ?? 0 ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'delayed' ? ' active' : '' ?>" href="?status=delayed">Delayed</a>
            <a class="nav-tab<?= $statusFilter === 'delivered' ? ' active' : '' ?>" href="?status=delivered">Delivered (<?= $countMap['delivered'] ?? 0 ?>)</a>
            <a class="nav-tab<?= $statusFilter === 'cancelled' ? ' active' : '' ?>" href="?status=cancelled">Cancelled (<?= $countMap['cancelled'] ?? 0 ?>)</a>
        </div>
        <form method="GET" style="display:flex;gap:0.3rem;margin:0;" onsubmit="return true;">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <input type="text" class="form-control" name="q" placeholder="Search order # / customer..." value="<?= e($searchQuery) ?>" style="min-width:220px;">
            <button class="btn btn-primary btn-sm" type="submit">Search</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h2 style="margin:0;">Order Registry</h2>
            <small class="text-muted"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> · showing · click <strong>Proof</strong> for compliance audit</small>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($orders)): ?>
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <div style="font-size:2.4rem;margin-bottom:0.5rem;">📦</div>
                <p style="margin:0;font-size:0.95rem;">No orders match the current filter.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" style="border:0;margin:0;">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Store</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Rider</th>
                            <th>Total</th>
                            <th>Created</th>
                            <th>Delivered</th>
                            <th style="text-align:center;">Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>
                                <strong style="font-size:0.88rem;"><?= e($o['order_number'] ?? ('ORD-' . $o['id'])) ?></strong>
                            </td>
                            <td><?= e($o['store_name'] ?? '<em class="text-muted">—</em>') ?></td>
                            <td>
                                <div style="font-weight:500;font-size:0.84rem;"><?= e($o['customer_name'] ?? '—') ?></div>
                                <?php if (!empty($o['customer_phone'])): ?>
                                    <small class="text-muted"><?= e($o['customer_phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= statusBadge((string)($o['order_status'] ?? 'pending')) ?></td>
                            <td>
                                <div style="font-size:0.82rem;font-weight:500;">
                                    <?php
                                    $pm = strtoupper((string)($o['payment_method'] ?? 'cash'));
                                    $pmLabelMap = [
                                        'cash' => 'COD', 'COD' => 'COD',
                                        'gcash' => 'GCash', 'GCASH' => 'GCash',
                                        'maya' => 'Maya', 'MAYA' => 'Maya',
                                        'card' => 'Card', 'CARD' => 'Card',
                                    ];
                                    echo $pmMap[$pm] ?? ucfirst($pm);
                                    ?>
                                </div>
                                <?php if (!empty($o['payment_status'])): ?>
                                    <small class="text-muted"><?= e(ucfirst((string)$o['payment_status'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($o['rider_name'])): ?>
                                    <div style="font-size:0.82rem;"><?= e($o['rider_name']) ?></div>
                                    <?php if (!empty($o['rider_code'])): ?>
                                        <small class="text-muted"><?= e($o['rider_code']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em class="text-muted" style="font-size:0.82rem;">Unassigned</em>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600;white-space:nowrap;"><?= formatMoney((float)($o['order_total'] ?? 0)) ?></td>
                            <td style="white-space:nowrap;font-size:0.8rem;"><?= e(formatDateTime($o['created_at'] ?? null)) ?></td>
                            <td style="white-space:nowrap;font-size:0.8rem;"><?= e(formatDateTime($o['delivered_at'] ?? null)) ?></td>
                            <td style="text-align:center;">
                                <a href="<?= APP_URL ?>/proof_viewer.php?order_id=<?= (int)$o['id'] ?>"
                                   class="btn btn-primary btn-sm"
                                   style="font-weight:600;"
                                   title="View Proof of Delivery compliance audit">
                                    <i data-lucide="file-check" style="width:14px;height:14px;margin-right:4px;"></i>
                                    Proof
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
