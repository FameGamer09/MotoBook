<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
$isCreate = $id === -1;

$pdo = getDBConnection();

$hasNewCols = static function () use ($pdo): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riders'
           AND COLUMN_NAME IN ('gcash_mobile_number','fcm_push_token')"
    );
    $stmt->execute();
    $cached = ((int) $stmt->fetchColumn()) >= 2;
    return $cached;
};

$rider = null;
if (!$isCreate) {
    $stmt = $pdo->prepare('SELECT * FROM riders WHERE id = ?');
    $stmt->execute([$id]);
    $rider = $stmt->fetch();
    if (!$rider) {
        echo json_encode(['success' => false]);
        exit;
    }
}

$fullName = (string) ($rider['full_name'] ?? '');
$email    = (string) ($rider['email']    ?? '');
$phone    = (string) ($rider['phone']    ?? '');
$vtype    = (string) ($rider['vehicle_type']   ?? 'MOTORCYCLE');
$plate    = (string) ($rider['vehicle_plate']  ?? '');
$license  = (string) ($rider['license_number'] ?? '');
$status   = (string) ($rider['status']        ?? 'active');
$gcashMobile  = (string) ($rider['gcash_mobile_number']  ?? '');
$gcashAccount = (string) ($rider['gcash_account_name']   ?? '');
$gcashQrUri   = (string) ($rider['gcash_qr_data_uri']    ?? '');
$fcmToken     = (string) ($rider['fcm_push_token']       ?? '');

$formAction = APP_URL . '/riders.php';
$title = $isCreate ? 'Add New Rider' : htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

$overviewHtml = '
<div class="detail-grid">
    <div class="detail-item"><label>Full Name</label><span>' . ($isCreate ? '<em>Complete the form below</em>' : e($fullName)) . '</span></div>
    <div class="detail-item"><label>Email</label><span>' . ($isCreate ? '<em>Set in the form</em>' : e($email)) . '</span></div>
    <div class="detail-item"><label>Phone</label><span>' . ($isCreate ? '&mdash;' : e($phone)) . '</span></div>
    <div class="detail-item"><label>Vehicle</label><span>' . e($vtype) . ' &mdash; ' . e($plate ?: 'N/A') . '</span></div>
    <div class="detail-item"><label>License</label><span>' . e($license ?: 'N/A') . '</span></div>';
if (!$isCreate) {
    $overviewHtml .= '
    <div class="detail-item"><label>Status</label><span>' . statusBadge($rider['status'] ?? '') . ' ' . statusBadge($rider['duty_status'] ?? '') . '</span></div>
    <div class="detail-item"><label>Total Deliveries</label><span>' . (int) ($rider['total_deliveries'] ?? 0) . '</span></div>
    <div class="detail-item"><label>Avg Rating</label><span>' . renderStars((float) ($rider['avg_rating'] ?? 0)) . '</span></div>';
}
if ($hasNewCols() && !$isCreate) {
    $overviewHtml .= '
    <div class="detail-item"><label>GCash Number</label><span>' . (trim($gcashMobile) !== '' ? e($gcashMobile) : '<span class="text-muted">Not set</span>') . '</span></div>
    <div class="detail-item"><label>GCash Account</label><span>' . (trim($gcashAccount) !== '' ? e($gcashAccount) : '<span class="text-muted">Not set</span>') . '</span></div>
    <div class="detail-item"><label>Push Notifications</label><span>' . (trim($fcmToken) !== '' ? '<span class="badge badge-success">Registered</span>' : '<span class="badge badge-outline">Pending</span>') . '</span></div>';
    if (trim($gcashQrUri) !== '') {
        $overviewHtml .= '
    <div class="detail-item"><label>GCash QR</label><span><img alt="Rider GCash QR" src="' . e($gcashQrUri) . '" style="max-width:120px;max-height:120px;background:#fff;border-radius:8px;padding:4px;"></span></div>';
    }
}
$overviewHtml .= '
</div>
<form method="POST" action="' . $formAction . '" enctype="multipart/form-data" style="margin-top:1.5rem;">
    ' . csrfField() . '
    <input type="hidden" name="action" value="' . ($isCreate ? 'create_rider' : 'update_profile') . '">
    <input type="hidden" name="rider_id" value="' . $id . '">
    <h4 style="margin-bottom:0.75rem;color:var(--cyan-800);">' . ($isCreate ? 'Add New Rider' : 'Edit Profile') . '</h4>
    <div class="form-row">
        <div class="form-group"><label>Full Name</label><input name="full_name" class="form-control" required value="' . e($fullName) . '"></div>
        <div class="form-group"><label>Email</label><input name="email" type="email" class="form-control" required value="' . e($email) . '" ' . ($isCreate ? '' : 'readonly style="background:var(--slate-50);cursor:not-allowed;"') . '></div>
        <div class="form-group"><label>Phone</label><input name="phone" class="form-control" value="' . e($phone) . '"></div>
        <div class="form-group"><label>Vehicle Type</label>
            <select name="vehicle_type" class="form-control">
                <option value="MOTORCYCLE"' . ($vtype === 'MOTORCYCLE' ? ' selected' : '') . '>Motorcycle</option>
                <option value="EBIKE"'       . ($vtype === 'EBIKE'       ? ' selected' : '') . '>E-Bike</option>
                <option value="CAR"'          . ($vtype === 'CAR'         ? ' selected' : '') . '>Car</option>
                <option value="VAN"'          . ($vtype === 'VAN'         ? ' selected' : '') . '>Van</option>
            </select>
        </div>
        <div class="form-group"><label>Plate Number</label><input name="vehicle_plate" class="form-control" value="' . e($plate) . '"></div>
        <div class="form-group"><label>License Number</label><input name="license_number" class="form-control" value="' . e($license) . '"></div>';
