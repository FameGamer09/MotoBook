<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    $_SESSION['flash']['error'] = 'Invalid order ID.';
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

function hasRiderTables(PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT TABLE_NAME) FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('rider_pod_records', 'riders')");
    $stmt->execute();
    $cached = ((int)$stmt->fetchColumn()) >= 2;
    return $cached;
}

$riderMigrationReady = hasRiderTables($pdo);

$orderStmt = $pdo->prepare('SELECT o.*, ps.store_name, r.full_name AS rider_name, r.rider_code
    FROM orders o
    LEFT JOIN partnership_stores ps ON ps.id = o.store_id
    LEFT JOIN riders r ON r.id = o.rider_id
    WHERE o.id = ? LIMIT 1');
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    $_SESSION['flash']['error'] = 'Order #' . $orderId . ' not found.';
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

$riderOrder = null;
$riderPod = null;
$telemetryLogs = [];
$incidents = [];

if ($riderMigrationReady) {
    try {
        $roStmt = $pdo->prepare('SELECT id, rider_id, order_code, state, payout_amount, tip_amount, cod_amount,
            offer_received_at, accepted_at, pickup_arrived_at, verified_at, dropoff_arrived_at, completed_at,
            merchant_name, merchant_address, dropoff_name, dropoff_address, dropoff_phone, special_notes,
            payment_method, payment_reference, payment_status
            FROM rider_orders WHERE shared_order_id = ? LIMIT 1');
        $roStmt->execute([$orderId]);
        $riderOrder = $roStmt->fetch() ?: null;

        if ($riderOrder) {
            $roId = (int)$riderOrder['id'];
            $podStmt = $pdo->prepare('SELECT proof_image_path, proof_image_lat, proof_image_lng, proof_captured_at,
                cod_collected_amt, cod_change_due, cod_confirmed, signature_path, signature_name, dropoff_notes, handoff_mode,
                payment_method, payment_reference, payment_confirmed,
                payment_proof_image_path, payment_proof_image_lat, payment_proof_image_lng, payment_captured_at, created_at
                FROM rider_pod_records WHERE rider_order_id = ? LIMIT 1');
            $podStmt->execute([$roId]);
            $riderPod = $podStmt->fetch() ?: null;

            $telStmt = $pdo->prepare('SELECT lat, lng, heading, speed_kmh, accuracy_m, motion_state, is_offline_batch, recorded_at
                FROM rider_location_logs WHERE rider_order_id = ? ORDER BY recorded_at DESC LIMIT 20');
            $telStmt->execute([$roId]);
            $telemetryLogs = $telStmt->fetchAll();

            $incStmt = $pdo->prepare('SELECT incident_code, detail, created_at FROM rider_order_incidents WHERE rider_order_id = ? ORDER BY id DESC LIMIT 10');
            $incStmt->execute([$roId]);
            $incidents = $incStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $riderMigrationReady = false;
    }
}

$pageTitle = 'Proof of Delivery — Order #' . e($order['order_number'] ?? (string)$orderId);
include __DIR__ . '/includes/header.php';

function formatGps(float $val): string
{
    return number_format($val, 6, '.', '');
}

function formatSpeed($val): string
{
    if ($val === null) return '—';
    return number_format((float)$val, 1) . ' km/h';
}

function formatAccuracy($val): string
{
    if ($val === null) return '—';
    return '±' . number_format((float)$val, 1) . 'm';
}

function incidentBadge(string $code): string
{
    $map = [
        'STORE_CLOSED' => 'badge-danger',
        'LONG_STORE_WAIT_15' => 'badge-warning',
        'ORDER_MISSING' => 'badge-danger',
        'ITEM_UNAVAILABLE' => 'badge-warning',
        'MERCHANT_CONGESTION' => 'badge-warning',
        'ITEM_DAMAGED' => 'badge-danger',
        'CUSTOMER_UNREACHABLE' => 'badge-warning',
        'WRONG_ADDRESS' => 'badge-danger',
        'MECHANICAL_ISSUE' => 'badge-danger',
        'OTHER' => 'badge-muted',
    ];
    $class = $map[$code] ?? 'badge-muted';
    $label = ucwords(strtolower(str_replace('_', ' ', $code)));
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function paymentMethodLabel(string $m): string
{
    $map = [
        'COD' => 'Cash on Delivery',
        'GCASH' => 'GCash',
        'MAYA' => 'Maya',
        'GRABPAY' => 'GrabPay',
        'SHOPEEPAY' => 'ShopeePay',
        'CARD' => 'Credit/Debit Card',
        'BANK_TRANSFER' => 'Bank Transfer',
        'PREPAID' => 'Prepaid Wallet',
    ];
    return $map[$m] ?? e($m);
}

function paymentStatusBadge(string $s): string
{
    $map = [
        'UNPAID' => 'badge-danger',
        'PENDING_VERIFICATION' => 'badge-warning',
        'PAID' => 'badge-success',
    ];
    $class = $map[$s] ?? 'badge-muted';
    $label = ucwords(strtolower(str_replace('_', ' ', $s)));
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger"><?= e($msg) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
    <div>
        <a href="<?= APP_URL ?>/orders.php" class="btn btn-outline btn-sm" style="margin-right:0.5rem;">
            <i data-lucide="arrow-left"></i> Back to Orders
        </a>
        <span style="font-size:1.1rem;font-weight:600;">Compliance Audit Viewer</span>
    </div>
    <div style="display:flex;gap:0.35rem;align-items:center;">
        <span class="text-muted">Generated: <?= e(date('M d, Y h:i:s A')) ?></span>
        <button class="btn btn-primary-outline btn-sm" onclick="window.print()">
            <i data-lucide="printer"></i> Print Report
        </button>
    </div>
</div>

<?php if (!$riderMigrationReady): ?>
<div class="card" style="border-left:4px solid var(--warning);">
    <div class="card-body" style="display:flex;gap:1rem;align-items:flex-start;">
        <div style="font-size:2rem;flex-shrink:0;">⚠️</div>
        <div>
            <h3 style="margin:0 0 0.4rem 0;color:var(--warning);">Rider migration not yet applied.</h3>
            <p style="margin:0;color:var(--gray-600);">
                The <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;">riders</code> and/or
                <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;">rider_pod_records</code> tables
                are missing from the database. Run the rider migration at
                <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;">A-rider/migrations/001_init_rider_schema.sql</code>
                to enable proof-of-delivery enrichment and telemetry data.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">

    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(8,145,178,0.12);display:flex;align-items:center;justify-content:center;color:var(--cyan-700);">
                <i data-lucide="clipboard-list"></i>
            </div>
            <div>
                <h2 style="margin:0;">Order Summary</h2>
                <small class="text-muted">Core order metadata &amp; assignment</small>
            </div>
        </div>
        <div class="card-body">
            <table class="data-table" style="border:0;">
                <tbody>
                    <tr>
                        <td style="width:40%;color:var(--gray-500);font-size:0.82rem;">Order #</td>
                        <td><strong><?= e($order['order_number'] ?? 'ORD-' . $orderId) ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Status</td>
                        <td><?= statusBadge((string)($order['order_status'] ?? 'pending')) ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Store</td>
                        <td><?= e($order['store_name'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Customer</td>
                        <td><?= e($order['customer_name'] ?? '—') ?>
                            <?php if (!empty($order['customer_phone'])): ?>
                                <small class="text-muted"> · <?= e($order['customer_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Delivery Address</td>
                        <td style="line-height:1.4;"><?= nl2br(e($order['delivery_address'] ?? '—')) ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Assigned Rider</td>
                        <td>
                            <?php if (!empty($order['rider_name'])): ?>
                                <strong><?= e($order['rider_name']) ?></strong>
                                <?php if (!empty($order['rider_code'])): ?>
                                    <small class="text-muted"> · <?= e($order['rider_code']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <em class="text-muted">Unassigned</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Rider Order State</td>
                        <td>
                            <?php if ($riderOrder): ?>
                                <?= statusBadge(strtolower((string)$riderOrder['state'])) ?>
                            <?php else: ?>
                                <em class="text-muted">No rider order sync</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Order Total</td>
                        <td><strong><?= formatMoney((float)($order['order_total'] ?? 0)) ?></strong>
                            <small class="text-muted"> (Fee: <?= formatMoney((float)($order['delivery_fee'] ?? 0)) ?>)</small>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Placed</td>
                        <td><?= e(formatDateTime($order['created_at'] ?? null)) ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Delivered</td>
                        <td><?= e(formatDateTime($order['delivered_at'] ?? null)) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;color:var(--green-700);">
                <i data-lucide="camera"></i>
            </div>
            <div>
                <h2 style="margin:0;">Delivery Proof</h2>
                <small class="text-muted">Drop-off photo · GPS watermark · timestamp</small>
            </div>
        </div>
        <div class="card-body">
            <?php if ($riderPod && !empty($riderPod['proof_image_path'])): ?>
                <div style="border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;margin-bottom:0.85rem;background:#f8fafc;">
                    <img src="<?= e($riderPod['proof_image_path']) ?>" alt="Delivery proof" style="width:100%;height:260px;object-fit:cover;display:block;">
                </div>
            <?php else: ?>
                <div style="border:2px dashed var(--gray-300);border-radius:10px;padding:2rem;text-align:center;margin-bottom:0.85rem;background:#f8fafc;">
                    <div style="font-size:2.4rem;color:var(--gray-400);margin-bottom:0.35rem;">🖼️</div>
                    <p style="margin:0;color:var(--gray-500);font-size:0.88rem;">No delivery photo submitted yet.</p>
                </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.6rem;">
                <div style="background:var(--gray-50);padding:0.65rem 0.8rem;border-radius:8px;">
                    <div class="text-muted" style="font-size:0.72rem;margin-bottom:0.1rem;">GPS Latitude</div>
                    <div style="font-family:ui-monospace,Consolas,monospace;font-weight:600;font-size:0.88rem;">
                        <?php if ($riderPod && $riderPod['proof_image_lat'] !== null): ?>
                            <?= e(formatGps((float)$riderPod['proof_image_lat'])) ?>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
                <div style="background:var(--gray-50);padding:0.65rem 0.8rem;border-radius:8px;">
                    <div class="text-muted" style="font-size:0.72rem;margin-bottom:0.1rem;">GPS Longitude</div>
                    <div style="font-family:ui-monospace,Consolas,monospace;font-weight:600;font-size:0.88rem;">
                        <?php if ($riderPod && $riderPod['proof_image_lng'] !== null): ?>
                            <?= e(formatGps((float)$riderPod['proof_image_lng'])) ?>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:0.6rem;background:var(--gray-50);padding:0.65rem 0.8rem;border-radius:8px;">
                <div class="text-muted" style="font-size:0.72rem;margin-bottom:0.1rem;">Photo Captured At</div>
                <div style="font-weight:600;font-size:0.88rem;">
                    <?= $riderPod && !empty($riderPod['proof_captured_at']) ? e(formatDateTime($riderPod['proof_captured_at'])) : '—' ?>
                </div>
            </div>
            <?php if ($riderPod && !empty($riderPod['handoff_mode'])): ?>
            <div style="margin-top:0.6rem;background:var(--gray-50);padding:0.65rem 0.8rem;border-radius:8px;">
                <div class="text-muted" style="font-size:0.72rem;margin-bottom:0.1rem;">Handoff Mode</div>
                <div style="font-weight:600;font-size:0.88rem;"><?= e(ucwords(strtolower(str_replace('_', ' ', (string)$riderPod['handoff_mode'])))) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($riderPod && !empty($riderPod['dropoff_notes'])): ?>
            <div style="margin-top:0.6rem;background:rgba(8,145,178,0.06);padding:0.65rem 0.8rem;border-radius:8px;border-left:3px solid var(--cyan-500);">
                <div class="text-muted" style="font-size:0.72rem;margin-bottom:0.1rem;">Drop-off Notes</div>
                <div style="font-size:0.85rem;line-height:1.45;"><?= nl2br(e($riderPod['dropoff_notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(59,130,246,0.12);display:flex;align-items:center;justify-content:center;color:var(--blue-700);">
                <i data-lucide="receipt"></i>
            </div>
            <div>
                <h2 style="margin:0;">Payment Proof</h2>
                <small class="text-muted">GCash/POS screenshot · ref · status</small>
            </div>
        </div>
        <div class="card-body">
            <?php
            $payMethod = $riderPod['payment_method'] ?? $riderOrder['payment_method'] ?? $order['payment_method'] ?? 'COD';
            $payRef = $riderPod['payment_reference'] ?? $riderOrder['payment_reference'] ?? $order['payment_ref'] ?? null;
            $payStatus = $riderOrder['payment_status'] ?? $order['payment_status'] ?? 'UNPAID';
            $payConfirmed = !empty($riderPod['payment_confirmed']) || (!empty($riderPod['cod_confirmed']) && strtoupper((string)$payMethod) === 'COD');
            ?>
            <?php if ($riderPod && !empty($riderPod['payment_proof_image_path'])): ?>
                <div style="border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;margin-bottom:0.85rem;background:#f8fafc;">
                    <img src="<?= e($riderPod['payment_proof_image_path']) ?>" alt="Payment proof" style="width:100%;height:220px;object-fit:cover;display:block;">
                </div>
            <?php else: ?>
                <div style="border:2px dashed var(--gray-300);border-radius:10px;padding:1.5rem;text-align:center;margin-bottom:0.85rem;background:#f8fafc;">
                    <div style="font-size:2.2rem;color:var(--gray-400);margin-bottom:0.35rem;">💳</div>
                    <p style="margin:0;color:var(--gray-500);font-size:0.85rem;">No payment screenshot uploaded.</p>
                </div>
            <?php endif; ?>

            <table class="data-table" style="border:0;">
                <tbody>
                    <tr>
                        <td style="width:45%;color:var(--gray-500);font-size:0.82rem;">Method</td>
                        <td><strong><?= e(paymentMethodLabel(strtoupper((string)$payMethod))) ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Reference #</td>
                        <td style="font-family:ui-monospace,Consolas,monospace;">
                            <?= !empty($payRef) ? e($payRef) : '<em class="text-muted">—</em>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Status</td>
                        <td><?= paymentStatusBadge(strtoupper((string)$payStatus)) ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Staff Confirmation</td>
                        <td>
                            <?php if ($payConfirmed): ?>
                                <span class="badge badge-success">✓ Confirmed</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Awaiting verification</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (strtoupper((string)$payMethod) === 'COD'): ?>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">COD Collected</td>
                        <td><strong><?= formatMoney((float)($riderPod['cod_collected_amt'] ?? 0)) ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Change Due</td>
                        <td><?= formatMoney((float)($riderPod['cod_change_due'] ?? 0)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($riderPod && $riderPod['payment_proof_image_lat'] !== null): ?>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Screenshot GPS</td>
                        <td style="font-family:ui-monospace,Consolas,monospace;font-size:0.8rem;">
                            <?= e(formatGps((float)$riderPod['payment_proof_image_lat'])) ?>,
                            <?= e(formatGps((float)$riderPod['payment_proof_image_lng'])) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="color:var(--gray-500);font-size:0.82rem;">Captured</td>
                        <td><?= !empty($riderPod['payment_captured_at']) ? e(formatDateTime($riderPod['payment_captured_at'])) : '—' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(168,85,247,0.12);display:flex;align-items:center;justify-content:center;color:var(--purple-700);">
                <i data-lucide="pen-tool"></i>
            </div>
            <div>
                <h2 style="margin:0;">Customer Signature</h2>
                <small class="text-muted">Signatory name &amp; captured signature image</small>
            </div>
        </div>
        <div class="card-body">
            <?php if ($riderPod && !empty($riderPod['signature_path'])): ?>
                <div style="border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;margin-bottom:0.85rem;background:linear-gradient(135deg,#fff 0%,#fafafa 100%);">
                    <img src="<?= e($riderPod['signature_path']) ?>" alt="Customer signature" style="width:100%;height:200px;object-fit:contain;padding:1rem;display:block;">
                </div>
            <?php else: ?>
                <div style="border:2px dashed var(--gray-300);border-radius:10px;padding:2rem;text-align:center;margin-bottom:0.85rem;background:#fafafa;">
                    <div style="font-size:2.4rem;color:var(--gray-400);margin-bottom:0.35rem;">✍️</div>
                    <p style="margin:0;color:var(--gray-500);font-size:0.88rem;">No customer signature on file.</p>
                </div>
            <?php endif; ?>

            <div style="background:var(--gray-50);padding:0.8rem 1rem;border-radius:8px;">
                <div class="text-muted" style="font-size:0.72rem;margin-bottom:0.2rem;">Signatory Name</div>
                <?php if ($riderPod && !empty($riderPod['signature_name'])): ?>
                    <div style="font-weight:600;font-size:1rem;"><?= e($riderPod['signature_name']) ?></div>
                <?php else: ?>
                    <em class="text-muted">Not provided</em>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<div class="card" style="margin-top:1rem;">
    <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;justify-content:space-between;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;color:var(--amber-700);">
                <i data-lucide="route"></i>
            </div>
            <div>
                <h2 style="margin:0;">Telemetry Breadcrumb</h2>
                <small class="text-muted">Last <?= count($telemetryLogs) ?> location pings · time / lat / lng / speed / accuracy</small>
            </div>
        </div>
        <span class="badge badge-info"><?= count($telemetryLogs) ?> rows</span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($telemetryLogs)): ?>
            <div style="padding:2rem;text-align:center;color:var(--gray-500);">
                <div style="font-size:2rem;margin-bottom:0.35rem;">🛰️</div>
                <p style="margin:0;font-size:0.88rem;">No telemetry logs recorded for this delivery.</p>
            </div>
        <?php else: ?>
            <div style="max-height:380px;overflow-y:auto;">
                <table class="data-table" style="border:0;margin:0;">
                    <thead style="position:sticky;top:0;background:var(--gray-50);z-index:1;">
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Timestamp</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Speed</th>
                            <th>Accuracy</th>
                            <th>Motion</th>
                            <th style="width:60px;">Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_values($telemetryLogs) as $i => $log): ?>
                            <tr>
                                <td style="color:var(--gray-500);font-size:0.8rem;"><?= $i + 1 ?></td>
                                <td style="font-size:0.82rem;white-space:nowrap;"><?= e(formatDateTime($log['recorded_at'] ?? null)) ?></td>
                                <td style="font-family:ui-monospace,Consolas,monospace;font-size:0.78rem;"><?= e(formatGps((float)$log['lat'])) ?></td>
                                <td style="font-family:ui-monospace,Consolas,monospace;font-size:0.78rem;"><?= e(formatGps((float)$log['lng'])) ?></td>
                                <td style="font-size:0.82rem;"><?= e(formatSpeed($log['speed_kmh'] ?? null)) ?></td>
                                <td style="font-size:0.82rem;"><?= e(formatAccuracy($log['accuracy_m'] ?? null)) ?></td>
                                <td>
                                    <?php
                                    $motion = $log['motion_state'] ?? '';
                                    $motionMap = ['MOVING' => 'badge-info', 'IDLE' => 'badge-warning', 'STOPPED' => 'badge-muted'];
                                    $motionClass = $motionMap[$motion] ?? 'badge-muted';
                                    $motionLabel = ucwords(strtolower($motion));
                                    ?>
                                    <?php if ($motion): ?><span class="badge <?= $motionClass ?>" style="font-size:0.68rem;"><?= e($motionLabel) ?></span><?php else: ?>—<?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?= !empty($log['is_offline_batch']) ? '<span class="badge badge-warning" style="font-size:0.62rem;">OFFLINE</span>' : '<span class="text-muted" style="font-size:0.72rem;">Live</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;justify-content:space-between;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(239,68,68,0.12);display:flex;align-items:center;justify-content:center;color:var(--red-700);">
                <i data-lucide="alert-triangle"></i>
            </div>
            <div>
                <h2 style="margin:0;">Incident History</h2>
                <small class="text-muted">Last 10 rider-reported incidents for this order</small>
            </div>
        </div>
        <span class="badge <?= empty($incidents) ? 'badge-success' : 'badge-danger' ?>"><?= count($incidents) ?> incident<?= count($incidents) === 1 ? '' : 's' ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($incidents)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--gray-500);border:2px dashed var(--gray-200);border-radius:10px;background:#fafafa;">
                <div style="font-size:2rem;margin-bottom:0.35rem;">✅</div>
                <p style="margin:0;font-size:0.88rem;">No incidents reported for this order. Clean delivery.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:0.55rem;">
                <?php foreach ($incidents as $inc): ?>
                    <div style="display:flex;gap:0.75rem;padding:0.8rem 1rem;border:1px solid var(--gray-200);border-radius:10px;background:#fff;border-left:4px solid var(--red-400);">
                        <div style="flex-shrink:0;margin-top:0.1rem;">
                            <?= incidentBadge((string)$inc['incident_code']) ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <?php if (!empty($inc['detail'])): ?>
                                <p style="margin:0 0 0.25rem 0;font-size:0.86rem;line-height:1.45;"><?= nl2br(e($inc['detail'])) ?></p>
                            <?php else: ?>
                                <p style="margin:0 0 0.25rem 0;color:var(--gray-500);font-size:0.84rem;"><em>No additional detail provided.</em></p>
                            <?php endif; ?>
                            <small class="text-muted" style="font-size:0.74rem;">Logged: <?= e(formatDateTime($inc['created_at'] ?? null)) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .btn:not(.btn-primary-outline:last-child) { display:none !important; }
    .main-content { margin-left:0 !important; }
    .card { break-inside:avoid; page-break-inside:avoid; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
