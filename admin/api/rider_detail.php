<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT * FROM riders WHERE id = ?');
$stmt->execute([$id]);
$rider = $stmt->fetch();

if (!$rider) {
    echo json_encode(['success' => false]);
    exit;
}

$overviewHtml = '
<div class="detail-grid">
    <div class="detail-item"><label>Full Name</label><span>' . e($rider['full_name']) . '</span></div>
    <div class="detail-item"><label>Email</label><span>' . e($rider['email']) . '</span></div>
    <div class="detail-item"><label>Phone</label><span>' . e($rider['phone']) . '</span></div>
    <div class="detail-item"><label>Vehicle</label><span>' . e($rider['vehicle_type']) . ' — ' . e($rider['vehicle_plate'] ?? 'N/A') . '</span></div>
    <div class="detail-item"><label>License</label><span>' . e($rider['license_number'] ?? 'N/A') . '</span></div>
    <div class="detail-item"><label>Status</label><span>' . statusBadge($rider['status']) . ' ' . statusBadge($rider['duty_status']) . '</span></div>
    <div class="detail-item"><label>Total Deliveries</label><span>' . (int) $rider['total_deliveries'] . '</span></div>
    <div class="detail-item"><label>Avg Rating</label><span>' . renderStars((float) $rider['avg_rating']) . '</span></div>
</div>
<form method="POST" action="' . APP_URL . '/riders.php" style="margin-top:1.5rem;">
    ' . csrfField() . '
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="rider_id" value="' . $rider['id'] . '">
    <h4 style="margin-bottom:0.75rem;color:var(--cyan-800);">Edit Profile</h4>
    <div class="form-row">
        <div class="form-group"><label>Full Name</label><input name="full_name" class="form-control" value="' . e($rider['full_name']) . '"></div>
        <div class="form-group"><label>Phone</label><input name="phone" class="form-control" value="' . e($rider['phone']) . '"></div>
        <div class="form-group"><label>Vehicle Type</label><input name="vehicle_type" class="form-control" value="' . e($rider['vehicle_type']) . '"></div>
        <div class="form-group"><label>Plate Number</label><input name="vehicle_plate" class="form-control" value="' . e($rider['vehicle_plate'] ?? '') . '"></div>
        <div class="form-group"><label>License Number</label><input name="license_number" class="form-control" value="' . e($rider['license_number'] ?? '') . '"></div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
</form>
<div class="btn-group" style="margin-top:1rem;">
    <form method="POST" action="' . APP_URL . '/riders.php" style="display:inline;">
        ' . csrfField() . '
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="rider_id" value="' . $rider['id'] . '">
        <select name="status" class="form-control" style="display:inline-block;width:auto;">
            <option value="active"' . ($rider['status'] === 'active' ? ' selected' : '') . '>Active</option>
            <option value="on_duty"' . ($rider['status'] === 'on_duty' ? ' selected' : '') . '>On Duty</option>
            <option value="inactive"' . ($rider['status'] === 'inactive' ? ' selected' : '') . '>Inactive</option>
            <option value="suspended"' . ($rider['status'] === 'suspended' ? ' selected' : '') . '>Suspended</option>
        </select>
        <button type="submit" class="btn btn-warning btn-sm">Update Status</button>
    </form>
    <form method="POST" action="' . APP_URL . '/riders.php" style="display:inline;" onsubmit="return confirmAction(\'Reset password to password123?\');">
        ' . csrfField() . '
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="rider_id" value="' . $rider['id'] . '">
        <button type="submit" class="btn btn-outline btn-sm">Reset Password</button>
    </form>
</div>';

$reviewStmt = $pdo->prepare('SELECT * FROM customer_reviews WHERE rider_id = ? ORDER BY created_at DESC LIMIT 20');
$reviewStmt->execute([$id]);
$reviews = $reviewStmt->fetchAll();

$reviewsHtml = '<div class="rating-summary"><div class="big-rating">' . number_format((float) $rider['avg_rating'], 1) . '</div>' . renderStars((float) $rider['avg_rating']) . '<p>' . (int) $rider['total_deliveries'] . ' completed deliveries</p></div>';

foreach ($reviews as $review) {
    $reviewsHtml .= '<div class="review-item' . ($review['is_flagged'] ? ' flagged' : '') . '">';
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

echo json_encode([
    'success' => true,
    'rider' => $rider,
    'overview_html' => $overviewHtml,
    'reviews_html' => $reviewsHtml,
    'earnings_html' => $earningsHtml,
]);
