<?php

declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/dispatch.php';

$pdo = riderAdminDB();
$reqPath = preg_replace('#^.*/A-rider/api#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$reqPath = '/'.trim($reqPath, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function _api_recursive_numericize(mixed $v): mixed
{
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $val) {
            $out[$k] = _api_recursive_numericize($val);
        }

        return $out;
    }
    if (is_string($v) && $v !== '' && preg_match('/^-?\d+(\.\d+)?$/', $v) === 1) {
        if (strpos($v, '.') !== false) {
            return (float) $v;
        }
        if (strlen($v) >= 10 && $v[0] !== '-') {
            return (float) $v;
        }
        $i = (int) $v;
        if ((string) $i === $v) {
            return $i;
        }

        return (float) $v;
    }

    return $v;
}

$apiSafeColCache = null;
$apiSafe = static function (PDO $pdo, string $table, string $col, string $def = "''", string $alias = '') use (&$apiSafeColCache): string {
    if ($apiSafeColCache === null) {
        $apiSafeColCache = [];
        $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('.$pdo->quote($table).')');
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $apiSafeColCache[strtolower($r['TABLE_NAME'])][strtolower($r['COLUMN_NAME'])] = true;
        }
    }
    $has = isset($apiSafeColCache[strtolower($table)][strtolower($col)]);
    $expr = $has ? "`$table`.`$col`" : $def;

    return $alias === '' ? $expr : "$expr AS `$alias`";
};

