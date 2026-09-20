<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/migrate_ops.php';
requireLogin();

$pdo = getDBConnection();
runOperationsMigration($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    foreach (['global_delivery_fee', 'bulk_min_orders', 'bulk_discount_percent', 'max_refund_amount', 'delay_threshold_minutes'] as $key) {
        if (isset($_POST[$key])) {
            $stmt = $pdo->prepare('UPDATE platform_settings SET setting_value = ? WHERE setting_key = ?');
            $stmt->execute([trim((string) $_POST[$key]), $key]);
        }
    }
    flash('success', 'Global rules updated. Management staff see these as read-only via API.');
    redirect('/settings.php');
}

$pageTitle = 'Global Settings';
include __DIR__ . '/includes/header.php';

$settings = $pdo->query('SELECT * FROM platform_settings ORDER BY setting_key')->fetchAll();
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2>Platform Rules (Super Admin Full Control)</h2></div>
    <div class="card-body">
        <p class="text-muted" style="margin-bottom:1rem;">Management staff can view these values through the Super Admin API but cannot change them.</p>
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-row">
                <?php foreach ($settings as $row): ?>
                    <?php if (!in_array($row['setting_key'], ['global_delivery_fee', 'bulk_min_orders', 'bulk_discount_percent', 'max_refund_amount', 'delay_threshold_minutes'], true)) {
                        continue;
                    } ?>
                    <div class="form-group">
                        <label><?= e($row['setting_label']) ?></label>
                        <input class="form-control" name="<?= e($row['setting_key']) ?>" value="<?= e($row['setting_value']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary" type="submit">Save Global Rules</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Management API</h2></div>
    <div class="card-body">
        <p>Endpoint: <code><?= e((isset($_SERVER['HTTP_HOST']) ? ('http://' . $_SERVER['HTTP_HOST']) : '') . APP_URL) ?>/api/v1/index.php?route=health</code></p>
        <p>The Motobook Management panel at <code>/A-management</code> authenticates and transacts only through this API.</p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
