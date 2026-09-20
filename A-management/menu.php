<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

$user = currentUser();
$myStoreId = currentUserStoreId() ?? 0;
$storeId = (int) ($_GET['store_id'] ?? $myStoreId);

if (isStoreStaff() && $storeId !== $myStoreId) {
    flash('error', 'You can only manage your own store menu.');
    header('Location: ' . APP_URL . '/menu.php');
    exit;
}

if (isStoreStaff() && !$storeId) {
    flash('error', 'Your account is not assigned to a store.');
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    if (($_POST['action'] ?? '') === 'toggle' && is_numeric($_POST['item_id'] ?? '')) {
        $ok = toggleMenuItem((int) $_POST['item_id']);
        flash($ok ? 'success' : 'error', $ok ? 'Item availability toggled.' : 'Toggle failed.');
        header('Location: ' . APP_URL . '/menu.php' . (isStoreStaff() ? '' : '?store_id=' . $storeId));
        exit;
    }
    if (($_POST['action'] ?? '') === 'add') {
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Main');
        $price = (float) ($_POST['price'] ?? 0);
        $ok = $itemName !== '' && addMenuItem($storeId, $itemName, $category, $price);
        flash($ok ? 'success' : 'error', $ok ? 'Menu item added.' : 'Add item failed.');
        header('Location: ' . APP_URL . '/menu.php' . (isStoreStaff() ? '' : '?store_id=' . $storeId));
        exit;
    }
}

$menu = [];
$stores = fetchStores();
if ($storeId) {
    $menu = fetchMenu($storeId);
}

$storeName = '';
foreach ($stores as $s) {
    if ((int) $s['id'] === $storeId) {
        $storeName = $s['store_name'];
        break;
    }
}

$pageTitle = 'Menu Availability · ' . ($storeName ?: 'Store');
include __DIR__ . '/includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>🍽️ <?= e($storeName) ?> Menu</h2>
            <small class="text-muted">Toggle items Off during 86 or ingredient shortages.</small>
        </div>
        <div class="card-body">
            <?php if (!isStoreStaff()): ?>
                <div class="form-group">
                    <label>Jump to store</label>
                    <select class="form-control" onchange="if(this.value) location.href='?store_id='+this.value">
                        <option value="">Select store…</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $storeId ? 'selected' : '' ?>><?= e($s['store_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if (!$storeId): ?>
                <p class="text-muted">Select a partner store to assist with menu availability.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Category</th><th>Item</th><th>Price</th><th>Available</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($menu as $item): ?>
                            <tr>
                                <td><?= e($item['category']) ?></td>
                                <td><strong><?= e($item['item_name']) ?></strong></td>
                                <td><?= formatMoney((float) $item['price']) ?></td>
                                <td><?= !empty($item['is_available']) ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-muted">OFF</span>' ?></td>
                                <td>
                                    <form method="POST" style="margin:0" onsubmit="return confirm('Toggle availability?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <button class="btn btn-outline btn-sm" type="submit"><?= !empty($item['is_available']) ? 'Mark 86 / OFF' : 'Turn back ON' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($menu)): ?>
                            <tr><td colspan="5" class="text-muted">No menu items yet. Add the store's initial menu below.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($storeId): ?>
    <div class="card">
        <div class="card-header"><h2>Add menu item</h2></div>
        <div class="card-body">
            <div class="alert alert-info">
                Use this to help new stores upload their initial menu. For bulk uploads, coordinate with store manager directly.
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Item name</label>
                    <input class="form-control" name="item_name" required placeholder="Chickenjoy Bucket, Large Milk Tea, etc.">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <input class="form-control" name="category" value="Main" placeholder="Fast Food, Sides, Drinks, Hardware...">
                    </div>
                    <div class="form-group">
                        <label>Price (₱)</label>
                        <input class="form-control" name="price" type="number" step="0.01" min="0" required placeholder="99.00">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Add item to <?= e($storeName) ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
