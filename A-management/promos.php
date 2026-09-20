<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

$user = currentUser();
$isStore = isStoreStaff();
$myStoreId = (int) ($user['store_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit' && $isStore) {
        $fields = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'discount_percent' => (float) ($_POST['discount_percent'] ?? 0),
        ];
        $ok = submitPromo($fields);
        flash($ok ? 'success' : 'error', $ok ? 'Promo submitted for approval.' : 'Promo submission failed.');
        header('Location: ' . APP_URL . '/promos.php');
        exit;
    }

    if ($action === 'review' && isPlatformStaff()) {
        $id = (int) $_POST['promo_id'];
        $status = $_POST['status'] ?? 'rejected';
        $notes = trim($_POST['notes'] ?? '');
        $ok = reviewPromo($id, $status, $notes);
        flash($ok ? 'success' : 'error', $ok ? 'Promo review recorded.' : 'Promo review failed.');
        header('Location: ' . APP_URL . '/promos.php');
        exit;
    }
}

$promos = fetchPromos();

$pageTitle = $isStore ? 'Submit Store Promo' : 'Store Promo Approvals';
include __DIR__ . '/includes/header.php';
?>

<?php if ($isStore): ?>
<div class="card">
    <div class="card-header"><h2>📮 Submit promo for approval</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            Motobook management staff will review discount requests before they go live on the app. This prevents unapproved discounts from affecting platform commissions.
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit">
            <div class="form-group"><label>Promo title</label><input class="form-control" name="title" required placeholder="Chickenjoy Tuesday 20% Off"></div>
            <div class="form-row">
                <div class="form-group"><label>Discount (%)</label><input class="form-control" name="discount_percent" type="number" step="0.01" min="0" max="100" required placeholder="20"></div>
                <div class="form-group"><label>Validity period</label><input class="form-control" type="text" id="validityText" placeholder="e.g. Sept 1-30, 2026"></div>
            </div>
            <div class="form-group">
                <label>Full mechanics, terms & description (required)</label>
                <textarea class="form-control" id="fullDescription" name="description" rows="3" required placeholder="Include: validity dates, SKU exclusions, minimum purchase, how to redeem…"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">📤 Submit for Motobook approval</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>📋 <?= $isStore ? 'My submitted promos' : 'Promo approval queue' ?></h2>
        <?php if (isPlatformStaff()): ?>
            <small class="text-muted">Approve only after verifying mechanics won't break global commission rules.</small>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Submitted</th><th>Store</th><th>Promo</th><th>Discount</th><th>Mechanics</th>
                        <th>Status</th><th>Submitted by</th><th><?= isPlatformStaff() ? 'Review' : 'Reviewer notes' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promos as $p): ?>
                        <tr>
                            <td><?= formatDateTime($p['created_at']) ?></td>
                            <td><strong><?= e($p['store_name']) ?></strong></td>
                            <td><strong><?= e($p['title']) ?></strong></td>
                            <td><strong style="color:var(--danger)"><?= number_format((float)$p['discount_percent'], 2) ?>%</strong></td>
                            <td style="max-width:260px"><?= e($p['description'] ?? '') ?></td>
                            <td><?= statusBadge((string) $p['status']) ?></td>
                            <td><?= e($p['submitted_by_name'] ?? '') ?></td>
                            <td>
                                <?php if (isPlatformStaff() && ($p['status'] ?? '') === 'pending'): ?>
                                    <button class="btn btn-outline btn-sm" onclick="showReview(<?= (int)$p['id'] ?>, <?= json_encode(e($p['title'])) ?>)">Review</button>
                                <?php else: ?>
                                    <?= $p['review_notes'] ? '<small class="text-muted">'.e($p['review_notes']).'</small>' : '<span class="text-muted">—</span>' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($promos)): ?>
                        <tr><td colspan="8" class="text-muted">No promos yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (isPlatformStaff()): ?>
<div class="card" id="reviewCard" style="display:none">
    <div class="card-header">
        <h2>✅ Approve / Reject promo request</h2>
        <button class="btn btn-outline btn-sm" onclick="document.getElementById('reviewCard').style.display='none'">Close</button>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="promo_id" id="reviewPromoId" value="0">
            <p><strong>Promo:</strong> <span id="reviewTitle"></span></p>
            <div class="form-group">
                <label>Decision</label>
                <select class="form-control" name="status" required>
                    <option value="approved">✅ Approve — goes live on app</option>
                    <option value="rejected">❌ Reject — send notes back</option>
                </select>
            </div>
            <div class="form-group">
                <label>Review notes (shared with store manager)</label>
                <textarea class="form-control" name="notes" rows="3" required placeholder="Approved with mechanics X, or Rejected because global cap…"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Submit review decision</button>
        </form>
    </div>
</div>
<script>
function showReview(id, title) {
    document.getElementById('reviewCard').style.display = 'block';
    document.getElementById('reviewPromoId').value = id;
    document.getElementById('reviewTitle').textContent = title;
    location.hash = 'reviewCard';
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
