<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

$pageMode = 'store';
$user = currentUser();
$myStoreId = currentUserStoreId() ?? 0;
$storeId = (int)($_GET['store_id'] ?? $myStoreId);
$viewMode = ($_GET['view'] ?? 'grid') === 'table' ? 'table' : 'grid';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if (isStoreStaff() && (!$myStoreId || $storeId !== $myStoreId)) {
    if (!$myStoreId) {
        flash('error', 'Your account is not assigned to a store.');
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
    flash('error', 'You can only manage your own store menu.');
    header('Location: ' . APP_URL . '/menu.php');
    exit;
}

if (isStoreStaff() && !$storeId) {
    flash('error', 'Your account is not assigned to a store.');
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$canDelete = canDeleteMenu($user);

function handleMenuUpload(?array $file): ?string
{
    if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) return null;
    $uploadDir = dirname(__DIR__, 1) . '/admin/uploads/menu_items';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
    if (!is_dir($uploadDir)) return null;
    $ext = $allowed[$mime];
    $name = 'mi_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . '/' . $name;
    if ($ext === 'jpg') {
        $src = imagecreatefromjpeg($file['tmp_name']);
    } elseif ($ext === 'png') {
        $src = imagecreatefrompng($file['tmp_name']);
    } elseif ($ext === 'gif') {
        $src = imagecreatefromgif($file['tmp_name']);
    } else {
        $src = imagecreatefromwebp($file['tmp_name']);
    }
    if (!$src) return null;
    [$origW, $origH] = getimagesize($file['tmp_name']) ?: [800, 600];
    $maxW = 800; $maxH = 800;
    $ratio = min($maxW / $origW, $maxH / $origH, 1);
    $newW = (int)round($origW * $ratio);
    $newH = (int)round($origH * $ratio);
    $dst = imagecreatetruecolor(max(1, $newW), max(1, $newH));
    if ($ext === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    $ok = false;
    if ($ext === 'jpg') $ok = imagejpeg($dst, $dest, 85);
    elseif ($ext === 'png') $ok = imagepng($dst, $dest, 7);
    elseif ($ext === 'gif') $ok = imagegif($dst, $dest);
    else $ok = imagewebp($dst, $dest, 85);
    imagedestroy($src); imagedestroy($dst);
    return $ok ? ('uploads/menu_items/' . $name) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_item' && is_numeric($_POST['item_id'] ?? '')) {
        $itemId = (int)$_POST['item_id'];
        $scope = isStoreStaff() ? $myStoreId : null;
        if ($scope) {
            $check = getOpsDB()->prepare('SELECT id FROM store_menu_items WHERE id = ? AND store_id = ?');
            $check->execute([$itemId, $scope]);
            if (!$check->fetch()) {
                flash('error', 'Item not found in your store.');
                header('Location: ' . APP_URL . '/menu.php');
                exit;
            }
        }
        $ok = toggleMenuItem($itemId);
        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        }
        flash($ok ? 'success' : 'error', $ok ? 'Item availability updated.' : 'Update failed.');
        header('Location: ' . APP_URL . '/menu.php' . (isStoreStaff() ? '' : '?store_id=' . $storeId));
        exit;
    }

    if ($action === 'save_item') {
        $itemId = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $itemData = [
            'id' => $itemId,
            'item_name' => trim((string)($_POST['item_name'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? 'Uncategorized')),
            'price' => (float)($_POST['price'] ?? 0),
            'description' => (string)($_POST['description'] ?? ''),
            'is_available' => isset($_POST['is_available']) ? 1 : 0,
        ];
        $imgRel = handleMenuUpload($_FILES['item_image'] ?? null);
        if ($imgRel) {
            $itemData['image_path'] = $imgRel;
        } elseif (!empty($_POST['existing_image_path'])) {
            $itemData['image_path'] = (string)$_POST['existing_image_path'];
        } else {
            $itemData['image_path'] = null;
        }
        $groupsRaw = $_POST['groups'] ?? [];
        $groups = [];
        if (is_array($groupsRaw)) {
            foreach ($groupsRaw as $gIdx => $g) {
                if (!is_array($g)) continue;
                $group = [
                    'group_name' => trim((string)($g['group_name'] ?? '')),
                    'selection_type' => ($g['selection_type'] ?? 'radio') === 'checkbox' ? 'checkbox' : 'radio',
                    'min_select' => max(0, (int)($g['min_select'] ?? 0)),
                    'max_select' => max(1, (int)($g['max_select'] ?? 1)),
                    'is_required' => !empty($g['is_required']) ? 1 : 0,
                    'options' => [],
                ];
                if ($group['group_name'] === '') continue;
                $opts = $g['options'] ?? [];
                if (is_array($opts)) {
                    foreach ($opts as $o) {
                        if (!is_array($o)) continue;
                        $optName = trim((string)($o['option_name'] ?? ''));
                        if ($optName === '') continue;
                        $group['options'][] = [
                            'option_name' => $optName,
                            'price_delta' => (float)($o['price_delta'] ?? 0),
                            'is_available' => isset($o['is_available']) ? 1 : 1,
                        ];
                    }
                }
                $groups[] = $group;
            }
        }
        $newId = saveItemWithOptions($storeId, $itemData, $groups);
        if ($newId) {
            flash('success', ($itemId > 0 ? 'Menu item updated.' : 'Menu item added.'));
        } else {
            flash('error', 'Save failed. Ensure name, category, and price are valid.');
        }
        $qs = isStoreStaff() ? '' : '?store_id=' . $storeId;
        header('Location: ' . APP_URL . '/menu.php' . $qs);
        exit;
    }

    if ($action === 'delete_item' && $canDelete && is_numeric($_POST['item_id'] ?? '')) {
        $itemId = (int)$_POST['item_id'];
        $scope = isStoreStaff() ? $myStoreId : null;
        $ok = deleteMenuItem($itemId, $scope);
        flash($ok ? 'success' : 'error', $ok ? 'Menu item deleted.' : 'Delete failed or forbidden.');
        $qs = isStoreStaff() ? '' : '?store_id=' . $storeId;
        header('Location: ' . APP_URL . '/menu.php' . $qs);
        exit;
    }
}

$stores = fetchStores();
$menu = [];
if ($storeId) {
    $menu = fetchMenuWithCounts($storeId);
}
$categories = [];
foreach ($menu as $m) {
    $c = (string)($m['category'] ?? 'Uncategorized');
    if (!in_array($c, $categories, true)) $categories[] = $c;
}
$storeName = '';
foreach ($stores as $s) {
    if ((int)$s['id'] === $storeId) { $storeName = $s['store_name']; break; }
}

$editItem = null;
if ($editId > 0) {
    $editItem = fetchItemWithOptions($editId);
    if ($editItem && isStoreStaff() && (int)$editItem['store_id'] !== $myStoreId) {
        $editItem = null;
    }
}

$pageTitle = 'Menu Manager · ' . ($storeName ?: 'Store');
include __DIR__ . '/includes/header.php';
?>

<div class="page-header-row card" style="margin-bottom:1rem;padding:1rem 1.25rem;display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap;">
    <div>
        <h2 style="margin:0;font-size:1.35rem;">🍽️ <?= e($storeName) ?> Menu Manager</h2>
        <p class="text-muted" style="margin:0.25rem 0 0 0;font-size:0.85rem;">
            Manage item availability, pricing, and variant groups (sides, drinks, add-ons).
        </p>
    </div>
    <div style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
        <?php if (!isStoreStaff()): ?>
        <select class="form-control" style="width:auto;min-width:240px;" onchange="location.href='?store_id='+this.value+'&view=<?= e($viewMode) ?>'">
            <option value="">Select store…</option>
            <?php foreach ($stores as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $storeId ? 'selected' : '' ?>><?= e($s['store_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <div class="btn-group" role="group" aria-label="View mode">
            <a class="btn btn-outline btn-sm<?= $viewMode === 'grid' ? ' active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>">Grid View</a>
            <a class="btn btn-outline btn-sm<?= $viewMode === 'table' ? ' active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view'=>'table'])) ?>">Table View</a>
        </div>
        <?php if ($storeId): ?>
        <button type="button" class="btn btn-primary btn-sm" id="openAddItemBtn" data-drawer="itemDrawer">
            + New Item
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$storeId): ?>
<div class="card">
    <div class="card-body">
        <p class="text-muted">Select a partner store to manage their menu catalog.</p>
    </div>
</div>
<?php else: ?>

<?php if ($viewMode === 'grid'): ?>
<div class="menu-grid" id="menuGrid">
    <?php foreach ($menu as $item): ?>
    <?php
        $imgSrc = '';
        if (!empty($item['image_path'])) {
            $imgSrc = rtrim(APP_URL, '/') . '/../admin/' . ltrim($item['image_path'], '/');
        }
        $totalVariations = (int)($item['group_count'] ?? 0) + (int)($item['option_count'] ?? 0);
    ?>
    <article class="menu-card<?= empty($item['is_available']) ? ' is-unavailable' : '' ?>" data-item-id="<?= (int)$item['id'] ?>">
        <div class="menu-card-thumb">
            <?php if ($imgSrc): ?>
                <img src="<?= e($imgSrc) ?>" alt="<?= e($item['item_name']) ?>" loading="lazy">
            <?php else: ?>
                <div class="menu-card-thumb-placeholder">🍽️</div>
            <?php endif; ?>
            <label class="upload-btn" title="Upload new image" data-upload-for="<?= (int)$item['id'] ?>">
                📷
            </label>
        </div>
        <div class="menu-card-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;margin-bottom:0.35rem;">
                <div style="min-width:0;flex:1;">
                    <span class="category-pill"><?= e($item['category'] ?? 'Uncategorized') ?></span>
                    <h3 class="menu-card-title" title="<?= e($item['item_name']) ?>"><?= e($item['item_name']) ?></h3>
                </div>
                <div class="menu-card-price">₱<?= formatMoney((float)$item['price']) ?></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin:0.6rem 0 0.8rem 0;gap:0.5rem;">
                <span class="variations-badge" title="Modifier groups / sub-options">
                    🏷️ <?= (int)($item['group_count'] ?? 0) ?> groups · <?= (int)($item['option_count'] ?? 0) ?> options
                </span>
                <label class="switch" title="Toggle availability">
                    <input type="checkbox" class="avail-toggle" data-item-id="<?= (int)$item['id'] ?>"<?= !empty($item['is_available']) ? ' checked' : '' ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            <form method="POST" class="inline-toggle-form" data-item-id="<?= (int)$item['id'] ?>" style="display:none;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_item">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
            </form>
            <div class="menu-card-actions">
                <button type="button" class="btn btn-outline btn-sm btn-edit-item" data-item-id="<?= (int)$item['id'] ?>" data-drawer="itemDrawer">✏️ Edit</button>
                <button type="button" class="btn btn-outline btn-sm btn-edit-groups" data-item-id="<?= (int)$item['id'] ?>" data-focus-groups="1" data-drawer="itemDrawer">🧩 Variants</button>
                <?php if ($canDelete): ?>
                <form method="POST" style="margin:0;display:inline;" onsubmit="return confirm('Permanently delete \'<?= e($item['item_name']) ?>\' and all its variants? This cannot be undone.')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete item">🗑️</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
    <?php if (empty($menu)): ?>
    <div class="card" style="grid-column:1/-1;padding:2rem;text-align:center;">
        <p class="text-muted" style="margin:0 0 0.75rem 0;">No menu items yet. Click <strong>+ New Item</strong> to add your first catalog entry.</p>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead><tr>
                    <th>Item</th><th>Category</th><th>Price</th><th>Variants</th><th>Available</th><th>Actions</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($menu as $item): ?>
                    <tr class="<?= empty($item['is_available']) ? 'row-muted' : '' ?>">
                        <td><strong><?= e($item['item_name']) ?></strong></td>
                        <td><span class="category-pill"><?= e($item['category'] ?? 'Uncategorized') ?></span></td>
                        <td>₱<?= formatMoney((float)$item['price']) ?></td>
                        <td><?= (int)($item['group_count'] ?? 0) ?> groups · <?= (int)($item['option_count'] ?? 0) ?> options</td>
                        <td>
                            <label class="switch" title="Toggle availability">
                                <input type="checkbox" class="avail-toggle" data-item-id="<?= (int)$item['id'] ?>"<?= !empty($item['is_available']) ? ' checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                            <form method="POST" class="inline-toggle-form" data-item-id="<?= (int)$item['id'] ?>" style="display:none;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_item">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                            </form>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                                <button type="button" class="btn btn-outline btn-sm btn-edit-item" data-item-id="<?= (int)$item['id'] ?>" data-drawer="itemDrawer">Edit</button>
                                <button type="button" class="btn btn-outline btn-sm btn-edit-groups" data-item-id="<?= (int)$item['id'] ?>" data-focus-groups="1" data-drawer="itemDrawer">Variants</button>
                                <?php if ($canDelete): ?>
                                <form method="POST" style="margin:0;display:inline;" onsubmit="return confirm('Delete item?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($menu)): ?>
                    <tr><td colspan="6" class="text-muted">No menu items yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="drawer-overlay" id="itemDrawerOverlay" data-drawer-overlay="itemDrawer"></div>
<aside class="drawer-right" id="itemDrawer" aria-labelledby="itemDrawerTitle" role="dialog" aria-modal="true">
    <div class="drawer-header">
        <h3 id="itemDrawerTitle" class="drawer-title">New Menu Item</h3>
        <button type="button" class="drawer-close" data-drawer-close="itemDrawer" aria-label="Close">&times;</button>
    </div>
    <form method="POST" id="itemForm" enctype="multipart/form-data" class="drawer-body" onsubmit="return true;" data-option-builder="1">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_item">
        <input type="hidden" name="item_id" id="hfItemId" value="0">
        <input type="hidden" name="existing_image_path" id="hfExistingImage" value="">

        <section class="drawer-section">
            <h4 class="drawer-section-title">Basic Information</h4>
            <div class="image-drop" id="imageDropZone">
                <div class="image-drop-preview" id="imageDropPreview">
                    <span class="image-drop-placeholder">📷<br><small>Drag image here or click to upload</small></span>
                </div>
                <input type="file" id="itemImageInput" name="item_image" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
            </div>
            <div class="form-group">
                <label for="fItemName">Item name <span class="req">*</span></label>
                <input type="text" id="fItemName" class="form-control" name="item_name" required maxlength="150" placeholder="e.g. Chickenjoy Bucket">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="fCategory">Category <span class="req">*</span></label>
                    <input type="text" id="fCategory" class="form-control" name="category" list="categoryDatalist" value="Main" placeholder="Main, Drinks, Family Packs…" maxlength="80" required>
                    <datalist id="categoryDatalist">
                        <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="fPrice">Base Price (₱) <span class="req">*</span></label>
                    <input type="number" id="fPrice" class="form-control" name="price" min="0" step="0.01" value="0" required>
                </div>
            </div>
            <div class="form-group">
                <label for="fDescription">Description</label>
                <textarea id="fDescription" class="form-control" name="description" rows="2" maxlength="500" placeholder="Short item description (optional)"></textarea>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;">
                <label class="switch" title="Available to order">
                    <input type="checkbox" id="fIsAvailable" name="is_available" checked>
                    <span class="slider round"></span>
                </label>
                <label for="fIsAvailable" style="margin:0;cursor:pointer;">Available for ordering</label>
            </div>
        </section>

        <section class="drawer-section" id="sectionGroups" data-option-group-table="store_menu_item_option_groups">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <h4 class="drawer-section-title" style="margin:0;">Modifier &amp; Add-on Groups</h4>
                <button type="button" class="btn btn-primary-outline btn-sm" id="btnAddGroup" data-add-group="1">+ Add Option Group</button>
            </div>
            <div id="optionGroupsContainer" class="option-groups-container"></div>
            <template id="tplOptionGroup">
                <div class="option-group-card" data-group-index="0">
                    <div class="option-group-head">
                        <input type="text" class="form-control" data-group-name placeholder="Group name (e.g. Choice A: Sides)" maxlength="100">
                        <button type="button" class="btn btn-danger-outline btn-xs btn-remove-group" title="Remove group">✕</button>
                    </div>
                    <div class="option-group-rules">
                        <label class="rule-item">
                            <span>Selection</span>
                            <select class="form-control" data-selection-type>
                                <option value="radio">Single (Radio)</option>
                                <option value="checkbox">Multi (Checkbox)</option>
                            </select>
                        </label>
                        <label class="rule-item">
                            <span>Min</span>
                            <input type="number" class="form-control" data-min-select min="0" value="1">
                        </label>
                        <label class="rule-item">
                            <span>Max</span>
                            <input type="number" class="form-control" data-max-select min="1" value="1">
                        </label>
                        <label class="rule-item switch-row">
                            <span>Required</span>
                            <label class="switch"><input type="checkbox" data-is-required><span class="slider round"></span></label>
                        </label>
                    </div>
                    <table class="option-table">
                        <thead><tr><th>Sub-item Name</th><th style="width:140px;">+ ₱ Delta</th><th style="width:80px;">Active</th><th style="width:40px;"></th></tr></thead>
                        <tbody class="option-tbody"></tbody>
                        <tfoot><tr><td colspan="4"><button type="button" class="btn btn-add-option">+ Add Sub-option</button></td></tr></tfoot>
                    </table>
                </div>
            </template>
            <template id="tplOptionRow">
                <tr class="option-row">
                    <td><input type="text" class="form-control" data-option-name placeholder="Extra Gravy, Coke, Rice…" maxlength="150"></td>
                    <td><input type="number" class="form-control" data-price-delta step="0.01" min="0" value="0"></td>
                    <td style="text-align:center;"><label class="switch"><input type="checkbox" data-option-avail checked><span class="slider round"></span></label></td>
                    <td><button type="button" class="btn btn-danger-outline btn-xs btn-remove-option" title="Remove option">✕</button></td>
                </tr>
            </template>
        </section>

        <div class="drawer-footer">
            <button type="button" class="btn btn-outline" data-drawer-close="itemDrawer">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btnSaveItem">💾 Save Menu Item</button>
        </div>
    </form>
</aside>

<script id="menuEditData" type="application/json"><?= json_encode($editItem) ?></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
