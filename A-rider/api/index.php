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
