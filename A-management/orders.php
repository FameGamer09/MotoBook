<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null) && ($_POST['action'] ?? '') === 'reassign') {
    requirePlatform();
    $orderId = (int)$_POST['order_id'];
    $riderId = (int)$_POST['rider_id'];
    $reason = trim($_POST['reason'] ?? 'Vehicle trouble / stopped moving');
    $ok = reassignOrderRider($orderId, $riderId, $reason);
    flash($ok ? 'success' : 'error', $ok ? 'Rider reassigned successfully.' : 'Reassign failed.');
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

$status = $_GET['status'] ?? 'all';
$orders = fetchOrders($status);

$columns = [
    'placed' => 'Placed',
    'preparing' => 'Preparing',
    'driver_assigned' => 'Driver Assigned',
    'out_for_delivery' => 'Out for Delivery',
    'delayed' => 'Delayed',
];

$grouped = [];
foreach (array_keys($columns) as $key) {
    $grouped[$key] = [];
}
foreach ($orders as $order) {
    $bucket = orderBucket($order['display_status'] ?? $order['order_status']);
    $grouped[$bucket][] = $order;
}

$riders = isPlatformStaff() ? fetchActiveRiders() : [];
$detailId = (int)($_GET['id'] ?? 0);
$detail = null;
if ($detailId) {
    $detailOrder = fetchOrderById($detailId);
    if ($detailOrder) {
        $pdo = getOpsDB();
        $eventsStmt = $pdo->prepare('SELECT oe.*, s.email AS actor_email FROM order_events oe LEFT JOIN staff s ON s.id = oe.actor_id AND oe.actor_type = ? WHERE oe.order_id = ? ORDER BY oe.id ASC');
        $eventsStmt->execute(['staff', $detailId]);
        $events = $eventsStmt->fetchAll();

        $chatsStmt = $pdo->prepare('SELECT oc.*, CASE WHEN oc.sender_type = ? THEN (SELECT full_name FROM riders WHERE id = oc.sender_id) WHEN oc.sender_type = ? THEN (SELECT full_name FROM staff WHERE id = oc.sender_id) WHEN oc.sender_type = ? THEN (SELECT customer_name FROM orders WHERE id = oc.order_id) ELSE ? END AS sender_name FROM order_chats oc WHERE oc.order_id = ? ORDER BY oc.id ASC');
        $chatsStmt->execute(['rider', 'staff', 'customer', 'Customer', $detailId]);
        $chats = $chatsStmt->fetchAll();

        $gpsStmt = $pdo->prepare('SELECT * FROM order_gps_points WHERE order_id = ? ORDER BY recorded_at ASC');
        $gpsStmt->execute([$detailId]);
        $gps = $gpsStmt->fetchAll();

        $detail = [
            'success' => true,
            'order' => $detailOrder,
            'events' => $events,
            'chats' => $chats,
            'gps' => $gps,
        ];
    }
}

$pageTitle = 'Live Order Board';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Active dispatch board</h2>
        <small class="text-muted">Auto-refresh every 25 seconds · Delay thresholds set by Super Admin</small>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <strong>Filter:</strong>
            <a class="btn btn-outline btn-sm <?= $status === 'all' ? 'btn-primary' : '' ?>" href="<?= APP_URL ?>/orders.php?status=all">All Active</a>
            <a class="btn btn-outline btn-sm <?= $status === 'delayed' ? 'btn-primary' : '' ?>" href="<?= APP_URL ?>/orders.php?status=delayed">⚠ Delayed Only</a>
        </div>
        <div class="kanban">
            <?php foreach ($columns as $key => $label): ?>
                <div class="kanban-col">
                    <h3><?= e($label) ?> (<?= count($grouped[$key]) ?>)</h3>
                    <?php foreach ($grouped[$key] as $order): ?>
                        <div class="order-card <?= !empty($order['is_delayed']) ? 'delayed' : '' ?>">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;flex-wrap:wrap">
                                <strong><?= e($order['order_number']) ?></strong>
                                <div><?= statusBadge((string)$order['display_status']) ?></div>
                            </div>
                            <?php if (!empty($order['is_delayed'])): ?><span class="badge badge-danger" style="margin-top:.3rem">Delay alert · <?= (int)($order['age_minutes'] ?? 0) ?> min</span><?php endif; ?>
                            <div style="margin-top:.4rem"><strong><?= e($order['store_name'] ?? '') ?></strong></div>
                            <small>👤 <?= e($order['customer_name']) ?> · <?= e($order['customer_phone'] ?? '') ?></small><br>
                            <small>🛵 <?= e($order['rider_name'] ?? 'Unassigned') ?> · <?= formatMoney((float)($order['order_total'] ?? 0)) ?></small>
                            <div style="margin-top:.5rem">
                                <a class="btn btn-outline btn-sm" href="?id=<?= (int)$order['id'] ?>#detail">📋 View Evidence</a>
                            </div>
                            <?php if (isPlatformStaff()): ?>
                            <form method="POST" style="margin-top:.6rem">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reassign">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                <select class="form-control" name="rider_id" required style="margin-bottom:.3rem">
                                    <option value="">Reassign rider…</option>
                                    <?php foreach ($riders as $rider): ?>
                                        <option value="<?= (int)$rider['id'] ?>"><?= e($rider['full_name']) ?> (<?= e($rider['duty_status']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control" style="margin-bottom:.3rem" name="reason" placeholder="Flat tire, stopped on map…">
                                <button class="btn btn-primary btn-sm" type="submit">↻ Reassign</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($detail && !empty($detail['success'])): $order = $detail['order']; ?>
<div class="card" id="detail">
    <div class="card-header"><h2>Order evidence · <?= e($order['order_number']) ?></h2></div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <p><strong>Customer:</strong> <?= e($order['customer_name']) ?> · <?= e($order['customer_phone'] ?? '') ?></p>
                <p><strong>Store:</strong> <?= e($order['store_name'] ?? '') ?></p>
                <p><strong>Rider:</strong> <?= e($order['rider_name'] ?? '—') ?></p>
                <p><strong>Total:</strong> <?= formatMoney((float)($order['order_total'] ?? 0)) ?> · <?= e($order['payment_method']) ?></p>
                <p><strong>Status:</strong> <?= statusBadge((string)($order['display_status'] ?? $order['order_status'])) ?></p>
                <p><strong>Age:</strong> <?= (int)($order['age_minutes'] ?? 0) ?> minutes · ETA <?= (int)($order['eta_minutes'] ?? 0) ?> min</p>
                <?php if (!empty($order['photo_path'])): ?>
                    <p><strong>Photo evidence:</strong><br><img src="<?= ADMIN_URL ?>/<?= e($order['photo_path']) ?>" alt="Order photo" style="max-width:100%;max-height:240px;border-radius:8px;border:1px solid var(--cyan-200)"></p>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="margin:0 0 .5rem;font-size:.95rem">Event timeline</h3>
                <?php if (empty($detail['events'])): ?>
                    <p class="text-muted">No events logged yet.</p>
                <?php else: ?>
                    <?php foreach ($detail['events'] as $ev): ?>
                        <div class="chat">
                            <strong><?= e($ev['event_type']) ?></strong>
                            <small>by <?= e($ev['actor_email'] ?? 'system') ?> · <?= formatDateTime($ev['created_at']) ?></small>
                            <div><?= e($ev['notes'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="margin:1.25rem 0 .5rem;font-size:.95rem">💬 Chat history</h3>
        <?php if (empty($detail['chats'])): ?>
            <p class="text-muted">No messages yet.</p>
        <?php else: ?>
            <?php foreach ($detail['chats'] as $chat): ?>
                <div class="chat"><strong><?= e($chat['sender_name']) ?></strong> <small><?= e($chat['sender_role']) ?> · <?= formatDateTime($chat['sent_at']) ?></small><div><?= e($chat['message']) ?></div></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin:1.25rem 0 .5rem;font-size:.95rem">📍 GPS track points</h3>
        <?php if (empty($detail['gps'])): ?>
            <p class="text-muted">No GPS points recorded yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Time</th><th>Latitude</th><th>Longitude</th></tr></thead>
                    <tbody>
                    <?php foreach ($detail['gps'] as $point): ?>
                        <tr><td><?= formatDateTime($point['recorded_at']) ?></td><td><?= e((string)$point['latitude']) ?></td><td><?= e((string)$point['longitude']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<meta http-equiv="refresh" content="25">
<?php include __DIR__ . '/includes/footer.php'; ?>