if ($isCreate) {
    $overviewHtml .= '
        <div class="form-group"><label>Temporary Password</label><input name="password" class="form-control" type="password" autocomplete="new-password" placeholder="Will set to Motobook200409 if left blank"></div>';
}
if ($hasNewCols()) {
    $overviewHtml .= '
        <div class="form-group"><label>GCash Mobile Number</label><input name="gcash_mobile_number" class="form-control" maxlength="20" placeholder="09170000000" value="' . e($gcashMobile) . '"></div>
        <div class="form-group"><label>GCash Account Name</label><input name="gcash_account_name" class="form-control" maxlength="80" placeholder="JUAN DELA CRUZ" value="' . e($gcashAccount) . '"></div>
        <div class="form-group"><label>GCash QR Image (upload to generate data-URI)</label><input name="gcash_qr_image" type="file" accept="image/*" class="form-control"></div>
        <div class="form-group"><label>Push Token (FCM / Web Push, auto-set by rider app)</label><input name="fcm_push_token" class="form-control" maxlength="255" placeholder="Paste token here manually if needed" value="' . e($fcmToken) . '" readonly onfocus="this.removeAttribute(\'readonly\');"></div>';
}
$overviewHtml .= '
    </div>
    <button type="submit" class="btn btn-primary btn-sm">' . ($isCreate ? 'Create Rider' : 'Save Profile') . '</button>
    <a href="' . APP_URL . '/riders.php" class="btn btn-outline btn-sm" style="margin-left:0.5rem;">Cancel</a>
</form>';
if (!$isCreate) {
    $overviewHtml .= '
<div class="btn-group" style="margin-top:1rem;">
    <form method="POST" action="' . APP_URL . '/riders.php" style="display:inline;">
        ' . csrfField() . '
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="rider_id" value="' . (int) ($rider['id'] ?? 0) . '">
        <select name="status" class="form-control" style="display:inline-block;width:auto;">
            <option value="active"'   . ($status === 'active'   ? ' selected' : '') . '>Active</option>
            <option value="on_duty"'  . ($status === 'on_duty'  ? ' selected' : '') . '>On Duty</option>
            <option value="inactive"' . ($status === 'inactive' ? ' selected' : '') . '>Inactive</option>
            <option value="suspended"'. ($status === 'suspended'? ' selected' : '') . '>Suspended</option>
        </select>
        <button type="submit" class="btn btn-warning btn-sm">Update Status</button>
    </form>
    <form method="POST" action="' . APP_URL . '/riders.php" style="display:inline;" onsubmit="return confirmAction(\'Reset password to Motobook200409?\');">
        ' . csrfField() . '
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="rider_id" value="' . (int) ($rider['id'] ?? 0) . '">
        <button type="submit" class="btn btn-outline btn-sm">Reset Password</button>
    </form>
</div>';
}

$reviewsHtml = '';
$earningsHtml = '';
if (!$isCreate) {
    $reviewStmt = $pdo->prepare('SELECT * FROM customer_reviews WHERE rider_id = ? ORDER BY created_at DESC LIMIT 20');
    $reviewStmt->execute([$id]);
    $reviews = $reviewStmt->fetchAll();

    $reviewsHtml = '<div class="rating-summary"><div class="big-rating">' . number_format((float) ($rider['avg_rating'] ?? 0), 1) . '</div>' . renderStars((float) ($rider['avg_rating'] ?? 0)) . '<p>' . (int) ($rider['total_deliveries'] ?? 0) . ' completed deliveries</p></div>';

    foreach ($reviews as $review) {
        $reviewsHtml .= '<div class="review-item' . (!empty($review['is_flagged']) ? ' flagged' : '') . '">';
        $reviewsHtml .= '<div class="review-meta"><strong>' . e($review['customer_name']) . '</strong> ' . renderStars((float) $review['rating']) . '<small>' . formatDateTime($review['created_at']) . '</small></div>';
        $reviewsHtml .= '<p>' . e($review['comment']) . '</p></div>';
    }

    if (empty($reviews)) {
        $reviewsHtml .= '<p class="text-muted">No reviews yet.</p>';
    }

    $earnStmt = $pdo->prepare('SELECT * FROM rider_daily_collections WHERE rider_id = ? ORDER BY collection_date DESC LIMIT 15');
    $earnStmt->execute([$id]);
    $earnings = $earnStmt->fetchAll();

    $earningsHtml = '<div class="table-responsive"><table><thead><tr><th>Date</th><th>Orders</th><th>Collected</th><th>Commission</th><th>Status</th></tr></thead><tbody>';

    foreach ($earnings as $e) {
        $earningsHtml .= '<tr><td>' . formatDate($e['collection_date']) . '</td><td>' . (int) $e['orders_completed'] . '</td>';
        $earningsHtml .= '<td>' . formatMoney((float) $e['total_collected']) . '</td><td>' . formatMoney((float) $e['commission_earned']) . '</td>';
        $earningsHtml .= '<td>' . statusBadge($e['remittance_status']) . '</td></tr>';
    }

    $earningsHtml .= '</tbody></table></div>';

    if (empty($earnings)) {
        $earningsHtml = '<p class="text-muted">No earnings history.</p>';
    }
}

echo json_encode([
    'success' => true,
    'rider'   => $rider,
    'title'   => $title,
    'overview_html' => $overviewHtml,
    'reviews_html'  => $reviewsHtml,
    'earnings_html' => $earningsHtml,
]);
