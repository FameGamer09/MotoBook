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

$stmt = $pdo->prepare('
    SELECT ps.*, sc.name AS category_name
    FROM partnership_stores ps
    LEFT JOIN store_categories sc ON sc.id = ps.category_id
    WHERE ps.id = ?
');
$stmt->execute([$id]);
$store = $stmt->fetch();

if (!$store) {
    echo json_encode(['success' => false]);
    exit;
}

$salesStmt = $pdo->prepare('
    SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total_sales,
           COUNT(*) AS order_count
    FROM orders WHERE store_id = ? AND order_status = ?
');
$salesStmt->execute([$id, 'delivered']);
$sales = $salesStmt->fetch();

$monthSalesStmt = $pdo->prepare('
    SELECT COALESCE(SUM(order_total + delivery_fee), 0) AS total
    FROM orders WHERE store_id = ? AND order_status = ?
    AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
');
$monthSalesStmt->execute([$id, 'delivered']);
$monthSales = (float) $monthSalesStmt->fetch()['total'];

$categories = $pdo->query('SELECT id, name FROM store_categories ORDER BY name')->fetchAll();

$permitLink = $store['business_permit']
    ? '<a href="' . APP_URL . '/' . e($store['business_permit']) . '" target="_blank">View Permit</a>'
    : '—';

$detailHtml = '
<div class="detail-grid">
    <div class="detail-item"><label>Store Name</label><span>' . e($store['store_name']) . '</span></div>
    <div class="detail-item"><label>Category</label><span>' . e($store['category_name'] ?? 'N/A') . '</span></div>
    <div class="detail-item"><label>Status</label><span>' . statusBadge($store['status']) . '</span></div>
    <div class="detail-item"><label>Operating Hours</label><span>' . e($store['operating_hours']) . '</span></div>
    <div class="detail-item"><label>Commission Rate</label><span>' . number_format((float) $store['commission_rate'], 2) . '%</span></div>
    <div class="detail-item"><label>Total Orders</label><span>' . (int) $store['total_orders'] . '</span></div>
    <div class="detail-item"><label>Lifetime Sales</label><span>' . formatMoney((float) $sales['total_sales']) . '</span></div>
    <div class="detail-item"><label>This Month Sales</label><span>' . formatMoney($monthSales) . '</span></div>
    <div class="detail-item"><label>Address</label><span>' . e($store['branch_address']) . '</span></div>
    <div class="detail-item"><label>Coordinates</label><span>' . e($store['latitude'] ?? '—') . ', ' . e($store['longitude'] ?? '—') . '</span></div>
    <div class="detail-item"><label>Contact</label><span>' . e($store['contact_phone'] ?? '—') . ' / ' . e($store['contact_email'] ?? '—') . '</span></div>
    <div class="detail-item"><label>Owner</label><span>' . e($store['owner_name'] ?? '—') . ' (' . e($store['owner_email'] ?? '—') . ')</span></div>
    <div class="detail-item"><label>TIN</label><span>' . e($store['tax_id'] ?? '—') . '</span></div>
    <div class="detail-item"><label>Business Permit</label><span>' . $permitLink . '</span></div>
</div>

<form method="POST" action="' . APP_URL . '/stores.php" style="margin-top:1.5rem;">
    ' . csrfField() . '
    <input type="hidden" name="action" value="update_store">
    <input type="hidden" name="store_id" value="' . $store['id'] . '">
    <h4 style="margin-bottom:0.75rem;color:var(--cyan-800);">Edit Store Configuration</h4>
    <div class="form-row">
        <div class="form-group"><label>Store Name</label><input name="store_name" class="form-control" value="' . e($store['store_name']) . '"></div>
        <div class="form-group"><label>Category</label><select name="category_id" class="form-control">';

foreach ($categories as $cat) {
    $selected = (int) $store['category_id'] === (int) $cat['id'] ? ' selected' : '';
    $detailHtml .= '<option value="' . $cat['id'] . '"' . $selected . '>' . e($cat['name']) . '</option>';
}

$detailHtml .= '</select></div>
        <div class="form-group"><label>Address</label><input name="branch_address" class="form-control" value="' . e($store['branch_address']) . '"></div>
        <div class="form-group"><label>Latitude</label><input name="latitude" class="form-control" value="' . e($store['latitude'] ?? '') . '"></div>
        <div class="form-group"><label>Longitude</label><input name="longitude" class="form-control" value="' . e($store['longitude'] ?? '') . '"></div>
        <div class="form-group"><label>Phone</label><input name="contact_phone" class="form-control" value="' . e($store['contact_phone'] ?? '') . '"></div>
        <div class="form-group"><label>Email</label><input name="contact_email" class="form-control" value="' . e($store['contact_email'] ?? '') . '"></div>
        <div class="form-group"><label>Hours</label><input name="operating_hours" class="form-control" value="' . e($store['operating_hours']) . '"></div>
        <div class="form-group"><label>Commission %</label><input name="commission_rate" type="number" class="form-control" value="' . e((string) $store['commission_rate']) . '"></div>
        <div class="form-group"><label>TIN</label><input name="tax_id" class="form-control" value="' . e($store['tax_id'] ?? '') . '"></div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Save Configuration</button>
</form>';

echo json_encode([
    'success' => true,
    'store' => $store,
    'detail_html' => $detailHtml,
]);
