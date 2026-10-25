<?php

declare(strict_types=1);

require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/migrate_ops.php';
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
include __DIR__.'/includes/header.php';

$settings = $pdo->query('SELECT * FROM platform_settings ORDER BY setting_key')->fetchAll();
$settingHelp = [
    'bulk_discount_percent' => 'Discount applied when an order qualifies for the bulk ordering rule.',
    'bulk_min_orders' => 'Number of orders required before the bulk discount is applied.',
    'delay_threshold_minutes' => 'Minutes after which an active order is flagged as delayed.',
    'global_delivery_fee' => 'Default delivery charge shown to customers at checkout.',
    'max_refund_amount' => 'Maximum refund amount staff can issue without escalation.',
];
$settingConstraints = [
    'bulk_discount_percent' => ['type' => 'number', 'min' => '0', 'max' => '100', 'step' => '0.01', 'suffix' => '%'],
    'bulk_min_orders' => ['type' => 'number', 'min' => '1', 'step' => '1', 'suffix' => 'orders'],
    'delay_threshold_minutes' => ['type' => 'number', 'min' => '1', 'step' => '1', 'suffix' => 'min'],
    'global_delivery_fee' => ['type' => 'number', 'min' => '0', 'step' => '0.01', 'suffix' => 'PHP'],
    'max_refund_amount' => ['type' => 'number', 'min' => '0', 'step' => '0.01', 'suffix' => 'PHP'],
];
?>

<?php if ($msg = flash('success')) { ?>
    <div class="alert alert-success settings-alert" role="status" aria-live="polite"><?= e($msg) ?></div>
<?php } ?>

<section class="card settings-card" aria-labelledby="settings-heading">
    <div class="card-header settings-card-header">
        <div>
            <h2 id="settings-heading"><i data-lucide="sliders-horizontal" aria-hidden="true"></i> Platform rules</h2>
            <p>Set the defaults used across customer orders and staff operations.</p>
        </div>
        <span class="settings-badge"><i data-lucide="shield-check" aria-hidden="true"></i> Admin controlled</span>
    </div>
    <div class="card-body settings-card-body">
        <div class="settings-notice"><i data-lucide="info" aria-hidden="true"></i><p>Management staff can view these rules through the API. Only a super administrator can change them.</p></div>
        <form method="POST" class="settings-form">
            <?= csrfField() ?>
            <div class="settings-grid">
                <?php foreach ($settings as $row) { ?>
                    <?php if (! in_array($row['setting_key'], ['global_delivery_fee', 'bulk_min_orders', 'bulk_discount_percent', 'max_refund_amount', 'delay_threshold_minutes'], true)) {
                        continue;
                    }
                    $key = (string) $row['setting_key'];
                    $constraints = $settingConstraints[$key] ?? ['type' => 'text', 'suffix' => ''];
                    $helpId = 'help-'.preg_replace('/[^a-z0-9_-]/i', '-', $key);
                    ?>
                    <div class="setting-field">
                        <label for="setting-<?= e($key) ?>"><?= e($row['setting_label']) ?></label>
                        <p id="<?= e($helpId) ?>" class="setting-help"><?= e($settingHelp[$key] ?? '') ?></p>
                        <div class="setting-input-wrap">
                            <input
                                id="setting-<?= e($key) ?>"
                                class="form-control"
                                type="<?= e($constraints['type']) ?>"
                                name="<?= e($key) ?>"
                                value="<?= e($row['setting_value']) ?>"
                                min="<?= e($constraints['min'] ?? '') ?>"
                                <?= isset($constraints['max']) ? 'max="'.e($constraints['max']).'"' : '' ?>
                                step="<?= e($constraints['step'] ?? 'any') ?>"
                                inputmode="decimal"
                                aria-describedby="<?= e($helpId) ?>"
                                required
                            >
                            <span><?= e($constraints['suffix'] ?? '') ?></span>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="settings-actions">
                <p><i data-lucide="lock-keyhole" aria-hidden="true"></i> Changes take effect for new transactions after saving.</p>
                <button class="btn btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i> Save changes</button>
            </div>
        </form>
    </div>
</section>

<section class="card settings-api-card" aria-labelledby="api-heading">
    <div class="card-header settings-api-header">
        <div class="settings-api-icon"><i data-lucide="radio-tower" aria-hidden="true"></i></div>
        <div><h2 id="api-heading">Management API</h2><p>Connection used by the Motobook Management panel.</p></div>
    </div>
    <div class="card-body settings-api-body">
        <div><span class="settings-api-label">Health check endpoint</span><code><?= e((isset($_SERVER['HTTP_HOST']) ? ('http://'.$_SERVER['HTTP_HOST']) : '').APP_URL) ?>/api/v1/index.php?route=health</code></div>
        <div><span class="settings-api-label">Management panel</span><code>/A-management</code></div>
    </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
