<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $storeId = (int) ($_POST['store_id'] ?? 0);

    if ($action === 'pause' && $storeId) {
        if (isStoreStaff()) {
            $myStore = currentUserStoreId() ?? 0;
            if ($myStore !== $storeId) {
                flash('error', 'You can only pause your own store.');
                header('Location: ' . APP_URL . '/stores.php');
                exit;
            }
        }
        $status = $_POST['status'] ?? 'paused';
        $ok = setStoreStatus($storeId, $status);
        flash($ok ? 'success' : 'error', $ok ? 'Store status updated.' : 'Store pause failed.');
        header('Location: ' . APP_URL . '/stores.php');
        exit;
    }

    if ($action === 'onboard' && isPlatformStaff() && $storeId) {
        $fields = ['store_name', 'branch_address', 'contact_phone', 'contact_email', 'operating_hours', 'owner_name', 'owner_email'];
        $body = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f]) && trim((string) $_POST[$f]) !== '') {
                $body[$f] = trim((string) $_POST[$f]);
            }
        }
        $ownerPassword = trim((string) ($_POST['owner_password'] ?? ''));
        if ($ownerPassword !== '') {
            $body['owner_password'] = $ownerPassword;
        }
        $ok = updateStoreOnboarding($storeId, $body);
        flash($ok ? 'success' : 'error', $ok ? 'Store profile updated.' : 'Onboarding update failed.');
        header('Location: ' . APP_URL . '/stores.php');
        exit;
    }
}

$stores = fetchStores();

$pageTitle = isStoreStaff() ? 'My Store Status' : 'Partner Store Support';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?= isStoreStaff() ? 'Your Store Information' : 'Partner Stores Directory' ?></h2>
        <?php if (isPlatformStaff()): ?>
            <small class="text-muted">Help store owners with onboarding, pausing during rush, and profile setup.</small>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Store</th><th>Category</th><th>Branch</th><th>Contact</th><th>Hours</th><th>Status</th><th>Owner</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $s): ?>
                        <tr>
                            <td><strong><?= e($s['store_name']) ?></strong><br><small>ID #<?= (int) $s['id'] ?> · <?= (int) ($s['total_orders'] ?? 0) ?> orders</small></td>
                            <td><?= e($s['category_name'] ?? '—') ?></td>
                            <td><?= e($s['branch_address']) ?></td>
                            <td><?= e($s['contact_phone'] ?? '—') ?><br><small><?= e($s['contact_email'] ?? '') ?></small></td>
                            <td><?= e($s['operating_hours'] ?? '—') ?></td>
                            <td><?= statusBadge((string) $s['status']) ?></td>
                            <td><?= e($s['owner_name'] ?? '—') ?><br><small><?= e($s['owner_email'] ?? '') ?></small></td>
                            <td style="min-width:220px">
                                <form method="POST" style="display:inline;margin-right:.3rem" onsubmit="return confirm('Change store status?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="pause">
                                    <input type="hidden" name="store_id" value="<?= (int) $s['id'] ?>">
                                    <select class="form-control" name="status" style="width:110px;display:inline;padding:.25rem .4rem;font-size:.75rem;margin-right:.3rem">
                                        <option value="open" <?= ($s['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                                        <option value="paused" <?= ($s['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Busy/Paused</option>
                                        <option value="offline" <?= ($s['status'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline</option>
                                    </select>
                                    <button class="btn btn-warning btn-sm" type="submit">Set</button>
                                </form>
                                <?php if (isPlatformStaff()): ?>
                                    <button type="button" class="btn btn-outline btn-sm onboard-btn" data-store="<?= e(json_encode([
                                        'id' => (int) $s['id'],
                                        'store_name' => (string) $s['store_name'],
                                        'branch_address' => (string) $s['branch_address'],
                                        'contact_phone' => (string) ($s['contact_phone'] ?? ''),
                                        'contact_email' => (string) ($s['contact_email'] ?? ''),
                                        'operating_hours' => (string) ($s['operating_hours'] ?? ''),
                                        'owner_name' => (string) ($s['owner_name'] ?? ''),
                                        'owner_email' => (string) ($s['owner_email'] ?? ''),
                                    ], JSON_UNESCAPED_UNICODE)) ?>">Onboard</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (isPlatformStaff()): ?>
<div class="card" id="onboardCard" style="display:none">
    <div class="card-header">
        <h2>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            Store Onboarding Assistance
        </h2>
        <button class="btn btn-outline btn-sm" onclick="document.getElementById('onboardCard').style.display='none'">Close</button>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Fill in missing profile details for a new physical store (Jollibee, milk tea shops, hardware, etc.). Uploading permits and initial menu can also be coordinated here. Global commission rate remains with Super Admin.
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="onboard">
            <input type="hidden" name="store_id" id="onboardStoreId" value="0">
            <div class="form-row">
                <div class="form-group"><label>Store name</label><input class="form-control" name="store_name" id="o_store_name"></div>
                <div class="form-group"><label>Operating hours</label><input class="form-control" name="operating_hours" id="o_hours" placeholder="08:00 - 22:00"></div>
            </div>
            <div class="form-group"><label>Branch address</label><input class="form-control" name="branch_address" id="o_addr"></div>
            <div class="form-row">
                <div class="form-group"><label>Contact phone</label><input class="form-control" name="contact_phone" id="o_phone"></div>
                <div class="form-group"><label>Contact email</label><input class="form-control" name="contact_email" id="o_email" type="email"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Owner name</label><input class="form-control" name="owner_name" id="o_owner"></div>
                <div class="form-group"><label>Owner email</label><input class="form-control" name="owner_email" id="o_owner_email" type="email"></div>
            </div>
            <div class="form-group">
                <label>Set / reset owner login password (leave blank to keep existing)</label>
                <input class="form-control" name="owner_password" type="password" placeholder="Store owner will use this email + password to access this panel.">
            </div>
            <button class="btn btn-primary" type="submit">Save store profile</button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.onboard-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const store = JSON.parse(this.getAttribute('data-store') || '{}');
        document.getElementById('onboardCard').style.display = 'block';
        document.getElementById('onboardStoreId').value = store.id || 0;
        document.getElementById('o_store_name').value = store.store_name || '';
        document.getElementById('o_addr').value = store.branch_address || '';
        document.getElementById('o_phone').value = store.contact_phone || '';
        document.getElementById('o_email').value = store.contact_email || '';
        document.getElementById('o_hours').value = store.operating_hours || '';
        document.getElementById('o_owner').value = store.owner_name || '';
        document.getElementById('o_owner_email').value = store.owner_email || '';
        location.hash = 'onboardCard';
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
