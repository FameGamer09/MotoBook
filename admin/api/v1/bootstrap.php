<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/config/app.php';
require_once dirname(__DIR__, 2).'/config/database.php';
require_once dirname(__DIR__, 2).'/includes/functions.php';
require_once dirname(__DIR__, 2).'/includes/migrate_ops.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function settingsMap(PDO $pdo): array
{
    $rows = $pdo->query('SELECT setting_key, setting_value, setting_label, is_locked_for_staff FROM platform_settings')->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[$row['setting_key']] = $row;
    }

    return $map;
}

function settingValue(PDO $pdo, string $key, string $default = '0'): string
{
    $map = settingsMap($pdo);

    return (string) ($map[$key]['setting_value'] ?? $default);
}

function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return $matches[1];
    }

    return $_GET['token'] ?? null;
}

function requireApiUser(PDO $pdo): array
{
    $token = bearerToken();
    if (! $token) {
        apiJson(['success' => false, 'message' => 'Missing API token.'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT * FROM api_tokens WHERE token_hash = ? AND expires_at > NOW()');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (! $row) {
        apiJson(['success' => false, 'message' => 'Invalid or expired API token.'], 401);
    }

    $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$row['id']]);

    return $row;
}

function forbidStaffAccounts(array $user): void
{
    if (in_array($user['actor_type'], ['platform_staff', 'store_staff', 'store_owner'], true)) {
        apiJson(['success' => false, 'message' => 'Management staff cannot create or remove staff accounts.'], 403);
    }
}

function storeScopeId(array $user): ?int
{
    if (in_array($user['actor_type'], ['store_staff', 'store_owner'], true)) {
        $pdo = getDBConnection();
        if ($user['actor_type'] === 'store_owner') {
            return (int) $user['actor_id'];
        }
        $stmt = $pdo->prepare('SELECT store_id FROM staff WHERE id = ?');
        $stmt->execute([$user['actor_id']]);

        return (int) ($stmt->fetchColumn() ?: 0) ?: null;
    }

    return null;
}

function delayThreshold(PDO $pdo): int
{
    return max(5, (int) settingValue($pdo, 'delay_threshold_minutes', '35'));
}

function decorateOrder(array $order, int $threshold): array
{
    $terminal = in_array($order['order_status'], ['delivered', 'cancelled'], true);
    $age = (int) ((time() - strtotime((string) $order['created_at'])) / 60);
    $order['age_minutes'] = $age;
    $order['is_delayed'] = ! $terminal && ($order['order_status'] === 'delayed' || $age > $threshold);
    $order['display_status'] = $order['is_delayed'] && $order['order_status'] !== 'delayed'
        ? 'delayed'
        : $order['order_status'];

    return $order;
}

function issueToken(PDO $pdo, string $actorType, int $actorId, string $email): string
{
    $plain = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO api_tokens (token_hash, actor_type, actor_id, actor_email, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR))');
    $stmt->execute([hash('sha256', $plain), $actorType, $actorId, $email]);

    return $plain;
}

function logOrderEvent(PDO $pdo, int $orderId, string $type, string $notes, string $actorEmail): void
{
    $pdo->prepare('INSERT INTO order_events (order_id, event_type, notes, actor_email) VALUES (?, ?, ?, ?)')
        ->execute([$orderId, $type, $notes, $actorEmail]);
}

function hasRiderSchema(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_orders' LIMIT 1");
    $stmt->execute();
    $cache = ((int) $stmt->fetchColumn()) > 0;

    return $cache;
}

