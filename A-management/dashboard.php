<?php

declare(strict_types=1);

require_once __DIR__.'/includes/functions.php';
requireOpsLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isPlatformStaff()) {
        http_response_code(403);
        exit('Only Motobook management staff can change the delivery fee.');
    }

    if (! verifyCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please try again.');
        header('Location: '.APP_URL.'/dashboard.php');
        exit;
    }

    $settingRules = [
        'global_delivery_fee' => ['label' => 'delivery fee', 'pattern' => '/^\d{1,6}(?:\.\d{1,2})?$/D', 'min' => 0, 'max' => 999999.99, 'integer' => false],
        'bulk_min_orders' => ['label' => 'minimum bulk orders', 'pattern' => '/^\d{1,6}$/D', 'min' => 1, 'max' => 999999, 'integer' => true],
        'bulk_discount_percent' => ['label' => 'bulk discount percentage', 'pattern' => '/^\d{1,3}(?:\.\d{1,2})?$/D', 'min' => 0, 'max' => 100, 'integer' => false],
        'max_refund_amount' => ['label' => 'maximum refund amount', 'pattern' => '/^\d{1,6}(?:\.\d{1,2})?$/D', 'min' => 0, 'max' => 999999.99, 'integer' => false],
        'delay_threshold_minutes' => ['label' => 'delay threshold', 'pattern' => '/^\d{1,6}$/D', 'min' => 5, 'max' => 999999, 'integer' => true],
    ];
    $settingsToSave = [];
    $invalidSettings = [];

    foreach ($settingRules as $key => $rule) {
        $value = trim((string) ($_POST[$key] ?? ''));
        $numericValue = (float) $value;
        if (! preg_match($rule['pattern'], $value) || $numericValue < $rule['min'] || $numericValue > $rule['max']) {
            $invalidSettings[] = $rule['label'];

            continue;
        }

        $settingsToSave[$key] = $rule['integer'] ? (string) (int) $value : number_format($numericValue, 2, '.', '');
    }

    if ($invalidSettings) {
        flash('error', 'Please check these values: '.implode(', ', $invalidSettings).'.');
    } elseif (! updateManagementRules($settingsToSave)) {
        flash('error', 'The platform rules could not be saved. Please try again.');
    } else {
        flash('success', 'Platform rules saved.');
    }

    header('Location: '.APP_URL.'/dashboard.php');
    exit;
}

$kpis = dashboardKpis();
$orders = fetchOrders('all');
$pageTitle = 'Operations Dashboard';
include __DIR__.'/includes/header.php';

$settings = [
    'global_delivery_fee' => settingValue('global_delivery_fee', '49.00'),
    'bulk_min_orders' => settingValue('bulk_min_orders', '3'),
    'bulk_discount_percent' => settingValue('bulk_discount_percent', '10'),
    'max_refund_amount' => settingValue('max_refund_amount', '500.00'),
    'delay_threshold_minutes' => (string) delayThreshold(),
];
$delayed = array_values(array_filter($orders, fn ($o) => ! empty($o['is_delayed'])));
$recent = array_slice($orders, 0, 6);
?>