$send = function (mixed $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(_api_recursive_numericize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
};

try {
    if ($reqPath === '/session/me' && $method === 'GET') {
        if (empty($_SESSION['rider_id'])) {
            $send(['authenticated' => false], 200);
        }
        $t = 'riders';
        $stmt = $pdo->prepare('SELECT id, rider_code,
            COALESCE(NULLIF('.$apiSafe($pdo, $t, 'name', "''").", ''), NULLIF(".$apiSafe($pdo, $t, 'full_name', "''").", '')) AS name,
            email, ".$apiSafe($pdo, $t, 'phone', "''").' AS phone, '.$apiSafe($pdo, $t, 'vehicle_plate', "''").' AS vehicle_plate,
            COALESCE(NULLIF('.$apiSafe($pdo, $t, 'vehicle_type', "''").", ''), 'MOTORCYCLE') AS vehicle_type,
            COALESCE(".$apiSafe($pdo, $t, 'city', "''").", '') AS city,
            CASE UPPER(".$apiSafe($pdo, $t, 'status', "'OFFLINE'").")
                WHEN 'ACTIVE'    THEN 'ACTIVE'
                WHEN 'ON_DUTY'   THEN 'ON_SHIFT'
                WHEN 'ON_SHIFT'  THEN 'ON_SHIFT'
                WHEN 'SUSPENDED' THEN 'SUSPENDED'
                WHEN 'INACTIVE'  THEN 'INACTIVE'
                ELSE 'OFFLINE'
            END AS status,
            COALESCE(".$apiSafe($pdo, $t, 'duty_today_payout', '0').', 0) AS duty_today_payout,
            COALESCE('.$apiSafe($pdo, $t, 'completed_today', '0').', 0)   AS completed_today,
            COALESCE('.$apiSafe($pdo, $t, 'acceptance_rate', '0').', 0)   AS acceptance_rate,
            COALESCE('.$apiSafe($pdo, $t, 'active_hours_today', '0').', 0) AS active_hours_today,
            '.$apiSafe($pdo, $t, 'current_shift_started_at', 'NULL').' AS current_shift_started_at,
            '.$apiSafe($pdo, $t, 'gcash_mobile_number').' AS gcash_mobile_number,
            '.$apiSafe($pdo, $t, 'gcash_account_name').'  AS gcash_account_name,
            '.$apiSafe($pdo, $t, 'gcash_qr_data_uri').'   AS gcash_qr_data_uri,
            '.$apiSafe($pdo, $t, 'fcm_push_token').'      AS fcm_push_token,
            '.$apiSafe($pdo, $t, 'fcm_push_sub_json', "'null'").' AS fcm_push_sub_json
            FROM riders WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['rider_id']]);
        $rider = $stmt->fetch() ?: null;
        if (! $rider) {
            $send(['authenticated' => false], 404);
        }
        $settingsStmt = $pdo->prepare('SELECT address, vehicle_or_number, vehicle_cr_number, notification_preferences, quick_pin_hash FROM rider_account_settings WHERE rider_id = ? LIMIT 1');
        $settingsStmt->execute([(int) $rider['id']]);
        $settings = $settingsStmt->fetch() ?: [];
        $rider['address'] = $settings['address'] ?? '';
        $rider['vehicle_or_number'] = $settings['vehicle_or_number'] ?? '';
        $rider['vehicle_cr_number'] = $settings['vehicle_cr_number'] ?? '';
        $rider['notification_preferences'] = json_decode((string) ($settings['notification_preferences'] ?? ''), true) ?: ['in_app' => true, 'browser' => false];
        $rider['quick_pin_enabled'] = ! empty($settings['quick_pin_hash']);
        $rider['two_factor_enabled'] = false;
        $send(['authenticated' => true, 'rider' => $rider], 200);
    }

    if ($reqPath === '/session/push-token' && $method === 'POST') {
        $r = riderRequireAuth();
        $body = riderInputJson();
        $token = (string) ($body['token'] ?? $body['pushToken'] ?? '');
        $subJson = $body['subscription_json'] ?? $body['subscriptionJson'] ?? null;
        $subStr = null;
        if ($subJson !== null) {
            $subStr = is_string($subJson) ? $subJson : json_encode($subJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $stmt = $pdo->prepare('UPDATE riders SET fcm_push_token = ?, fcm_push_sub_json = ? WHERE id = ?');
        $stmt->execute([$token === '' ? null : $token, $subStr, (int) $r['id']]);
        $send(['ok' => true, 'fcm_push_token' => $token === '' ? null : $token], 200);
    }

    if ($reqPath === '/session/toggle-duty' && $method === 'POST') {
        $r = riderRequireAuth();
        $body = riderInputJson();
        $goOnline = ! empty($body['online']);
        $now = new DateTimeImmutable;
        $newStatus = $goOnline ? 'ON_SHIFT' : 'OFFLINE';
        $legacyDuty = $goOnline ? 'on_trip' : 'online';
        $legacyStat = $goOnline ? 'on_duty' : 'active';
        $pdo->beginTransaction();
        $setStarted = $goOnline ? 'current_shift_started_at = ?' : 'current_shift_started_at = current_shift_started_at';
        $stmt = $pdo->prepare('UPDATE riders SET status = ?, duty_status = ?, '.$setStarted.' WHERE id = ?');
        $params = $goOnline
            ? [$newStatus, $legacyDuty, $now->format('Y-m-d H:i:s'), (int) $r['id']]
            : [$newStatus, $legacyDuty, (int) $r['id']];
        $stmt->execute($params);
        $pdo->commit();
        if ($goOnline) {
            autoAssignPendingRiderOrders($pdo);
        }
        $send(['ok' => true, 'status' => $newStatus, 'started_at' => $goOnline ? $now->format('c') : ($r['current_shift_started_at'] ? (new DateTimeImmutable((string) $r['current_shift_started_at']))->format('c') : null)], 200);
    }

    if ($reqPath === '/session/logout' && $method === 'POST') {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $send(['ok' => true, 'redirect' => UNIFIED_LOGOUT_URL]);
    }

    riderRequireAuth();
    $riderId = (int) $_SESSION['rider_id'];

    if ($reqPath === '/session/profile' && $method === 'POST') {
        $body = riderInputJson();
        $name = trim((string) ($body['name'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));
        $city = trim((string) ($body['city'] ?? ''));
        $vehicleType = strtoupper(trim((string) ($body['vehicle_type'] ?? 'MOTORCYCLE')));
        $vehiclePlate = strtoupper(trim((string) ($body['vehicle_plate'] ?? '')));
        $vehicleOrNumber = trim((string) ($body['vehicle_or_number'] ?? ''));
        $vehicleCrNumber = trim((string) ($body['vehicle_cr_number'] ?? ''));
        $address = trim((string) ($body['address'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($phone) > 24 || mb_strlen($city) > 80 || mb_strlen($address) > 255) {
            $send(['error' => 'INVALID_PROFILE', 'message' => 'Check the required name and field lengths.'], 422);
        }
        if (! in_array($vehicleType, ['MOTORCYCLE', 'EBIKE', 'CAR', 'VAN'], true) || mb_strlen($vehiclePlate) > 32 || mb_strlen($vehicleOrNumber) > 64 || mb_strlen($vehicleCrNumber) > 64) {
            $send(['error' => 'INVALID_VEHICLE', 'message' => 'Check the vehicle type, plate, OR, and CR details.'], 422);
        }

        $columnStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riders'");
        $columnStmt->execute();
        $riderColumns = array_fill_keys(array_map('strtolower', array_column($columnStmt->fetchAll(), 'COLUMN_NAME')), true);
        $updates = [];
        $values = [];
        foreach (['name' => $name, 'full_name' => $name, 'phone' => $phone, 'city' => $city, 'vehicle_type' => $vehicleType, 'vehicle_plate' => $vehiclePlate] as $column => $value) {
            if (isset($riderColumns[$column])) {
                $updates[] = "`{$column}` = ?";
                $values[] = $value;
            }
        }
        if (isset($riderColumns['updated_at'])) {
            $updates[] = 'updated_at = NOW()';
        }
        if ($updates) {
            $values[] = $riderId;
            $pdo->prepare('UPDATE riders SET '.implode(', ', $updates).' WHERE id = ?')->execute($values);
        }
        $pdo->prepare('INSERT INTO rider_account_settings (rider_id, address, vehicle_or_number, vehicle_cr_number, updated_at)
            VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE address = VALUES(address), vehicle_or_number = VALUES(vehicle_or_number), vehicle_cr_number = VALUES(vehicle_cr_number), updated_at = NOW()')
            ->execute([$riderId, $address, $vehicleOrNumber, $vehicleCrNumber]);
        $send(['ok' => true, 'profile' => [
            'name' => $name,
            'phone' => $phone,
            'city' => $city,
            'address' => $address,
            'vehicle_type' => $vehicleType,
            'vehicle_plate' => $vehiclePlate,
            'vehicle_or_number' => $vehicleOrNumber,
            'vehicle_cr_number' => $vehicleCrNumber,
        ]], 200);
    }

    if ($reqPath === '/session/notifications' && $method === 'POST') {
        $body = riderInputJson();
        $preferences = $body['preferences'] ?? [];
        $safePreferences = [
            'in_app' => ! empty($preferences['in_app']),
            'browser' => ! empty($preferences['browser']),
        ];
        $pdo->prepare('INSERT INTO rider_account_settings (rider_id, notification_preferences, updated_at)
            VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE notification_preferences = VALUES(notification_preferences), updated_at = NOW()')
            ->execute([$riderId, json_encode($safePreferences, JSON_THROW_ON_ERROR)]);
        $send(['ok' => true, 'preferences' => $safePreferences], 200);
    }

    if ($reqPath === '/session/documents' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT document_type, original_name, mime_type, status, uploaded_at FROM rider_documents WHERE rider_id = ? ORDER BY document_type');
        $stmt->execute([$riderId]);
        $send(['documents' => $stmt->fetchAll()], 200);
    }

    if ($reqPath === '/session/documents' && $method === 'POST') {
        $body = riderInputJson();
        $documentType = (string) ($body['document_type'] ?? '');
        $originalName = basename(trim((string) ($body['file_name'] ?? 'document')));
        $dataUri = (string) ($body['data_uri'] ?? '');
        if (! in_array($documentType, ['license', 'insurance', 'permit'], true) || preg_match('#^data:(application/pdf|image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=_-]+)$#i', $dataUri, $matches) !== 1) {
            $send(['error' => 'INVALID_DOCUMENT', 'message' => 'Choose a PDF, PNG, JPG, or WebP document.'], 422);
        }
        $binary = base64_decode(strtr($matches[2], '-_', '+/'), true);
        if ($binary === false || strlen($binary) > 4 * 1024 * 1024) {
            $send(['error' => 'DOCUMENT_TOO_LARGE', 'message' => 'Documents must be smaller than 4 MB.'], 413);
        }
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (! isset($extensions[$mimeType])) {
            $send(['error' => 'INVALID_DOCUMENT_TYPE'], 422);
        }
        $directory = dirname(__DIR__, 3).'/motobook-private/rider-documents/'.$riderId;
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $send(['error' => 'DOCUMENT_STORAGE_UNAVAILABLE'], 500);
        }
        $storedName = bin2hex(random_bytes(16)).'.'.$extensions[$mimeType];
        $path = $directory.'/'.$storedName;
        if (file_put_contents($path, $binary, LOCK_EX) === false) {
            $send(['error' => 'DOCUMENT_STORAGE_UNAVAILABLE'], 500);
        }
        try {
            $pdo->prepare('INSERT INTO rider_documents (rider_id, document_type, original_name, stored_name, mime_type, status, uploaded_at, updated_at)
                VALUES (?, ?, ?, ?, ?, \'PENDING\', NOW(), NOW()) ON DUPLICATE KEY UPDATE original_name = VALUES(original_name), stored_name = VALUES(stored_name), mime_type = VALUES(mime_type), status = \'PENDING\', uploaded_at = NOW(), updated_at = NOW()')
                ->execute([$riderId, $documentType, mb_substr($originalName, 0, 255), $storedName, $mimeType]);
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
        $send(['ok' => true, 'document' => ['document_type' => $documentType, 'original_name' => $originalName, 'mime_type' => $mimeType, 'status' => 'PENDING']], 201);
    }

    if ($reqPath === '/session/support' && $method === 'POST') {
        $body = riderInputJson();
        $category = trim((string) ($body['category'] ?? 'Account'));
        $subject = trim((string) ($body['subject'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        if ($subject === '' || $message === '' || mb_strlen($subject) > 120 || mb_strlen($message) > 4000) {
            $send(['error' => 'INVALID_SUPPORT_REQUEST', 'message' => 'Enter a subject and a message under 4,000 characters.'], 422);
        }
        $recent = $pdo->prepare('SELECT COUNT(*) FROM rider_support_requests WHERE rider_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $recent->execute([$riderId]);
        if ((int) $recent->fetchColumn() >= 5) {
            $send(['error' => 'SUPPORT_RATE_LIMITED', 'message' => 'Please wait before sending another request.'], 429);
        }
        $stmt = $pdo->prepare('INSERT INTO rider_support_requests (rider_id, category, subject, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$riderId, mb_substr($category, 0, 80), mb_substr($subject, 0, 120), $message]);
        $send(['ok' => true, 'request_id' => (int) $pdo->lastInsertId()], 201);
    }

    if ($reqPath === '/session/security/password' && $method === 'POST') {
        $body = riderInputJson();
        $currentPassword = (string) ($body['current_password'] ?? '');
        $newPassword = (string) ($body['new_password'] ?? '');
        if (! riderPasswordMatches($pdo, $riderId, $currentPassword)) {
            $send(['error' => 'CURRENT_PASSWORD_INVALID'], 422);
        }
        if (mb_strlen($newPassword) < 10 || mb_strlen($newPassword) > 200) {
            $send(['error' => 'WEAK_PASSWORD', 'message' => 'Use a password between 10 and 200 characters.'], 422);
        }
        if (! riderSavePassword($pdo, $riderId, $newPassword)) {
            $send(['error' => 'PASSWORD_UPDATE_FAILED'], 500);
        }
        $send(['ok' => true], 200);
    }

    if ($reqPath === '/session/security/pin' && $method === 'POST') {
        $body = riderInputJson();
        $currentPassword = (string) ($body['current_password'] ?? '');
        $pin = (string) ($body['pin'] ?? '');
        if (! riderPasswordMatches($pdo, $riderId, $currentPassword)) {
            $send(['error' => 'CURRENT_PASSWORD_INVALID'], 422);
        }
        if ($pin !== '' && preg_match('/^\d{4,6}$/', $pin) !== 1) {
            $send(['error' => 'INVALID_PIN', 'message' => 'PIN must contain 4 to 6 digits.'], 422);
        }
        $pinHash = $pin === '' ? null : password_hash($pin, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO rider_account_settings (rider_id, quick_pin_hash, updated_at)
            VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE quick_pin_hash = VALUES(quick_pin_hash), updated_at = NOW()')
            ->execute([$riderId, $pinHash]);
        $send(['ok' => true, 'quick_pin_enabled' => $pin !== ''], 200);
    }

    if (preg_match('#^/orders/list$#', $reqPath) && $method === 'GET') {
        autoAssignPendingRiderOrders($pdo);
        $filter = $_GET['state'] ?? null;
        $sql = 'SELECT * FROM rider_orders WHERE rider_id = ?';
        $params = [$riderId];
        if ($filter) {
            $sql .= ' AND state = ? ';
            $params[] = $filter;
        }
        $sql .= ' ORDER BY CASE state WHEN \'ACCEPTED\' THEN 0 ELSE 1 END, offer_received_at DESC LIMIT 30';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            if (isset($row['items_json'])) {
                $row['items'] = json_decode((string) $row['items_json'], true) ?: [];
            }
            unset($row['items_json']);
        }
        $send(['orders' => $rows]);
    }

    if (preg_match('#^/orders/(\d+)/transition$#', $reqPath, $m) && $method === 'POST') {
        $orderId = (int) $m[1];
        $body = riderInputJson();
        $target = (string) ($body['state'] ?? '');
        $valid = ['ACCEPTED', 'NAVIGATING_TO_PICKUP', 'ARRIVED_AT_PICKUP', 'ORDER_VERIFIED', 'NAVIGATING_TO_DROP_OFF', 'ARRIVED_AT_DROP_OFF', 'PROOF_SUBMITTED', 'COMPLETED'];
        if (! in_array($target, $valid, true)) {
            $send(['error' => 'INVALID_STATE'], 400);
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM rider_orders WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (! $order || (int) $order['rider_id'] !== $riderId) {
            $pdo->rollBack();
            $send(['error' => 'ORDER_NOT_FOUND'], 404);
        }
        $map = [
            'NAVIGATING_TO_PICKUP' => ['pickup_arrived_at' => null],
            'ARRIVED_AT_PICKUP' => ['pickup_arrived_at' => date('Y-m-d H:i:s')],
            'ORDER_VERIFIED' => ['verified_at' => date('Y-m-d H:i:s')],
            'ARRIVED_AT_DROP_OFF' => ['dropoff_arrived_at' => date('Y-m-d H:i:s')],
            'COMPLETED' => ['completed_at' => date('Y-m-d H:i:s')],
        ];
        $sets = 'SET state = ?';
        $args = [$target];
        if (isset($map[$target])) {
            foreach ($map[$target] as $col => $val) {
                if ($val === null) {
                    continue;
                }
                $sets .= ", {$col} = ?";
                $args[] = $val;
            }
        }
        $args[] = $orderId;
        $pdo->prepare("UPDATE rider_orders {$sets} WHERE id = ?")->execute($args);
        if ($target === 'COMPLETED') {
            $pdo->prepare('UPDATE riders SET completed_today = completed_today + 1,
                duty_today_payout = duty_today_payout + COALESCE((SELECT payout_amount FROM rider_orders WHERE id = ?), 0)
                WHERE id = ?')->execute([$orderId, $riderId]);
            if (! empty($order['shared_order_id'])) {
                $pdo->prepare("UPDATE orders SET order_status = 'delivered', updated_at = NOW()
                    WHERE id = ? AND order_status NOT IN ('cancelled', 'delivered')")
                    ->execute([(int) $order['shared_order_id']]);
            }
        }
        $pdo->commit();
        if ($target === 'COMPLETED') {
            autoAssignPendingRiderOrders($pdo);
        }
        $stmt = $pdo->prepare('SELECT * FROM rider_orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $final = $stmt->fetch();
        $final['items'] = json_decode((string) $final['items_json'], true) ?: [];
        unset($final['items_json']);
        $send(['ok' => true, 'order' => $final], 200);
    }

    if ($reqPath === '/telemetry/batch' && $method === 'POST') {
        $body = riderInputJson();
        $batch = $body['points'] ?? [];
        if (! is_array($batch) || ! $batch) {
            $send(['ok' => true, 'stored' => 0], 200);
        }
        if (count($batch) > 500) {
            $send(['error' => 'BATCH_TOO_LARGE'], 400);
        }
        $sql = 'INSERT INTO rider_location_logs (rider_id, rider_order_id, lat, lng, heading, speed_kmh, accuracy_m, motion_state, is_offline_batch, recorded_at) VALUES ';
        $ph = [];
        $args = [];
        $orderId = ! empty($body['rider_order_id']) ? (int) $body['rider_order_id'] : null;
        foreach ($batch as $p) {
            $ph[] = '(?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?) * 1)';
            $ts = (int) ($p['t_ms'] ?? floor(microtime(true) * 1000));
            $args[] = $riderId;
            $args[] = $orderId;
            $args[] = (float) ($p['lat'] ?? 0);
            $args[] = (float) ($p['lng'] ?? 0);
            $args[] = isset($p['heading']) ? (float) $p['heading'] : null;
            $args[] = isset($p['speed_kmh']) ? (float) $p['speed_kmh'] : null;
            $args[] = isset($p['accuracy_m']) ? (float) $p['accuracy_m'] : null;
            $args[] = in_array($p['motion'] ?? 'IDLE', ['MOVING', 'IDLE', 'STOPPED'], true) ? ($p['motion'] ?? 'IDLE') : 'IDLE';
            $args[] = ! empty($p['offline']) ? 1 : 0;
            $args[] = (int) floor($ts / 1000);
        }
        $pdo->prepare($sql.implode(',', $ph))->execute($args);
        $send(['ok' => true, 'stored' => count($batch), 'server_ts_ms' => (int) (microtime(true) * 1000)], 201);
    }

    if (preg_match('#^/orders/(\d+)/pod$#', $reqPath, $m) && $method === 'POST') {
        $orderId = (int) $m[1];
        $body = riderInputJson();
        $codCollected = (float) ($body['cod_collected'] ?? $body['cashReceived'] ?? 0.0);
        $codChange = (float) ($body['cod_change_due'] ?? $body['changeDue'] ?? 0.0);
        $codConfirmed = ! empty($body['cod_confirmed']);
        $proofDataUri = (string) ($body['proof_image_data'] ?? $body['photoUri'] ?? '');
        $signatureDataUri = (string) ($body['signature_data'] ?? $body['signatureUri'] ?? '');
        $proofLat = isset($body['proof_lat']) ? (float) $body['proof_lat'] : (isset($body['photoLat']) ? (float) $body['photoLat'] : null);
        $proofLng = isset($body['proof_lng']) ? (float) $body['proof_lng'] : (isset($body['photoLng']) ? (float) $body['photoLng'] : null);
        $proofTs = ! empty($body['proof_captured_at_ms']) ? (new DateTimeImmutable('@'.(int) floor(((int) $body['proof_captured_at_ms']) / 1000)))->format('Y-m-d H:i:s') : (new DateTimeImmutable)->format('Y-m-d H:i:s');
        $handoffRaw = $body['handoff_mode'] ?? $body['handoffMode'] ?? 'DIRECT';
        $handoff = in_array($handoffRaw, ['DIRECT', 'GATE_LEAVE', 'NEIGHBOR', 'LOCKER'], true) ? $handoffRaw : 'DIRECT';
        $sigName = (string) ($body['signature_name'] ?? $body['signerName'] ?? '');
        $notes = (string) ($body['dropoff_notes'] ?? $body['notes'] ?? '');
        $payMethodRaw = $body['payment_method'] ?? null;
        $payMethods = ['COD', 'GCASH', 'MAYA', 'GRABPAY', 'SHOPEEPAY', 'CARD', 'BANK_TRANSFER', 'PREPAID'];
        $paymentMethod = in_array($payMethodRaw, $payMethods, true) ? $payMethodRaw : null;
        $paymentRef = (string) ($body['payment_reference'] ?? '');
        $paymentConf = ! empty($body['payment_confirmed']);
        $payProofUri = (string) ($body['payment_proof_image_data'] ?? $body['paymentProofUri'] ?? '');
        $payProofLat = isset($body['payment_proof_lat']) ? (float) $body['payment_proof_lat'] : (isset($body['paymentProofLat']) ? (float) $body['paymentProofLat'] : null);
        $payProofLng = isset($body['payment_proof_lng']) ? (float) $body['payment_proof_lng'] : (isset($body['paymentProofLng']) ? (float) $body['paymentProofLng'] : null);
        $payProofTsRaw = $body['payment_proof_captured_at_ms'] ?? $body['paymentProofCapturedAtMs'] ?? null;
        $payProofTs = $payProofTsRaw ? (new DateTimeImmutable('@'.(int) floor(((int) $payProofTsRaw) / 1000)))->format('Y-m-d H:i:s') : null;
        $saveUri = function (string $dataUri, string $prefix) use ($riderId, $orderId): ?string {
            if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#i', $dataUri, $mm) !== 1) {
                return null;
            }
            $ext = strtolower($mm[1]);
            if ($ext === 'jpg') {
                $ext = 'jpeg';
            }
            $dir = dirname(__DIR__).'/public/uploads/pod';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fn = sprintf('%s-r%d-o%d-%s.%s', $prefix, $riderId, $orderId, bin2hex(random_bytes(5)), $ext);
            $raw = base64_decode(strtr($mm[2], '-_', '+/'), true);
            if ($raw === false) {
                return null;
            }
            @file_put_contents($dir.'/'.$fn, $raw);

            return rtrim(APP_URL_BASE, '/').'/public/uploads/pod/'.$fn;
        };
        $proofPath = $saveUri($proofDataUri, 'proof');
        $sigPath = $saveUri($signatureDataUri, 'sig');
        $payProofPath = $saveUri($payProofUri, 'payproof');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, payment_method AS current_pay FROM rider_orders WHERE id = ? AND rider_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId, $riderId]);
        $orderRow = $stmt->fetch();
        if (! $orderRow) {
            $pdo->rollBack();
            $send(['error' => 'ORDER_NOT_FOUND'], 404);
        }
        if ($paymentMethod === null) {
            $paymentMethod = is_string($orderRow['current_pay'] ?? null) ? (string) $orderRow['current_pay'] : 'COD';
        }
        $pdo->prepare('INSERT INTO rider_pod_records
          (rider_order_id, rider_id, proof_image_path, proof_image_lat, proof_image_lng, proof_captured_at,
           cod_collected_amt, cod_change_due, cod_confirmed, signature_path, signature_name, dropoff_notes, handoff_mode,
           payment_method, payment_reference, payment_confirmed,
           payment_proof_image_path, payment_proof_image_lat, payment_proof_image_lng, payment_captured_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE
            proof_image_path = VALUES(proof_image_path), proof_image_lat = VALUES(proof_image_lat), proof_image_lng = VALUES(proof_image_lng),
            proof_captured_at = VALUES(proof_captured_at), cod_collected_amt = VALUES(cod_collected_amt), cod_change_due = VALUES(cod_change_due),
            cod_confirmed = VALUES(cod_confirmed), signature_path = VALUES(signature_path), signature_name = VALUES(signature_name),
            dropoff_notes = VALUES(dropoff_notes), handoff_mode = VALUES(handoff_mode),
            payment_method = VALUES(payment_method), payment_reference = VALUES(payment_reference), payment_confirmed = VALUES(payment_confirmed),
            payment_proof_image_path = VALUES(payment_proof_image_path), payment_proof_image_lat = VALUES(payment_proof_image_lat),
            payment_proof_image_lng = VALUES(payment_proof_image_lng), payment_captured_at = VALUES(payment_captured_at)')
            ->execute([$orderId, $riderId, $proofPath, $proofLat, $proofLng, $proofTs,
                $codCollected, $codChange, ($codConfirmed ? 1 : 0), $sigPath, $sigName, $notes, $handoff,
                $paymentMethod, mb_substr($paymentRef, 0, 64), ($paymentConf ? 1 : 0),
                $payProofPath, $payProofLat, $payProofLng, $payProofTs]);
        $nextPayStatus = $paymentConf ? 'PAID' : ($paymentMethod === 'COD' && $codConfirmed ? 'PAID' : ($payProofPath || $paymentRef ? 'PENDING_VERIFICATION' : 'UNPAID'));
        $pdo->prepare("UPDATE rider_orders SET state = 'PROOF_SUBMITTED',
            payment_method = ?, payment_reference = ?, payment_status = ?
            WHERE id = ? AND state NOT IN ('COMPLETED','CANCELLED')")
            ->execute([$paymentMethod, mb_substr($paymentRef, 0, 64), $nextPayStatus, $orderId]);
        $pdo->commit();
        $send(['ok' => true, 'proof_path' => $proofPath, 'signature_path' => $sigPath, 'payment_proof_path' => $payProofPath], 201);
    }

    if (preg_match('#^/orders/(\d+)/incidents$#', $reqPath, $m) && $method === 'POST') {
        $orderId = (int) $m[1];
        $body = riderInputJson();
        $code = (string) ($body['code'] ?? 'UNKNOWN');
        $detail = (string) ($body['detail'] ?? '');
        $known = ['STORE_CLOSED', 'LONG_STORE_WAIT_15', 'ORDER_MISSING', 'CUSTOMER_UNREACHABLE', 'WRONG_ADDRESS', 'MECHANICAL_ISSUE', 'OTHER', 'ITEM_UNAVAILABLE', 'MERCHANT_CONGESTION', 'ITEM_DAMAGED'];
        if (! in_array($code, $known, true)) {
            $send(['error' => 'UNKNOWN_INCIDENT'], 400);
        }
        $pdo->prepare('INSERT INTO rider_order_incidents (rider_id, rider_order_id, incident_code, detail) VALUES (?,?,?,?)')
            ->execute([$riderId, $orderId, $code, mb_substr($detail, 0, 255)]);
        $send(['ok' => true, 'known_codes' => $known], 201);
    }

    if ($reqPath === '/session/ledger' && $method === 'GET') {
        $daysRaw = (int) ($_GET['days'] ?? 14);
        $days = max(7, min(30, $daysRaw));
        $today = new DateTimeImmutable('today');
        $startDate = $today->sub(new DateInterval('P'.($days - 1).'D'));

        $stmt = $pdo->prepare("SELECT
            DATE(completed_at) AS order_date,
            COUNT(*) AS completed_count,
            COALESCE(SUM(payout_amount), 0) AS payout_total,
            COALESCE(SUM(tip_amount), 0) AS tip_total,
            COALESCE(SUM(cod_amount), 0) AS cod_total,
            COALESCE(SUM(CASE WHEN payment_method = 'COD' THEN cod_amount ELSE 0 END), 0) AS cod_cash,
            COALESCE(SUM(CASE WHEN payment_method <> 'COD' THEN cod_amount ELSE 0 END), 0) AS cod_online,
            payment_method
            FROM rider_orders
            WHERE rider_id = ? AND state = 'COMPLETED' AND completed_at IS NOT NULL
            AND DATE(completed_at) >= ? AND DATE(completed_at) <= ?
            GROUP BY DATE(completed_at), payment_method
            ORDER BY DATE(completed_at) DESC");
        $stmt->execute([$riderId, $startDate->format('Y-m-d'), $today->format('Y-m-d')]);
        $rawRows = $stmt->fetchAll();

        $period = [];
        $dailyMap = [];
        foreach ($rawRows as $row) {
            $d = (string) $row['order_date'];
            if (! isset($dailyMap[$d])) {
                $dailyMap[$d] = [
                    'date' => $d,
                    'completed' => 0,
                    'payout_php' => 0.0,
                    'tips_php' => 0.0,
                    'cod_collected_php' => 0.0,
                    'online_confirmed_php' => 0.0,
                    'breakdown_by_method' => [],
                ];
            }
            $payMethod = (string) ($row['payment_method'] ?? 'COD');
            $dailyMap[$d]['breakdown_by_method'][$payMethod] =
                ($dailyMap[$d]['breakdown_by_method'][$payMethod] ?? 0.0) + (float) $row['cod_total'];
        }

        $stmtDaily = $pdo->prepare("SELECT
            DATE(completed_at) AS order_date,
            COUNT(*) AS completed_count,
            COALESCE(SUM(payout_amount), 0) AS payout_total,
            COALESCE(SUM(tip_amount), 0) AS tip_total,
            COALESCE(SUM(CASE WHEN payment_method = 'COD' THEN cod_amount ELSE 0 END), 0) AS cod_cash,
            COALESCE(SUM(CASE WHEN payment_method <> 'COD' AND payment_status IN ('PAID','PENDING_VERIFICATION') THEN cod_amount ELSE 0 END), 0) AS online_confirmed
            FROM rider_orders
            WHERE rider_id = ? AND state = 'COMPLETED' AND completed_at IS NOT NULL
            AND DATE(completed_at) >= ? AND DATE(completed_at) <= ?
            GROUP BY DATE(completed_at)
            ORDER BY DATE(completed_at) DESC");
        $stmtDaily->execute([$riderId, $startDate->format('Y-m-d'), $today->format('Y-m-d')]);
        $dailyTotals = $stmtDaily->fetchAll();
        foreach ($dailyTotals as $row) {
            $d = (string) $row['order_date'];
            if (isset($dailyMap[$d])) {
                $dailyMap[$d]['completed'] = (int) $row['completed_count'];
                $dailyMap[$d]['payout_php'] = (float) $row['payout_total'];
                $dailyMap[$d]['tips_php'] = (float) $row['tip_total'];
                $dailyMap[$d]['cod_collected_php'] = (float) $row['cod_cash'];
                $dailyMap[$d]['online_confirmed_php'] = (float) $row['online_confirmed'];
            }
        }

        $period = array_values($dailyMap);
        usort($period, static fn ($a, $b) => $b['date'] <=> $a['date']);

        $ordersPerDay = array_map(static fn ($p) => [
            'date' => $p['date'],
            'completed' => $p['completed'],
        ], $period);

        $send([
            'period' => $period,
            'orders_per_day' => $ordersPerDay,
        ], 200);
    }

    if (preg_match('#^/orders/(\d+)/client-location$#', $reqPath, $m)) {
        $orderId = (int) $m[1];
        $tmpDir = __DIR__.'/tmp';
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $filePath = $tmpDir.'/client_location_'.$orderId.'.json';

        $safeColCache = null;
        $hasClientCol = static function (PDO $pdo, string $col) use (&$safeColCache): bool {
            if ($safeColCache === null) {
                $safeColCache = [];
                $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_orders'");
                $stmt->execute();
                foreach ($stmt->fetchAll() as $r) {
                    $safeColCache[strtolower((string) $r['COLUMN_NAME'])] = true;
                }
            }

            return isset($safeColCache[strtolower($col)]);
        };

        if ($method === 'GET') {
            $stmt = $pdo->prepare('SELECT id, rider_id, state FROM rider_orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (! $order) {
                $send(['ok' => true, 'location' => null], 200);
            }
            if ((int) $order['rider_id'] !== $riderId
                && ! (in_array((string) $order['state'], ['OFFER_RECEIVED', 'OFFER_EXPIRED', 'OFFER_REJECTED', 'CANCELLED'], true) && (int) $order['rider_id'] === 0)) {
                // allow viewing if rider owns order OR demo order
            }

            $dbPoint = null;
            $latCol = $hasClientCol($pdo, 'client_lat');
            $lngCol = $hasClientCol($pdo, 'client_lng');
            $accCol = $hasClientCol($pdo, 'client_location_accuracy_m');
            $headCol = $hasClientCol($pdo, 'client_location_heading');
            $spdCol = $hasClientCol($pdo, 'client_location_speed_kmh');
            $tsCol = $hasClientCol($pdo, 'client_location_updated_at');
            $actCol = $hasClientCol($pdo, 'client_location_sharing_active');
            if ($latCol && $lngCol) {
                $cols = ['client_lat', 'client_lng'];
                if ($accCol) {
                    $cols[] = 'client_location_accuracy_m';
                }
                if ($headCol) {
                    $cols[] = 'client_location_heading';
                }
                if ($spdCol) {
                    $cols[] = 'client_location_speed_kmh';
                }
                if ($tsCol) {
                    $cols[] = 'client_location_updated_at';
                }
                if ($actCol) {
                    $cols[] = 'client_location_sharing_active';
                }
                $sql = 'SELECT '.implode(',', $cols).' FROM rider_orders WHERE id = ? LIMIT 1';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$orderId]);
                $row = $stmt->fetch() ?: [];
                if (! empty($row['client_lat']) && ! empty($row['client_lng'])) {
                    $tsMs = null;
                    if (! empty($row['client_location_updated_at'])) {
                        try {
                            $tsMs = (int) (new DateTimeImmutable((string) $row['client_location_updated_at']))->format('Uv');
                        } catch (Throwable $_) {
                        }
                    }
                    if ($tsMs === null) {
                        $tsMs = (int) (microtime(true) * 1000);
                    }
                    $dbPoint = [
                        'order_id' => $orderId,
                        'lat' => (float) $row['client_lat'],
                        'lng' => (float) $row['client_lng'],
                        'accuracy_m' => isset($row['client_location_accuracy_m']) ? (float) $row['client_location_accuracy_m'] : null,
                        'heading' => isset($row['client_location_heading']) ? (float) $row['client_location_heading'] : null,
                        'speed_kmh' => isset($row['client_location_speed_kmh']) ? (float) $row['client_location_speed_kmh'] : null,
                        't_ms' => $tsMs,
                        'sharing_active' => ! empty($row['client_location_sharing_active']) ? true : true,
                        'phone_battery_pct' => null,
                    ];
                }
            }

            $filePoint = null;
            if (is_file($filePath)) {
                $raw = @file_get_contents($filePath);
                if ($raw !== false) {
                    $dec = json_decode($raw, true);
                    if (is_array($dec) && isset($dec['lat'], $dec['lng'])) {
                        $filePoint = [
                            'order_id' => $orderId,
                            'lat' => (float) $dec['lat'],
                            'lng' => (float) $dec['lng'],
                            'accuracy_m' => isset($dec['accuracy_m']) ? (float) $dec['accuracy_m'] : null,
                            'heading' => isset($dec['heading']) ? (float) $dec['heading'] : null,
                            'speed_kmh' => isset($dec['speed_kmh']) ? (float) $dec['speed_kmh'] : null,
                            't_ms' => isset($dec['t_ms']) ? (int) $dec['t_ms'] : (int) (microtime(true) * 1000),
                            'sharing_active' => ! empty($dec['sharing_active']),
                            'phone_battery_pct' => isset($dec['phone_battery_pct']) ? (int) $dec['phone_battery_pct'] : null,
                        ];
                    }
                }
            }

            $pick = $filePoint ?? $dbPoint;
            if ($pick === null) {
                $pick = [
                    'order_id' => $orderId,
                    'lat' => null, 'lng' => null, 'accuracy_m' => null,
                    'heading' => null, 'speed_kmh' => null,
                    't_ms' => 0,
                    'sharing_active' => false,
                    'phone_battery_pct' => null,
                ];
            }
            $send(['ok' => true, 'location' => $pick], 200);
        }

        if ($method === 'POST' || $method === 'PUT') {
            $body = riderInputJson();
            $lat = isset($body['lat']) ? (float) $body['lat'] : null;
            $lng = isset($body['lng']) ? (float) $body['lng'] : null;
            if ($lat === null || $lng === null) {
                $send(['error' => 'LAT_LNG_REQUIRED'], 400);
            }
            $accuracy = isset($body['accuracy_m']) ? (float) $body['accuracy_m'] : null;
            $heading = isset($body['heading']) ? (float) $body['heading'] : null;
            $speed = isset($body['speed_kmh']) ? (float) $body['speed_kmh'] : null;
            $sharing = ! isset($body['sharing_active']) ? true : ! empty($body['sharing_active']);
            $battery = isset($body['phone_battery_pct']) ? (int) $body['phone_battery_pct'] : null;
            $nowMs = isset($body['t_ms']) ? (int) $body['t_ms'] : (int) (microtime(true) * 1000);
            $nowStr = (new DateTimeImmutable('@'.(int) floor($nowMs / 1000)))->format('Y-m-d H:i:s');

            $filePayload = [
                'order_id' => $orderId,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy_m' => $accuracy,
                'heading' => $heading,
                'speed_kmh' => $speed,
                't_ms' => $nowMs,
                'sharing_active' => $sharing,
                'phone_battery_pct' => $battery,
            ];
            @file_put_contents($filePath, json_encode($filePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            if ($hasClientCol($pdo, 'client_lat') && $hasClientCol($pdo, 'client_lng')) {
                $sets = ['client_lat = ?', 'client_lng = ?'];
                $args = [$lat, $lng];
                if ($hasClientCol($pdo, 'client_location_accuracy_m')) {
                    $sets[] = 'client_location_accuracy_m = ?';
                    $args[] = $accuracy;
                }
                if ($hasClientCol($pdo, 'client_location_heading')) {
                    $sets[] = 'client_location_heading = ?';
                    $args[] = $heading;
                }
                if ($hasClientCol($pdo, 'client_location_speed_kmh')) {
                    $sets[] = 'client_location_speed_kmh = ?';
                    $args[] = $speed;
                }
                if ($hasClientCol($pdo, 'client_location_updated_at')) {
                    $sets[] = 'client_location_updated_at = ?';
                    $args[] = $nowStr;
                }
                if ($hasClientCol($pdo, 'client_location_sharing_active')) {
                    $sets[] = 'client_location_sharing_active = ?';
                    $args[] = ($sharing ? 1 : 0);
                }
                $args[] = $orderId;
                $pdo->prepare('UPDATE rider_orders SET '.implode(', ', $sets).' WHERE id = ?')->execute($args);
            }
            $send(['ok' => true, 'stored_at_ms' => $nowMs], 201);
        }

        $send(['error' => 'METHOD_NOT_ALLOWED'], 405);
    }

    if ($reqPath === '/session/deposit' && $method === 'POST') {
        $body = riderInputJson();
        $today = new DateTimeImmutable('today');
        $depositDate = $today->format('Y-m-d');
        $now = new DateTimeImmutable;

        $cashDeposited = (float) ($body['cash_deposited_php'] ?? $body['deposited_cash_php'] ?? 0.0);
        $depositNote = (string) ($body['deposit_note'] ?? '');
        $slipDataUri = (string) ($body['deposit_slip_image_data'] ?? $body['slip_image'] ?? '');
        $slipLat = isset($body['lat']) ? (float) $body['lat'] : (isset($body['deposit_slip_lat']) ? (float) $body['deposit_slip_lat'] : null);
        $slipLng = isset($body['lng']) ? (float) $body['lng'] : (isset($body['deposit_slip_lng']) ? (float) $body['deposit_slip_lng'] : null);
        $capturedAtMsRaw = $body['capturedAtMs'] ?? $body['deposit_slip_captured_at_ms'] ?? null;
        $shiftStartRaw = $body['shift_started_at'] ?? null;
        $shiftEndRaw = $body['shift_ended_at'] ?? null;

        $shiftStart = $shiftStartRaw ? (new DateTimeImmutable((string) $shiftStartRaw))->format('Y-m-d H:i:s') : null;
        $shiftEnd = $shiftEndRaw ? (new DateTimeImmutable((string) $shiftEndRaw))->format('Y-m-d H:i:s') : null;

        $saveUri = function (string $dataUri, string $prefix) use ($riderId, $depositDate): ?string {
            if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#i', $dataUri, $mm) !== 1) {
                return null;
            }
            $ext = strtolower($mm[1]);
            if ($ext === 'jpg') {
                $ext = 'jpeg';
            }
            $dir = dirname(__DIR__).'/public/uploads/deposits';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fn = sprintf('%s-r%d-%s-%s.jpeg', $prefix, $riderId, str_replace('-', '', $depositDate), bin2hex(random_bytes(5)));
            $raw = base64_decode(strtr($mm[2], '-_', '+/'), true);
            if ($raw === false) {
                return null;
            }
            @file_put_contents($dir.'/'.$fn, $raw);

            return rtrim(APP_URL_BASE, '/').'/public/uploads/deposits/'.$fn;
        };
        $slipPath = $saveUri($slipDataUri, 'deposit');

        $pdo->beginTransaction();
        try {
            $stmtCod = $pdo->prepare("SELECT
                COALESCE(SUM(CASE WHEN payment_method = 'COD' THEN cod_amount ELSE 0 END), 0) AS cod_total,
                COALESCE(SUM(CASE WHEN payment_method <> 'COD' AND payment_status IN ('PAID','PENDING_VERIFICATION') THEN cod_amount ELSE 0 END), 0) AS online_total,
                COUNT(*) AS completed_count
                FROM rider_orders
                WHERE rider_id = ? AND state = 'COMPLETED' AND completed_at IS NOT NULL
                AND DATE(completed_at) = ?");
            $stmtCod->execute([$riderId, $depositDate]);
            $sums = $stmtCod->fetch() ?: [];
            $codCollected = (float) ($sums['cod_total'] ?? 0.0);
            $onlineConfirmed = (float) ($sums['online_total'] ?? 0.0);
            $completedCount = (int) ($sums['completed_count'] ?? 0);
            $totalExpected = $codCollected + $onlineConfirmed;

            $colDefs = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_deposits'")->fetchAll();
            $hasCol = static function (string $name) use ($colDefs): bool {
                foreach ($colDefs as $c) {
                    if (strcasecmp((string) $c['COLUMN_NAME'], $name) === 0) {
                        return true;
                    }
                }

                return false;
            };

            $nowStr = $now->format('Y-m-d H:i:s');
            $columns = ['rider_id', 'deposit_date', 'orders_completed_count', 'cash_cod_collected_php', 'online_confirmed_php', 'total_expected_php', 'deposited_cash_php', 'deposit_note', 'deposit_slip_image_path', 'deposit_status', 'created_at', 'updated_at'];
            $placeholders = ['?,?,?,?,?,?,?,?,?,?,?,?'];
            $values = [
                $riderId, $depositDate, $completedCount, $codCollected, $onlineConfirmed, $totalExpected,
                $cashDeposited, mb_substr($depositNote, 0, 500), $slipPath, 'PENDING', $nowStr, $nowStr,
            ];
            $updates = [
                'orders_completed_count = VALUES(orders_completed_count)',
                'cash_cod_collected_php = VALUES(cash_cod_collected_php)',
                'online_confirmed_php = VALUES(online_confirmed_php)',
                'total_expected_php = VALUES(total_expected_php)',
                'deposited_cash_php = VALUES(deposited_cash_php)',
                'deposit_note = VALUES(deposit_note)',
                'deposit_status = VALUES(deposit_status)',
                'updated_at = VALUES(updated_at)',
            ];
            if ($slipPath !== null) {
                $updates[] = 'deposit_slip_image_path = VALUES(deposit_slip_image_path)';
            }
            if ($hasCol('deposit_slip_lat')) {
                $columns[] = 'deposit_slip_lat';
                $placeholders[0] .= ',?';
                $values[] = $slipLat;
                $updates[] = 'deposit_slip_lat = VALUES(deposit_slip_lat)';
            }
            if ($hasCol('deposit_slip_lng')) {
                $columns[] = 'deposit_slip_lng';
                $placeholders[0] .= ',?';
                $values[] = $slipLng;
                $updates[] = 'deposit_slip_lng = VALUES(deposit_slip_lng)';
            }
            if ($hasCol('shift_started_at')) {
                $columns[] = 'shift_started_at';
                $placeholders[0] .= ',?';
                $values[] = $shiftStart;
                $updates[] = 'shift_started_at = VALUES(shift_started_at)';
            }
            if ($hasCol('shift_ended_at')) {
                $columns[] = 'shift_ended_at';
                $placeholders[0] .= ',?';
                $values[] = $shiftEnd;
                $updates[] = 'shift_ended_at = VALUES(shift_ended_at)';
            }

            $sql = sprintf(
                'INSERT INTO rider_deposits (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                implode(',', $columns),
                implode(',', $placeholders),
                implode(',', $updates),
            );
            $pdo->prepare($sql)->execute($values);

            $stmtFetch = $pdo->prepare('SELECT * FROM rider_deposits WHERE rider_id = ? AND deposit_date = ? LIMIT 1');
            $stmtFetch->execute([$riderId, $depositDate]);
            $deposit = $stmtFetch->fetch() ?: null;
            $pdo->commit();
            if (! $deposit) {
                throw new RuntimeException('DEPOSIT_INSERT_FAILED');
            }
            $send(['deposit' => $deposit], 200);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable $_) {
                }
            }
            throw $e;
        }
    }

    $send(['error' => 'NOT_FOUND', 'path' => $reqPath, 'method' => $method], 404);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $_) {
        }
    }
    error_log('[A-rider API] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    $send(['error' => 'SERVER_ERROR', 'detail' => getenv('APP_ENV') === 'production' ? null : $e->getMessage()], 500);
}