function syncLegacyOrderToRider(PDO $pdo, int $legacyOrderId, int $riderId, string $actorEmail = ''): ?array
{
    if (! hasRiderSchema($pdo)) {
        return null;
    }
    $stmtLegacy = $pdo->prepare('SELECT o.*, ps.store_name AS merchant_name,
        ps.branch_address AS merchant_address, ps.latitude AS merchant_lat, ps.longitude AS merchant_lng
        FROM orders o
        LEFT JOIN partnership_stores ps ON ps.id = o.store_id
        WHERE o.id = ? LIMIT 1');
    $stmtLegacy->execute([$legacyOrderId]);
    $legacy = $stmtLegacy->fetch();
    if (! $legacy) {
        return null;
    }
    $stmtItems = $pdo->prepare('SELECT item_name_snapshot AS name, qty, unit_price_snapshot AS unit_price, subtotal_snapshot AS subtotal
        FROM order_items WHERE order_id = ? ORDER BY id');
    $stmtItems->execute([$legacyOrderId]);
    $items = $stmtItems->fetchAll();
    $itemsJson = $items ? json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $orderCode = ! empty($legacy['order_number']) ? (string) $legacy['order_number'] : ('MB-SYNC-'.$legacyOrderId);
    $deliveryFee = (float) ($legacy['delivery_fee'] ?? 0);
    $orderTotal = (float) ($legacy['order_total'] ?? 0);
    $payMethod = strtolower((string) ($legacy['payment_method'] ?? 'cash'));
    $codAmount = $payMethod === 'cash' ? $orderTotal : 0.0;
    $mappedPayMethod = 'COD';
    if (in_array($payMethod, ['gcash', 'g-cash', 'gcash_qr', 'qr_ph', 'qrcode'], true)) {
        $mappedPayMethod = 'GCASH';
    } elseif (in_array($payMethod, ['maya', 'paymaya'], true)) {
        $mappedPayMethod = 'MAYA';
    } elseif (in_array($payMethod, ['grab', 'grabpay', 'grab_pay'], true)) {
        $mappedPayMethod = 'GRABPAY';
    } elseif (in_array($payMethod, ['shopeepay', 'shopee_pay', 'spaylater'], true)) {
        $mappedPayMethod = 'SHOPEEPAY';
    } elseif (in_array($payMethod, ['card', 'credit', 'debit', 'visa', 'mastercard'], true)) {
        $mappedPayMethod = 'CARD';
    } elseif (in_array($payMethod, ['bank', 'bank_transfer', 'instapay', 'pesonet'], true)) {
        $mappedPayMethod = 'BANK_TRANSFER';
    } elseif (in_array($payMethod, ['prepaid', 'wallet', 'credits'], true)) {
        $mappedPayMethod = 'PREPAID';
    }
    $mappedPayStatus = $codAmount > 0 ? 'UNPAID' : ((strtolower((string) ($legacy['order_status'] ?? 'placed')) === 'paid' || $payMethod !== 'cash') ? 'PAID' : 'UNPAID');
    $payout = max(0.0, $deliveryFee);
    $tip = 0.0;
    $now = date('Y-m-d H:i:s');
    $merchantOrderId = strtoupper(substr('MB'.substr(md5((string) $legacyOrderId), 0, 6), 0, 8));
    $storeId = ! empty($legacy['store_id']) ? (int) $legacy['store_id'] : null;
    $mName = ! empty($legacy['merchant_name']) ? (string) $legacy['merchant_name'] : ((string) ($legacy['store_name'] ?? 'Partner Store'));
    $mAddr = ! empty($legacy['merchant_address']) ? (string) $legacy['merchant_address'] : ((string) ($legacy['branch_address'] ?? ''));
    $mLat = ! empty($legacy['merchant_lat']) ? (float) $legacy['merchant_lat'] : null;
    $mLng = ! empty($legacy['merchant_lng']) ? (float) $legacy['merchant_lng'] : null;
    $dName = (string) ($legacy['customer_name'] ?? '');
    $dAddr = (string) ($legacy['delivery_address'] ?? '');
    $dPhone = ! empty($legacy['customer_phone']) ? (string) $legacy['customer_phone'] : null;
    $notes = (string) ($legacy['notes'] ?? '');
    $pdo->prepare("INSERT INTO rider_orders
        (rider_id, order_code, merchant_order_id, source_store_id, shared_order_id, source_system,
         merchant_name, merchant_lat, merchant_lng, merchant_address,
         dropoff_name, dropoff_lat, dropoff_lng, dropoff_address, dropoff_phone,
         total_distance_km, estimated_minutes, payout_amount, tip_amount, cod_amount,
         items_json, special_notes, state, accepted_at, offer_received_at, offer_expires_at,
         payment_method, payment_reference, payment_status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACCEPTED', NOW(), ?, NULL, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            rider_id = VALUES(rider_id),
            merchant_name = VALUES(merchant_name),
            merchant_lat = VALUES(merchant_lat), merchant_lng = VALUES(merchant_lng),
            merchant_address = VALUES(merchant_address),
            dropoff_name = VALUES(dropoff_name),
            dropoff_address = VALUES(dropoff_address), dropoff_phone = VALUES(dropoff_phone),
            payout_amount = VALUES(payout_amount), cod_amount = VALUES(cod_amount), items_json = VALUES(items_json),
            special_notes = VALUES(special_notes),
            state = CASE WHEN state IN ('COMPLETED','CANCELLED','OFFER_EXPIRED','OFFER_REJECTED') THEN state ELSE 'ACCEPTED' END,
            accepted_at = COALESCE(accepted_at, NOW()),
            offer_received_at = VALUES(offer_received_at), offer_expires_at = NULL,
            payment_method = VALUES(payment_method), payment_reference = VALUES(payment_reference),
            payment_status = VALUES(payment_status),
            updated_at = NOW()")
        ->execute([
            $riderId, $orderCode, $merchantOrderId, $storeId, $legacyOrderId, 'INHOUSE',
            $mName, $mLat, $mLng, $mAddr,
            $dName, null, null, $dAddr, $dPhone,
            0.0, 0, $payout, $tip, $codAmount,
            $itemsJson, $notes, $now,
            $mappedPayMethod, ! empty($legacy['payment_ref']) ? (string) $legacy['payment_ref'] : null, $mappedPayStatus,
        ]);
    $roId = null;
    $find = $pdo->prepare('SELECT id FROM rider_orders WHERE shared_order_id = ? LIMIT 1');
    $find->execute([$legacyOrderId]);
    $row = $find->fetch();
    if ($row) {
        $roId = (int) $row['id'];
    }
    if ($roId && $actorEmail !== '') {
        $pdo->prepare("INSERT IGNORE INTO order_events (order_id, event_type, notes, actor_email, created_at)
            VALUES (?, 'rider_sync', CONCAT('Synced to rider_orders #', ?), ?, NOW())")
            ->execute([$legacyOrderId, $roId, $actorEmail]);
    }

    return ['rider_order_id' => $roId, 'order_code' => $orderCode, 'state' => 'ACCEPTED'];
}
