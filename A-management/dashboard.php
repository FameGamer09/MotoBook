<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

$kpis = dashboardKpis();
$orders = fetchOrders('all');
$pageTitle = 'Operations Dashboard';
include __DIR__ . '/includes/header.php';

$settings = [
    'global_delivery_fee' => settingValue('global_delivery_fee', '49.00'),
    'bulk_min_orders' => settingValue('bulk_min_orders', '3'),
    'bulk_discount_percent' => settingValue('bulk_discount_percent', '10'),
    'max_refund_amount' => settingValue('max_refund_amount', '500.00'),
    'delay_threshold_minutes' => (string)delayThreshold(),
];
$delayed = array_values(array_filter($orders, fn($o) => !empty($o['is_delayed'])));
$recent = array_slice($orders, 0, 6);
?>

<div class="stats-grid">
    <div class="stat-card"><div class="label"><span class="live-dot"></span>Active orders</div><div class="value"><?= (int)($kpis['active_orders'] ?? 0) ?></div></div>
    <div class="stat-card"><div class="label">Delay alerts</div><div class="value" style="color:var(--danger)"><?= (int)($kpis['delayed_orders'] ?? 0) ?></div></div>
    <div class="stat-card"><div class="label">Open tickets</div><div class="value"><?= (int)($kpis['open_tickets'] ?? 0) ?></div></div>
    <?php if (isPlatformStaff()): ?>
        <div class="stat-card"><div class="label">Pending remittance</div><div class="value"><?= (int)($kpis['pending_remittance'] ?? 0) ?></div></div>
        <div class="stat-card"><div class="label">Riders on shift</div><div class="value"><?= (int)($kpis['riders_on_shift'] ?? 0) ?></div></div>
    <?php endif; ?>
    <div class="stat-card"><div class="label">Pending promos</div><div class="value"><?= (int)($kpis['pending_promos'] ?? 0) ?></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Delay alerts</h2>
            <a class="btn btn-outline btn-sm" href="<?= APP_URL ?>/orders.php?status=delayed">Open board</a>
        </div>
        <div class="card-body">
            <?php if (empty($delayed)): ?>
                <p class="text-muted">No delayed orders. Threshold is <?= e($settings['delay_threshold_minutes']) ?> minutes.</p>
            <?php else: ?>
                <?php foreach (array_slice($delayed, 0, 5) as $order): ?>
                    <div class="order-card delayed">
                        <strong><?= e($order['order_number']) ?></strong>
                        <?= statusBadge((string)($order['display_status'] ?? 'delayed')) ?>
                        <div><small><?= e($order['store_name'] ?? '') ?> · <?= e($order['rider_name'] ?? 'Unassigned') ?> · <?= (int)($order['age_minutes'] ?? 0) ?> min</small></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Platform rules (read-only)</h2></div>
        <div class="card-body">
            <div class="alert alert-info">Global delivery fees, bulking rules, and refund limits are configured by the Super Admin.</div>
            <p><strong>Global delivery fee:</strong> <?= formatMoney((float)$settings['global_delivery_fee']) ?></p>
            <p><strong>Bulk min orders:</strong> <?= e($settings['bulk_min_orders']) ?> (<?= e($settings['bulk_discount_percent']) ?>% discount)</p>
            <p><strong>Max refund limit:</strong> <?= formatMoney((float)$settings['max_refund_amount']) ?></p>
            <p><strong>Delay threshold:</strong> <?= e($settings['delay_threshold_minutes']) ?> minutes</p>
            <?php if (isPlatformStaff()): ?>
                <p class="text-muted">Staff accounts are managed in Super Admin.</p>
            <?php else: ?>
                <p class="text-muted">Watch orders, keep the menu accurate, and pause the store if the kitchen is overloaded.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Live order snapshot</h2>
        <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/orders.php">Full dispatch board</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Rider</th><th>Age</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $order): ?>
                        <tr>
                            <td><strong><?= e($order['order_number']) ?></strong></td>
                            <td><?= e($order['store_name'] ?? '') ?></td>
                            <td><?= e($order['customer_name'] ?? '') ?></td>
                            <td><?= e($order['rider_name'] ?? 'Unassigned') ?></td>
                            <td><?= (int)($order['age_minutes'] ?? 0) ?> min</td>
                            <td><?= statusBadge((string)($order['display_status'] ?? $order['order_status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="6" class="text-muted">No active orders.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
