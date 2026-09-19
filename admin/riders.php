<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $riderId = (int) ($_POST['rider_id'] ?? 0);

    if ($action === 'update_status' && $riderId > 0) {
        $status = $_POST['status'] ?? 'active';
        $allowed = ['active', 'inactive', 'on_duty', 'suspended'];
        if (in_array($status, $allowed, true)) {
            $stmt = $pdo->prepare('UPDATE riders SET status = ? WHERE id = ?');
            $stmt->execute([$status, $riderId]);
            flash('success', 'Rider status updated.');
        }
    }

    if ($action === 'reset_password' && $riderId > 0) {
        $newPass = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE riders SET password = ? WHERE id = ?');
        $stmt->execute([$newPass, $riderId]);
        flash('success', 'Rider password reset to: password123');
    }

    if ($action === 'update_profile' && $riderId > 0) {
        $stmt = $pdo->prepare('UPDATE riders SET full_name = ?, phone = ?, vehicle_plate = ?, license_number = ?, vehicle_type = ? WHERE id = ?');
        $stmt->execute([
            trim($_POST['full_name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['vehicle_plate'] ?? ''),
            trim($_POST['license_number'] ?? ''),
            trim($_POST['vehicle_type'] ?? ''),
            $riderId,
        ]);
        flash('success', 'Rider profile updated.');
    }

    redirect('/riders.php');
}

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$pageTitle = 'Riders Management';
include __DIR__ . '/includes/header.php';

$sql = 'SELECT * FROM riders WHERE 1=1';
$params = [];

if ($filter !== 'all') {
    $sql .= ' AND status = ?';
    $params[] = $filter;
}

if ($search !== '') {
    $sql .= ' AND (full_name LIKE ? OR email LIKE ? OR rider_code LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$sql .= ' ORDER BY full_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riders = $stmt->fetchAll();
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Riders Master Directory</h2>
        <a href="<?= APP_URL ?>/api/export.php?type=riders" class="btn btn-outline btn-sm">Export CSV</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, email, code..." value="<?= e($search) ?>">
            </div>
            <div class="form-group">
                <label>Filter Status</label>
                <select name="filter" class="form-control">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="on_duty" <?= $filter === 'on_duty' ? 'selected' : '' ?>>On-Duty</option>
                    <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Rider Name</th>
                        <th>Contact</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Duty</th>
                        <th>Rating</th>
                        <th>Deliveries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riders as $rider): ?>
                    <tr>
                        <td><?= e($rider['rider_code']) ?></td>
                        <td><strong><?= e($rider['full_name']) ?></strong></td>
                        <td><?= e($rider['phone']) ?><br><small><?= e($rider['email']) ?></small></td>
                        <td><?= e($rider['vehicle_type']) ?><br><small><?= e($rider['vehicle_plate'] ?? 'N/A') ?></small></td>
                        <td><?= statusBadge($rider['status']) ?></td>
                        <td><?= statusBadge($rider['duty_status']) ?></td>
                        <td><?= renderStars((float) $rider['avg_rating']) ?></td>
                        <td><?= (int) $rider['total_deliveries'] ?></td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openRiderModal(<?= $rider['id'] ?>)">View Profile</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="riderModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="riderModalTitle">Rider Profile</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-tabs">
                <button class="tab-btn active" data-tab="tab-overview">Overview</button>
                <button class="tab-btn" data-tab="tab-reviews">Reviews & Satisfaction</button>
                <button class="tab-btn" data-tab="tab-earnings">Earnings & Remittance</button>
            </div>
            <div class="tab-panel active" id="tab-overview"><div id="riderOverview"></div></div>
            <div class="tab-panel" id="tab-reviews"><div id="riderReviews"></div></div>
            <div class="tab-panel" id="tab-earnings"><div id="riderEarnings"></div></div>
        </div>
    </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