<div class="stats-grid">
    <div class="stat-card"><div class="label"><span class="live-dot"></span>Active orders</div><div class="value"><?= (int) ($kpis['active_orders'] ?? 0) ?></div></div>
    <div class="stat-card"><div class="label">Delay alerts</div><div class="value" style="color:var(--danger)"><?= (int) ($kpis['delayed_orders'] ?? 0) ?></div></div>
    <div class="stat-card"><div class="label">Open tickets</div><div class="value"><?= (int) ($kpis['open_tickets'] ?? 0) ?></div></div>
    <?php if (isPlatformStaff()) { ?>
        <div class="stat-card"><div class="label">Pending remittance</div><div class="value"><?= (int) ($kpis['pending_remittance'] ?? 0) ?></div></div>
        <div class="stat-card"><div class="label">Riders on shift</div><div class="value"><?= (int) ($kpis['riders_on_shift'] ?? 0) ?></div></div>
    <?php } ?>
    <div class="stat-card"><div class="label">Pending promos</div><div class="value"><?= (int) ($kpis['pending_promos'] ?? 0) ?></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Delay alerts</h2>
            <a class="btn btn-outline btn-sm" href="<?= APP_URL ?>/orders.php?status=delayed">Open board</a>
        </div>
        <div class="card-body">
            <?php if (empty($delayed)) { ?>
                <p class="text-muted">No delayed orders. Threshold is <?= e($settings['delay_threshold_minutes']) ?> minutes.</p>
            <?php } else { ?>
                <?php foreach (array_slice($delayed, 0, 5) as $order) { ?>
                    <div class="order-card delayed">
                        <strong><?= e($order['order_number']) ?></strong>
                        <?= statusBadge((string) ($order['display_status'] ?? 'delayed')) ?>
                        <div><small><?= e($order['store_name'] ?? '') ?> · <?= e($order['rider_name'] ?? 'Unassigned') ?> · <?= (int) ($order['age_minutes'] ?? 0) ?> min</small></div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Platform rules</h2></div>
        <div class="card-body">
            <?php if (isPlatformStaff()) { ?>
                <div class="alert alert-info">Save shared platform rules here. Delivery fees, refund limits, and delay alerts use these values.</div>
                <form method="POST" action="<?= APP_URL ?>/dashboard.php" class="form-row">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="deliveryFee">Global delivery fee (₱)</label>
                        <input class="form-control" id="deliveryFee" name="global_delivery_fee" type="number" min="0" max="999999.99" step="0.01" value="<?= e($settings['global_delivery_fee']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="bulkMinOrders">Minimum bulk orders</label>
                        <input class="form-control" id="bulkMinOrders" name="bulk_min_orders" type="number" min="1" max="999999" step="1" value="<?= e($settings['bulk_min_orders']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="bulkDiscountPercent">Bulk discount (%)</label>
                        <input class="form-control" id="bulkDiscountPercent" name="bulk_discount_percent" type="number" min="0" max="100" step="0.01" value="<?= e($settings['bulk_discount_percent']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="maxRefundAmount">Maximum refund (₱)</label>
                        <input class="form-control" id="maxRefundAmount" name="max_refund_amount" type="number" min="0" max="999999.99" step="0.01" value="<?= e($settings['max_refund_amount']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="delayThresholdMinutes">Delay threshold (minutes)</label>
                        <input class="form-control" id="delayThresholdMinutes" name="delay_threshold_minutes" type="number" min="5" max="999999" step="1" value="<?= e($settings['delay_threshold_minutes']) ?>" required>
                    </div>
                    <div class="form-group" style="align-self:end">
                        <button class="btn btn-primary" type="submit">Save all rules</button>
                    </div>
                </form>
            <?php } else { ?>
                <div class="alert alert-info">Global delivery fees, bulking rules, refund limits, and delay thresholds are configured by Motobook management staff.</div>
                <p><strong>Global delivery fee:</strong> <?= formatMoney((float) $settings['global_delivery_fee']) ?></p>
            <?php } ?>
            <?php if (! isPlatformStaff()) { ?>
                <p><strong>Bulk min orders:</strong> <?= e($settings['bulk_min_orders']) ?> (<?= e($settings['bulk_discount_percent']) ?>% discount)</p>
                <p><strong>Max refund limit:</strong> <?= formatMoney((float) $settings['max_refund_amount']) ?></p>
                <p><strong>Delay threshold:</strong> <?= e($settings['delay_threshold_minutes']) ?> minutes</p>
            <?php } ?>
            <?php if (isPlatformStaff()) { ?>
                <p class="text-muted">Staff accounts are managed in Super Admin.</p>
            <?php } else { ?>
                <p class="text-muted">Watch orders, keep the menu accurate, and pause the store if the kitchen is overloaded.</p>
            <?php } ?>
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
                    <?php foreach ($recent as $order) { ?>
                        <tr>
                            <td><strong><?= e($order['order_number']) ?></strong></td>
                            <td><?= e($order['store_name'] ?? '') ?></td>
                            <td><?= e($order['customer_name'] ?? '') ?></td>
                            <td><?= e($order['rider_name'] ?? 'Unassigned') ?></td>
                            <td><?= (int) ($order['age_minutes'] ?? 0) ?> min</td>
                            <td><?= statusBadge((string) ($order['display_status'] ?? $order['order_status'])) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($recent)) { ?>
                        <tr><td colspan="6" class="text-muted">No active orders.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
