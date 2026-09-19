<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'store_operator';
        $storeId = (int) ($_POST['store_id'] ?? 0);
        $password = $_POST['password'] ?? 'password123';

        if ($fullName && $email) {
            $code = 'STF-' . str_pad((string) ($pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare('INSERT INTO staff (staff_code, full_name, email, phone, password, store_id, role, shift_status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $storeIdVal = $storeId > 0 ? $storeId : null;
            $stmt->execute([$code, $fullName, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $storeIdVal, $role, 'off_shift']);
            flash('success', 'Staff member added successfully.');
        }
    }

    if ($action === 'update_staff') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if ($staffId > 0) {
            $storeId = (int) ($_POST['store_id'] ?? 0);
            $storeIdVal = $storeId > 0 ? $storeId : null;
            $stmt = $pdo->prepare('UPDATE staff SET full_name = ?, email = ?, phone = ?, store_id = ?, role = ?, shift_status = ? WHERE id = ?');
            $stmt->execute([
                trim($_POST['full_name'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['phone'] ?? ''),
                $storeIdVal,
                $_POST['role'] ?? 'store_operator',
                $_POST['shift_status'] ?? 'off_shift',
                $staffId,
            ]);
            flash('success', 'Staff profile updated.');
        }
    }

    if ($action === 'deactivate_staff') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if ($staffId > 0) {
            $pdo->prepare('UPDATE staff SET is_active = 0, shift_status = ? WHERE id = ?')->execute(['off_shift', $staffId]);
            flash('success', 'Staff account deactivated.');
        }
    }

    if ($action === 'activate_staff') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if ($staffId > 0) {
            $pdo->prepare('UPDATE staff SET is_active = 1 WHERE id = ?')->execute([$staffId]);
            flash('success', 'Staff account reactivated.');
        }
    }

    redirect('/staff.php');
}

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$pageTitle = 'Staff Management';
include __DIR__ . '/includes/header.php';

$sql = '
    SELECT s.*, ps.store_name
    FROM staff s
    LEFT JOIN partnership_stores ps ON ps.id = s.store_id
    WHERE 1=1
';
$params = [];

if ($search !== '') {
    $sql .= ' AND (s.full_name LIKE ? OR s.email LIKE ? OR s.staff_code LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

if ($roleFilter !== '') {
    $sql .= ' AND s.role = ?';
    $params[] = $roleFilter;
}

$sql .= ' ORDER BY s.full_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffList = $stmt->fetchAll();

$stores = $pdo->query('SELECT id, store_name FROM partnership_stores ORDER BY store_name')->fetchAll();
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Add New Staff</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_staff">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Assigned Store</label>
                    <select name="store_id" class="form-control">
                        <option value="0">System / No Store</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?= $store['id'] ?>"><?= e($store['store_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Role / Permissions</label>
                    <select name="role" class="form-control" required>
                        <option value="order_approver">Order Approver</option>
                        <option value="inventory_manager">Inventory Manager</option>
                        <option value="support_representative">Support Representative</option>
                        <option value="store_operator">Store Operator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" value="password123">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Add Staff Member</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Staff Directory</h2>
        <a href="<?= APP_URL ?>/api/export.php?type=staff" class="btn btn-outline btn-sm">Export CSV</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, email, ID..." value="<?= e($search) ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="order_approver" <?= $roleFilter === 'order_approver' ? 'selected' : '' ?>>Order Approver</option>
                    <option value="inventory_manager" <?= $roleFilter === 'inventory_manager' ? 'selected' : '' ?>>Inventory Manager</option>
                    <option value="support_representative" <?= $roleFilter === 'support_representative' ? 'selected' : '' ?>>Support Representative</option>
                    <option value="store_operator" <?= $roleFilter === 'store_operator' ? 'selected' : '' ?>>Store Operator</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Full Name</th>
                        <th>Assigned Store</th>
                        <th>Role / Permissions</th>
                        <th>Shift Status</th>
                        <th>Last Active</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="8" class="text-center text-muted">No staff records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($staffList as $staff): ?>
                    <tr>
                        <td><?= e($staff['staff_code']) ?></td>
                        <td>
                            <strong><?= e($staff['full_name']) ?></strong><br>
                            <small class="text-muted"><?= e($staff['email']) ?></small>
                        </td>
                        <td><?= e($staff['store_name'] ?? 'System') ?></td>
                        <td><?= e(roleLabel($staff['role'])) ?></td>
                        <td><?= statusBadge($staff['shift_status']) ?></td>
                        <td><?= formatDateTime($staff['last_active_at']) ?></td>
                        <td>
                            <?php if ($staff['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm" data-modal="staffModal<?= $staff['id'] ?>">Edit</button>
                            <?php if ($staff['is_active']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmAction('Deactivate this staff account?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="deactivate_staff">
                                <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Deactivate</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="activate_staff">
                                <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Reactivate</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <div class="modal-overlay" id="staffModal<?= $staff['id'] ?>">
                        <div class="modal">
                            <div class="modal-header">
                                <h3>Edit Staff — <?= e($staff['full_name']) ?></h3>
                                <button class="modal-close">&times;</button>
                            </div>
                            <div class="modal-body">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_staff">
                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Full Name</label>
                                            <input name="full_name" class="form-control" value="<?= e($staff['full_name']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input name="email" type="email" class="form-control" value="<?= e($staff['email']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Phone</label>
                                            <input name="phone" class="form-control" value="<?= e($staff['phone'] ?? '') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Assigned Store</label>
                                            <select name="store_id" class="form-control">
                                                <option value="0">System / No Store</option>
                                                <?php foreach ($stores as $store): ?>
                                                    <option value="<?= $store['id'] ?>" <?= (int) $staff['store_id'] === (int) $store['id'] ? 'selected' : '' ?>><?= e($store['store_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Role</label>
                                            <select name="role" class="form-control">
                                                <option value="order_approver" <?= $staff['role'] === 'order_approver' ? 'selected' : '' ?>>Order Approver</option>
                                                <option value="inventory_manager" <?= $staff['role'] === 'inventory_manager' ? 'selected' : '' ?>>Inventory Manager</option>
                                                <option value="support_representative" <?= $staff['role'] === 'support_representative' ? 'selected' : '' ?>>Support Representative</option>
                                                <option value="store_operator" <?= $staff['role'] === 'store_operator' ? 'selected' : '' ?>>Store Operator</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Shift Status</label>
                                            <select name="shift_status" class="form-control">
                                                <option value="on_shift" <?= $staff['shift_status'] === 'on_shift' ? 'selected' : '' ?>>On Shift</option>
                                                <option value="off_shift" <?= $staff['shift_status'] === 'off_shift' ? 'selected' : '' ?>>Off Shift</option>
                                                <option value="break" <?= $staff['shift_status'] === 'break' ? 'selected' : '' ?>>Break</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
