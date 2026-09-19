<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

$uploadDir = __DIR__ . '/uploads/permits/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_store') {
        $storeName = trim($_POST['store_name'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $address = trim($_POST['branch_address'] ?? '');
        $ownerPassword = $_POST['owner_password'] ?? 'password123';

        $permitPath = null;
        if (!empty($_FILES['business_permit']['name']) && $_FILES['business_permit']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['business_permit']['name'], PATHINFO_EXTENSION);
            $filename = 'permit_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['business_permit']['tmp_name'], $uploadDir . $filename)) {
                $permitPath = 'uploads/permits/' . $filename;
            }
        }

        if ($storeName && $address) {
            $stmt = $pdo->prepare('
                INSERT INTO partnership_stores (
                    store_name, category_id, branch_address, latitude, longitude,
                    contact_phone, contact_email, operating_hours, commission_rate, status,
                    owner_name, owner_email, owner_password, business_permit, tax_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $categoryVal = $categoryId > 0 ? $categoryId : null;
            $stmt->execute([
                $storeName,
                $categoryVal,
                $address,
                $_POST['latitude'] ?? null,
                $_POST['longitude'] ?? null,
                trim($_POST['contact_phone'] ?? ''),
                trim($_POST['contact_email'] ?? ''),
                trim($_POST['operating_hours'] ?? '08:00 - 22:00'),
                (float) ($_POST['commission_rate'] ?? 10),
                'open',
                trim($_POST['owner_name'] ?? ''),
                trim($_POST['owner_email'] ?? ''),
                password_hash($ownerPassword, PASSWORD_DEFAULT),
                $permitPath,
                trim($_POST['tax_id'] ?? ''),
            ]);
            flash('success', 'Partnership store onboarded successfully.');
        }
    }

    if ($action === 'update_status') {
        $storeId = (int) ($_POST['store_id'] ?? 0);
        $status = $_POST['status'] ?? 'open';
        $allowed = ['open', 'closed', 'paused', 'offline'];
        if ($storeId > 0 && in_array($status, $allowed, true)) {
            $pdo->prepare('UPDATE partnership_stores SET status = ? WHERE id = ?')->execute([$status, $storeId]);
            flash('success', 'Store operational status updated.');
        }
    }

    if ($action === 'update_store') {
        $storeId = (int) ($_POST['store_id'] ?? 0);
        if ($storeId > 0) {
            $pdo->prepare('
                UPDATE partnership_stores SET
                    store_name = ?, category_id = ?, branch_address = ?, latitude = ?, longitude = ?,
                    contact_phone = ?, contact_email = ?, operating_hours = ?, commission_rate = ?, tax_id = ?
                WHERE id = ?
            ')->execute([
                trim($_POST['store_name'] ?? ''),
                (int) ($_POST['category_id'] ?? 0) ?: null,
                trim($_POST['branch_address'] ?? ''),
                $_POST['latitude'] ?? null,
                $_POST['longitude'] ?? null,
                trim($_POST['contact_phone'] ?? ''),
                trim($_POST['contact_email'] ?? ''),
                trim($_POST['operating_hours'] ?? ''),
                (float) ($_POST['commission_rate'] ?? 10),
                trim($_POST['tax_id'] ?? ''),
                $storeId,
            ]);
            flash('success', 'Store details updated.');
        }
    }

    redirect('/stores.php');
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$pageTitle = 'Partnership Stores';
include __DIR__ . '/includes/header.php';

$sql = '
    SELECT ps.*, sc.name AS category_name
    FROM partnership_stores ps
    LEFT JOIN store_categories sc ON sc.id = ps.category_id
    WHERE 1=1
';
$params = [];

if ($search !== '') {
    $sql .= ' AND (ps.store_name LIKE ? OR ps.branch_address LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like]);
}

if ($statusFilter !== '') {
    $sql .= ' AND ps.status = ?';
    $params[] = $statusFilter;
}

$sql .= ' ORDER BY ps.store_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$storeList = $stmt->fetchAll();

$categories = $pdo->query('SELECT id, name FROM store_categories ORDER BY name')->fetchAll();
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Add New Partnership Store</h2>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_store">
            <div class="form-row">
                <div class="form-group">
                    <label>Store Name</label>
                    <input type="text" name="store_name" class="form-control" required placeholder="e.g. Jollibee">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Branch Address</label>
                    <input type="text" name="branch_address" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Latitude</label>
                    <input type="text" name="latitude" class="form-control" placeholder="14.599512">
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="text" name="longitude" class="form-control" placeholder="120.984222">
                </div>
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Operating Hours</label>
                    <input type="text" name="operating_hours" class="form-control" value="08:00 - 22:00">
                </div>
                <div class="form-group">
                    <label>Commission Rate (%)</label>
                    <input type="number" name="commission_rate" class="form-control" value="10" min="0" max="100" step="0.01">
                </div>
                <div class="form-group">
                    <label>Store Owner / Manager Name</label>
                    <input type="text" name="owner_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>Owner Email (Login)</label>
                    <input type="email" name="owner_email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Owner Password</label>
                    <input type="password" name="owner_password" class="form-control" value="password123">
                </div>
                <div class="form-group">
                    <label>Tax Identification (TIN)</label>
                    <input type="text" name="tax_id" class="form-control">
                </div>
                <div class="form-group">
                    <label>Business Permit Upload</label>
                    <input type="file" name="business_permit" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Onboard Store</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Partner Stores Directory</h2>
        <a href="<?= APP_URL ?>/api/export.php?type=stores" class="btn btn-outline btn-sm">Export CSV</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Store name, address..." value="<?= e($search) ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                    <option value="paused" <?= $statusFilter === 'paused' ? 'selected' : '' ?>>Paused</option>
                    <option value="offline" <?= $statusFilter === 'offline' ? 'selected' : '' ?>>Offline</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Store Name</th>
                        <th>Branch Location</th>
                        <th>Operating Hours</th>
                        <th>Status</th>
                        <th>Total Orders</th>
                        <th>Commission</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($storeList)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No stores found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($storeList as $store): ?>
                    <tr>
                        <td>
                            <strong><?= e($store['store_name']) ?></strong><br>
                            <small class="text-muted"><?= e($store['category_name'] ?? 'N/A') ?></small>
                        </td>
                        <td><?= e($store['branch_address']) ?></td>
                        <td><?= e($store['operating_hours']) ?></td>
                        <td><?= statusBadge($store['status']) ?></td>
                        <td><?= (int) $store['total_orders'] ?></td>
                        <td><?= number_format((float) $store['commission_rate'], 2) ?>%</td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openStoreModal(<?= $store['id'] ?>)">View</button>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
                                <select name="status" class="form-control" style="display:inline-block;width:auto;font-size:0.75rem;padding:0.3rem;">
                                    <option value="open" <?= $store['status'] === 'open' ? 'selected' : '' ?>>Force Open</option>
                                    <option value="paused" <?= $store['status'] === 'paused' ? 'selected' : '' ?>>Pause</option>
                                    <option value="closed" <?= $store['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                    <option value="offline" <?= $store['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                                </select>
                                <button type="submit" class="btn btn-warning btn-sm">Set</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="storeModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="storeModalTitle">Store Details</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="storeDetailContent"></div>
    </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
