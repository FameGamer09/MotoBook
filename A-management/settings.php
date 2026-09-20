<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

$settingsMap = allSettings();
$settings = array_values($settingsMap);

$userType = currentUser()['type'] ?? '';
$readOnly = $userType !== 'super_admin';

$pageTitle = 'Platform Rules (Read-Only)';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>🛡️ Global delivery fees & bulking rules</h2>
        <span class="badge badge-info">Read-only for Management</span>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <?php if ($readOnly): ?>
                <strong>Only Super Admin can change these values.</strong>
                Management staff have daily hands-on control (orders, remittance, tickets) but cannot adjust global platform rules.
                To make changes, sign in at <a href="<?= ADMIN_URL ?>/login.php"><?= ADMIN_URL ?>/login.php</a>.
            <?php else: ?>
                You are signed in as <strong>Super Admin</strong>. Changes made here will affect all client apps and rider fees.
            <?php endif; ?>
        </div>
        <div class="grid-2">
            <?php foreach ($settings as $s): ?>
                <div class="card" style="margin:0;border-left:4px solid var(--cyan-500)">
                    <div class="card-body">
                        <label style="font-weight:700;color:var(--cyan-800)"><?= e($s['setting_label']) ?></label>
                        <div style="margin-top:.35rem">
                            <input class="form-control"
                                value="<?= e($s['setting_value']) ?>"
                                <?= (!empty($s['is_locked_for_staff']) && $readOnly) ? 'readonly disabled style="background:var(--gray-100)"' : '' ?>>
                        </div>
                        <?php if (!empty($s['is_locked_for_staff']) && $readOnly): ?>
                            <small class="text-muted" style="margin-top:.3rem;display:block">🔒 Locked — Super Admin only (Global Settings panel)</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr style="margin:1.5rem 0;border:1px solid var(--cyan-100)">

        <h2 style="font-size:1rem;color:var(--cyan-900);margin-bottom:.75rem">📖 Responsibility matrix</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Operational task</th><th>Super Admin</th><th>Motobook Management</th><th>Store Manager</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Global delivery fees & bulking rules</strong></td><td>✅ Full Control</td><td>👁️ Read Only</td><td>👁️ Read Only</td></tr>
                    <tr><td><strong>Creating / removing staff accounts</strong></td><td>✅ Full Control</td><td>🚫 No Access</td><td>🚫 No Access</td></tr>
                    <tr><td><strong>Daily cash collection / POS remittance</strong></td><td>📊 Final audit & reports</td><td>💵 Daily hands-on processing</td><td>🚫 No Access</td></tr>
                    <tr><td><strong>Order reassignment & live tracking</strong></td><td>👁️ Executive view</td><td>🎛️ Active operational control</td><td>👁️ Their store only</td></tr>
                    <tr><td><strong>Customer complaints & refunds</strong></td><td>📊 High-level audit</td><td>⚖️ Daily ticket resolution (within cap)</td><td>📮 Reference only</td></tr>
                    <tr><td><strong>Merchant menu & item availability</strong></td><td>👁️ Oversees platform</td><td>🤝 Assists store managers</td><td>✅ Toggles own store</td></tr>
                    <tr><td><strong>Store pause / busy override</strong></td><td>✅ Any store</td><td>✅ Any store (phone-in help)</td><td>✅ Own store only</td></tr>
                    <tr><td><strong>Store promo approvals</strong></td><td>👁️ Audit trail</td><td>✅ Approves / rejects</td><td>📮 Submits requests</td></tr>
                    <tr><td><strong>Banner graphics upload</strong></td><td>✅ Full access</td><td>✅ Executes approved art</td><td>🚫 No Access</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
