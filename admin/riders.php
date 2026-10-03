<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

/* Guard: A-rider migration cols may not exist yet; never crash admin if migration 001 not applied. */
$hasGcashCols = static function () use ($pdo): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riders'
           AND COLUMN_NAME IN ('gcash_mobile_number','gcash_account_name','gcash_qr_data_uri','fcm_push_token')"
    );
    $stmt->execute();
    $cached = ((int) $stmt->fetchColumn()) >= 3;
    return $cached;
};

function readUploadedImageAsDataUri(string $fieldName): ?string {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]) || empty($_FILES[$fieldName]['tmp_name']) || !is_uploaded_file($_FILES[$fieldName]['tmp_name'])) {
        return null;
    }
    $err = (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($_FILES[$fieldName]['tmp_name']);
    if (!str_starts_with($mime, 'image/')) return null;
    $size = filesize($_FILES[$fieldName]['tmp_name']);
    if ($size === false || $size > 2 * 1024 * 1024) return null; // 2MB cap for data URI
    $bytes = (string) file_get_contents($_FILES[$fieldName]['tmp_name']);
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

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
        $newPass = password_hash('Motobook200409', PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $pdo->prepare('UPDATE riders SET password = ? WHERE id = ?');
        $stmt->execute([$newPass, $riderId]);
        flash('success', 'Rider password reset to: Motobook200409');
    }

    if ($action === 'update_profile' && $riderId > 0) {
        $binds = [
            trim($_POST['full_name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['vehicle_plate'] ?? ''),
            trim($_POST['license_number'] ?? ''),
            trim($_POST['vehicle_type'] ?? 'MOTORCYCLE'),
        ];
        $sql = 'UPDATE riders SET full_name = ?, phone = ?, vehicle_plate = ?, license_number = ?, vehicle_type = ?';
        if ($hasGcashCols()) {
            $gcashMobile  = trim((string) ($_POST['gcash_mobile_number'] ?? ''));
            $gcashAccount = trim((string) ($_POST['gcash_account_name'] ?? ''));
            $fcmToken     = trim((string) ($_POST['fcm_push_token'] ?? ''));
            $gcashQrNew   = readUploadedImageAsDataUri('gcash_qr_image');

            $sql .= ', gcash_mobile_number = ?, gcash_account_name = ?';
            $binds[] = $gcashMobile === '' ? null : $gcashMobile;
            $binds[] = $gcashAccount === '' ? null : $gcashAccount;
            if ($gcashQrNew !== null) {
                $sql .= ', gcash_qr_data_uri = ?';
                $binds[] = $gcashQrNew;
            }
            $sql .= ', fcm_push_token = ?';
            $binds[] = $fcmToken === '' ? null : $fcmToken;
        }
        $sql .= ' WHERE id = ?';
        $binds[] = $riderId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($binds);
        flash('success', 'Rider profile updated.');
    }

    if ($action === 'create_rider') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        if ($fullName === '' || $email === '') {
            flash('error', 'Full name and email are required to create a rider.');
        } else {
            $phone     = trim((string) ($_POST['phone'] ?? ''));
            $vType     = trim((string) ($_POST['vehicle_type'] ?? 'MOTORCYCLE'));
            $plate     = trim((string) ($_POST['vehicle_plate'] ?? ''));
            $license   = trim((string) ($_POST['license_number'] ?? ''));
            $passRaw   = trim((string) ($_POST['password'] ?? ''));
            $password  = password_hash($passRaw !== '' ? $passRaw : 'Motobook200409', PASSWORD_BCRYPT, ['cost' => 10]);

            $codeStmt = $pdo->prepare("SELECT CONCAT('RDR-', LPAD(COALESCE(MAX(CAST(SUBSTRING_INDEX(rider_code,'-',-1) AS UNSIGNED)), 0)+1, 4, '0')) FROM riders WHERE rider_code LIKE 'RDR-%'");
            $codeStmt->execute();
            $riderCode = (string) $codeStmt->fetchColumn();

            $sqlCols = 'INSERT INTO riders (rider_code, full_name, email, phone, password, vehicle_type, vehicle_plate, license_number, status';
            $sqlPlac = 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?';
            $binds   = [$riderCode, $fullName, $email, $phone === '' ? null : $phone, $password, $vType, $plate === '' ? null : $plate, $license === '' ? null : $license, 'active'];

            if ($hasGcashCols()) {
                $gcashMobile  = trim((string) ($_POST['gcash_mobile_number'] ?? ''));
                $gcashAccount = trim((string) ($_POST['gcash_account_name'] ?? ''));
                $gcashQrNew   = readUploadedImageAsDataUri('gcash_qr_image');
                $fcmToken     = trim((string) ($_POST['fcm_push_token'] ?? ''));
                $sqlCols .= ', gcash_mobile_number, gcash_account_name, gcash_qr_data_uri, fcm_push_token';
                $sqlPlac .= ', ?, ?, ?, ?';
                $binds[] = $gcashMobile  === '' ? null : $gcashMobile;
                $binds[] = $gcashAccount === '' ? null : $gcashAccount;
                $binds[] = $gcashQrNew;
                $binds[] = $fcmToken     === '' ? null : $fcmToken;
            }

            $sqlCols .= ') ' . $sqlPlac . ')';
            $stmt = $pdo->prepare($sqlCols);
            $stmt->execute($binds);
            flash('success', sprintf('Rider %s (%s) created. Default temp password: Motobook200409.', $riderCode, $fullName));
        }
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
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Riders Master Directory</h2>
        <div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openRiderModal(-1)" style="margin-right:0.5rem;">Add Rider</button>
            <a href="<?= APP_URL ?>/api/export.php?type=riders" class="btn btn-outline btn-sm">Export CSV</a>
        </div>
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
                        <th>GCash Ready</th>
                        <th>Push</th>
                        <th>Duty</th>
                        <th>Rating</th>
                        <th>Deliveries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riders as $rider):
                        $gcashOk  = $hasGcashCols() && !empty($rider['gcash_mobile_number']);
                        $pushOk   = $hasGcashCols() && !empty($rider['fcm_push_token']);
                    ?>
                    <tr>
                        <td><?= e($rider['rider_code']) ?></td>
                        <td><strong><?= e($rider['full_name']) ?></strong></td>
                        <td><?= e($rider['phone']) ?><br><small><?= e($rider['email']) ?></small></td>
                        <td><?= e($rider['vehicle_type']) ?><br><small><?= e($rider['vehicle_plate'] ?? 'N/A') ?></small></td>
                        <td><?= statusBadge($rider['status']) ?></td>
                        <td>
                            <?php if ($gcashOk): ?>
                                <span class="badge badge-success" title="<?= e($rider['gcash_mobile_number'] ?? '') ?>">
                                    GCash
                                </span>
                            <?php else: ?>
                                <span class="badge badge-outline">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $pushOk ? '<span class="badge badge-success">On</span>' : '<span class="badge badge-outline">Off</span>' ?>
                        </td>
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
