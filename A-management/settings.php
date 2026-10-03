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
        <h2>Global Platform Rules</h2>
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
                            <small class="text-muted" style="margin-top:.3rem;display:block"><span class="badge badge-muted">Locked</span> &mdash; Super Admin only (Global Settings panel)</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr style="margin:1.5rem 0;border:1px solid var(--cyan-100)">

        <h2 style="font-size:1rem;color:var(--cyan-900);margin-bottom:.75rem">Responsibility Matrix</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Operational task</th><th>Super Admin</th><th>Motobook Management</th><th>Store Manager</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Global delivery fees &amp; bulking rules</strong></td><td><span class="badge badge-success">Full Control</span></td><td><span class="badge badge-info">Read Only</span></td><td><span class="badge badge-info">Read Only</span></td></tr>
                    <tr><td><strong>Creating / removing staff accounts</strong></td><td><span class="badge badge-success">Full Control</span></td><td><span class="badge badge-danger">No Access</span></td><td><span class="badge badge-danger">No Access</span></td></tr>
                    <tr><td><strong>Daily cash collection / POS remittance</strong></td><td><span class="badge badge-info">Final Audit &amp; Reports</span></td><td><span class="badge badge-success">Active Processing</span></td><td><span class="badge badge-danger">No Access</span></td></tr>
                    <tr><td><strong>Order reassignment &amp; live tracking</strong></td><td><span class="badge badge-info">Executive View</span></td><td><span class="badge badge-success">Active Control</span></td><td><span class="badge badge-info">Store-Scoped View</span></td></tr>
                    <tr><td><strong>Customer complaints &amp; refunds</strong></td><td><span class="badge badge-info">High-Level Audit</span></td><td><span class="badge badge-warning">Daily Resolution</span></td><td><span class="badge badge-muted">Reference Only</span></td></tr>
                    <tr><td><strong>Merchant menu &amp; item availability</strong></td><td><span class="badge badge-info">Platform Oversight</span></td><td><span class="badge badge-info">Assisted Support</span></td><td><span class="badge badge-success">Own Store Control</span></td></tr>
                    <tr><td><strong>Store pause / busy override</strong></td><td><span class="badge badge-success">Any Store</span></td><td><span class="badge badge-success">Any Store (Support)</span></td><td><span class="badge badge-success">Own Store Only</span></td></tr>
                    <tr><td><strong>Store promo approvals</strong></td><td><span class="badge badge-info">Audit Trail</span></td><td><span class="badge badge-success">Approves / Rejects</span></td><td><span class="badge badge-muted">Submits Requests</span></td></tr>
                    <tr><td><strong>Banner graphics upload</strong></td><td><span class="badge badge-success">Full Access</span></td><td><span class="badge badge-success">Executes Approved Art</span></td><td><span class="badge badge-danger">No Access</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
