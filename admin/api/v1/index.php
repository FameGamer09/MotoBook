<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = getDBConnection();
runOperationsMigration($pdo);

$route = trim((string) ($_GET['route'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = apiBody();

if ($route === 'health') {
    apiJson(['success' => true, 'service' => 'Motobook Super Admin API v1', 'time' => date('c')]);
}

if ($route === 'auth/login' && $method === 'POST') {
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $panel = (string) ($body['panel'] ?? 'management');

    if ($email === '' || $password === '') {
        apiJson(['success' => false, 'message' => 'Email and password are required.'], 422);
    }

    if ($panel === 'admin') {
        $stmt = $pdo->prepare('SELECT * FROM super_admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password'])) {
            apiJson(['success' => false, 'message' => 'Invalid super admin credentials.'], 401);
        }
        $token = issueToken($pdo, 'super_admin', (int) $admin['id'], $admin['email']);
        apiJson(['success' => true, 'token' => $token, 'user' => [
            'id' => (int) $admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'type' => 'super_admin',
            'role' => 'super_admin',
            'store_id' => null,
            'permissions' => ['staff.write', 'settings.write', 'finance.audit'],
        ]]);
    }

    $stmt = $pdo->prepare('SELECT s.*, ps.store_name FROM staff s LEFT JOIN partnership_stores ps ON ps.id = s.store_id WHERE s.email = ? AND s.is_active = 1');
    $stmt->execute([$email]);
    $staff = $stmt->fetch();

    if ($staff && password_verify($password, $staff['password'])) {
        $type = $staff['staff_type'] === 'store' ? 'store_staff' : 'platform_staff';
        $token = issueToken($pdo, $type, (int) $staff['id'], $staff['email']);
        $pdo->prepare('UPDATE staff SET last_active_at = NOW() WHERE id = ?')->execute([$staff['id']]);
        apiJson(['success' => true, 'token' => $token, 'user' => [
            'id' => (int) $staff['id'],
            'name' => $staff['full_name'],
            'email' => $staff['email'],
            'type' => $type,
            'role' => $staff['role'],
            'store_id' => $staff['store_id'] ? (int) $staff['store_id'] : null,
            'store_name' => $staff['store_name'],
            'permissions' => $type === 'platform_staff'
                ? ['orders.dispatch', 'remittance.collect', 'tickets.resolve', 'promos.approve', 'banners.upload', 'settings.read']
                : ['orders.store', 'menu.manage', 'store.pause', 'promos.submit', 'helpdesk.create'],
        ]]);
    }

    $stmt = $pdo->prepare('SELECT * FROM partnership_stores WHERE owner_email = ?');
    $stmt->execute([$email]);
    $store = $stmt->fetch();
    if ($store && $store['owner_password'] && password_verify($password, $store['owner_password'])) {
        $token = issueToken($pdo, 'store_owner', (int) $store['id'], $store['owner_email']);
        apiJson(['success' => true, 'token' => $token, 'user' => [
            'id' => (int) $store['id'],
            'name' => $store['owner_name'] ?: $store['store_name'] . ' Owner',
            'email' => $store['owner_email'],
            'type' => 'store_owner',
            'role' => 'store_manager',
            'store_id' => (int) $store['id'],
            'store_name' => $store['store_name'],
            'permissions' => ['orders.store', 'menu.manage', 'store.pause', 'promos.submit', 'helpdesk.create'],
        ]]);
    }

    apiJson(['success' => false, 'message' => 'Invalid credentials for the Management panel.'], 401);
}

$user = requireApiUser($pdo);
$scopeStoreId = storeScopeId($user);
$isPlatform = $user['actor_type'] === 'platform_staff' || $user['actor_type'] === 'super_admin';
$isStore = in_array($user['actor_type'], ['store_staff', 'store_owner'], true);

if ($route === 'auth/me') {
    apiJson(['success' => true, 'user' => $user, 'store_scope' => $scopeStoreId]);
}

if ($route === 'settings' && $method === 'GET') {
    apiJson(['success' => true, 'settings' => array_values(settingsMap($pdo)), 'read_only' => $user['actor_type'] !== 'super_admin']);
}

if ($route === 'settings' && $method === 'POST') {
    if ($user['actor_type'] !== 'super_admin') {
        apiJson(['success' => false, 'message' => 'Global delivery fees and bulking rules are Super Admin only. Management has read-only access.'], 403);
    }
    foreach (['global_delivery_fee', 'bulk_min_orders', 'bulk_discount_percent', 'max_refund_amount', 'delay_threshold_minutes'] as $key) {
        if (isset($body[$key])) {
            $stmt = $pdo->prepare('UPDATE platform_settings SET setting_value = ? WHERE setting_key = ?');
            $stmt->execute([(string) $body[$key], $key]);
        }
    }
    apiJson(['success' => true, 'settings' => array_values(settingsMap($pdo))]);
}

if ($route === 'dashboard') {
    $threshold = delayThreshold($pdo);
    $storeFilter = $scopeStoreId ? ' AND store_id = ' . (int) $scopeStoreId : '';
    $activeOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('delivered','cancelled') {$storeFilter}")->fetchColumn();
    $delayed = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE (order_status = 'delayed' OR (order_status NOT IN ('delivered','cancelled') AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > {$threshold})) {$storeFilter}")->fetchColumn();
    $openTickets = (int) $pdo->query('SELECT COUNT(*) FROM complaint_tickets WHERE status IN (\'open\',\'in_review\')' . ($scopeStoreId ? ' AND store_id = ' . (int) $scopeStoreId : ''))->fetchColumn();
    $pendingRemit = (int) $pdo->query("SELECT COUNT(*) FROM rider_daily_collections WHERE remittance_status = 'pending' AND collection_date = CURDATE()")->fetchColumn();
    $ridersOnShift = (int) $pdo->query("SELECT COUNT(*) FROM rider_shifts WHERE shift_date = CURDATE() AND shift_status IN ('clocked_in','active','on_break')")->fetchColumn();
    $pendingPromos = (int) $pdo->query("SELECT COUNT(*) FROM store_promos WHERE status = 'pending'" . ($scopeStoreId ? ' AND store_id = ' . (int) $scopeStoreId : ''))->fetchColumn();

    apiJson(['success' => true, 'kpis' => [
        'active_orders' => $activeOrders,
        'delayed_orders' => $delayed,
        'open_tickets' => $openTickets,
        'pending_remittance' => $pendingRemit,
        'riders_on_shift' => $ridersOnShift,
        'pending_promos' => $pendingPromos,
    ], 'settings' => [
        'global_delivery_fee' => settingValue($pdo, 'global_delivery_fee'),
        'bulk_min_orders' => settingValue($pdo, 'bulk_min_orders'),
        'max_refund_amount' => settingValue($pdo, 'max_refund_amount'),
        'delay_threshold_minutes' => (string) $threshold,
    ]]);
}

if ($route === 'orders') {
    $threshold = delayThreshold($pdo);
    $sql = 'SELECT o.*, ps.store_name, r.full_name AS rider_name, r.duty_status AS rider_duty
            FROM orders o
            LEFT JOIN partnership_stores ps ON ps.id = o.store_id
            LEFT JOIN riders r ON r.id = o.rider_id
            WHERE 1=1';
    $params = [];
    if ($scopeStoreId) {
        $sql .= ' AND o.store_id = ?';
        $params[] = $scopeStoreId;
    }
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        if ($_GET['status'] === 'delayed') {
            $sql .= " AND o.order_status NOT IN ('delivered','cancelled') AND (o.order_status = 'delayed' OR TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > ?)";
            $params[] = $threshold;
        } else {
            $sql .= ' AND o.order_status = ?';
            $params[] = $_GET['status'];
        }
    } else {
        $sql .= " AND o.order_status NOT IN ('delivered','cancelled')";
    }
    $sql .= ' ORDER BY o.created_at DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = array_map(fn (array $row) => decorateOrder($row, $threshold), $stmt->fetchAll());
    apiJson(['success' => true, 'orders' => $orders]);
}

if (preg_match('#^orders/(\d+)$#', $route, $m) && $method === 'GET') {
    $id = (int) $m[1];
    $stmt = $pdo->prepare('SELECT o.*, ps.store_name, r.full_name AS rider_name FROM orders o LEFT JOIN partnership_stores ps ON ps.id = o.store_id LEFT JOIN riders r ON r.id = o.rider_id WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        apiJson(['success' => false, 'message' => 'Order not found.'], 404);
    }
    if ($scopeStoreId && (int) $order['store_id'] !== $scopeStoreId) {
        apiJson(['success' => false, 'message' => 'This order belongs to another store.'], 403);
    }
    $chats = $pdo->prepare('SELECT sender_role, sender_name, message, sent_at FROM order_chats WHERE order_id = ? ORDER BY sent_at ASC');
    $chats->execute([$id]);
    $gps = $pdo->prepare('SELECT latitude, longitude, recorded_at FROM gps_tracks WHERE order_id = ? ORDER BY recorded_at ASC');
    $gps->execute([$id]);
    $events = $pdo->prepare('SELECT event_type, notes, actor_email, created_at FROM order_events WHERE order_id = ? ORDER BY created_at DESC');
    $events->execute([$id]);
    $riderOrder = null;
    $riderPod = null;
    $riderLatestTelemetry = null;
    $riderIncidents = [];
    if (hasRiderSchema($pdo)) {
        $roStmt = $pdo->prepare('SELECT id, rider_id, order_code, state, payout_amount, tip_amount, cod_amount,
            offer_received_at, accepted_at, pickup_arrived_at, verified_at, dropoff_arrived_at, completed_at,
            merchant_name, merchant_address, dropoff_name, dropoff_address, dropoff_phone, special_notes
            FROM rider_orders WHERE shared_order_id = ? LIMIT 1');
        $roStmt->execute([$id]);
        $riderOrder = $roStmt->fetch() ?: null;
        if ($riderOrder) {
            $roId = (int)$riderOrder['id'];
            $podStmt = $pdo->prepare('SELECT proof_image_path, proof_image_lat, proof_image_lng, proof_captured_at,
                cod_collected_amt, cod_change_due, cod_confirmed, signature_path, signature_name, dropoff_notes, handoff_mode, created_at
                FROM rider_pod_records WHERE rider_order_id = ? LIMIT 1');
            $podStmt->execute([$roId]);
            $riderPod = $podStmt->fetch() ?: null;
            $telStmt = $pdo->prepare('SELECT lat, lng, heading, speed_kmh, accuracy_m, motion_state, is_offline_batch, recorded_at
                FROM rider_location_logs WHERE rider_order_id = ? ORDER BY recorded_at DESC LIMIT 1');
            $telStmt->execute([$roId]);
            $riderLatestTelemetry = $telStmt->fetch() ?: null;
            $incStmt = $pdo->prepare('SELECT incident_code, detail, created_at FROM rider_order_incidents WHERE rider_order_id = ? ORDER BY id DESC LIMIT 10');
            $incStmt->execute([$roId]);
            $riderIncidents = $incStmt->fetchAll();
        }
    }
    apiJson([
        'success' => true,
        'order' => decorateOrder($order, delayThreshold($pdo)),
        'chats' => $chats->fetchAll(),
        'gps' => $gps->fetchAll(),
        'events' => $events->fetchAll(),
        'rider_order' => $riderOrder,
        'rider_pod' => $riderPod,
        'rider_telemetry_latest' => $riderLatestTelemetry,
        'rider_incidents' => $riderIncidents,
    ]);
}

if (preg_match('#^orders/(\d+)/reassign$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Only Motobook management staff can reassign riders.'], 403);
    }
    $orderId = (int) $m[1];
    $riderId = (int) ($body['rider_id'] ?? 0);
    $reason = trim((string) ($body['reason'] ?? 'Manual reassignment'));
    $rider = $pdo->prepare("SELECT * FROM riders WHERE id = ? AND status IN ('active','on_duty')");
    $rider->execute([$riderId]);
    $riderRow = $rider->fetch();
    if (!$riderRow) {
        apiJson(['success' => false, 'message' => 'Rider is not available for assignment.'], 422);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET rider_id = ?, order_status = 'driver_assigned', assigned_at = NOW() WHERE id = ?")->execute([$riderId, $orderId]);
        $pdo->prepare("UPDATE riders SET duty_status = 'on_trip', status = 'on_duty' WHERE id = ?")->execute([$riderId]);
        logOrderEvent($pdo, $orderId, 'reassign', $reason . ' → ' . $riderRow['full_name'], $user['actor_email']);
        $riderSync = syncLegacyOrderToRider($pdo, $orderId, $riderId, (string)$user['actor_email']);
        $pdo->commit();
        apiJson(['success' => true, 'message' => 'Order reassigned to ' . $riderRow['full_name'] . '.', 'rider_sync' => $riderSync]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        apiJson(['success' => false, 'message' => 'Failed to reassign order: ' . $e->getMessage()], 500);
    }
}

if (preg_match('#^orders/(\d+)/rider-sync$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Only Motobook management staff can sync orders to the rider system.'], 403);
    }
    $orderId = (int)$m[1];
    $riderId = (int)($body['rider_id'] ?? 0);
    if ($riderId <= 0) {
        $probe = $pdo->prepare('SELECT rider_id FROM orders WHERE id = ? LIMIT 1');
        $probe->execute([$orderId]);
        $riderId = (int)($probe->fetchColumn() ?: 0);
    }
    if ($riderId <= 0) {
        apiJson(['success' => false, 'message' => 'No rider assigned to this legacy order. Assign a rider first.'], 422);
    }
    $synced = syncLegacyOrderToRider($pdo, $orderId, $riderId, (string)($user['actor_email'] ?? ''));
    if (!$synced || empty($synced['rider_order_id'])) {
        apiJson(['success' => false, 'message' => 'Rider schema not installed or order not found — run A-rider/migrations/001_init_rider_schema.sql first.'], 422);
    }
    logOrderEvent($pdo, $orderId, 'rider_sync', 'Explicit sync → rider_orders #' . $synced['rider_order_id'], (string)($user['actor_email'] ?? ''));
    apiJson(['success' => true, 'rider_sync' => $synced]);
}

if ($route === 'riders/active') {
    $stmt = $pdo->query("SELECT id, rider_code, full_name, phone, duty_status, status, vehicle_plate FROM riders WHERE status IN ('active','on_duty') ORDER BY full_name");
    $shifts = $pdo->query('SELECT rs.*, r.full_name FROM rider_shifts rs JOIN riders r ON r.id = rs.rider_id WHERE rs.shift_date = CURDATE() ORDER BY r.full_name')->fetchAll();
    apiJson(['success' => true, 'riders' => $stmt->fetchAll(), 'shifts' => $shifts]);
}

if ($route === 'incidents' && $method === 'GET') {
    if ($isStore) {
        apiJson(['success' => false, 'message' => 'Store managers cannot access the rider incident log.'], 403);
    }
    $rows = $pdo->query('SELECT i.*, r.full_name AS rider_name FROM rider_incidents i JOIN riders r ON r.id = i.rider_id ORDER BY i.created_at DESC LIMIT 50')->fetchAll();
    apiJson(['success' => true, 'incidents' => $rows]);
}

if ($route === 'incidents' && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Only management staff can log rider incidents.'], 403);
    }
    $pdo->prepare('INSERT INTO rider_incidents (rider_id, incident_type, notes, logged_by_staff_id) VALUES (?, ?, ?, ?)')
        ->execute([(int) ($body['rider_id'] ?? 0), $body['incident_type'] ?? 'other', trim((string) ($body['notes'] ?? '')), (int) $user['actor_id']]);
    apiJson(['success' => true, 'message' => 'Incident logged.']);
}

if ($route === 'remittance' && $method === 'GET') {
    if ($isStore) {
        apiJson(['success' => false, 'message' => 'Store managers cannot access rider remittance.'], 403);
    }
    $date = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare('SELECT rdc.*, r.full_name, r.rider_code FROM rider_daily_collections rdc JOIN riders r ON r.id = rdc.rider_id WHERE rdc.collection_date = ? ORDER BY r.full_name');
    $stmt->execute([$date]);
    apiJson(['success' => true, 'date' => $date, 'collections' => $stmt->fetchAll()]);
}

if (preg_match('#^remittance/(\d+)/collect$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Only Motobook management staff can collect remittance.'], 403);
    }
    $id = (int) $m[1];
    $pdo->prepare("UPDATE rider_daily_collections SET remittance_status = 'collected', collected_at = NOW(), collected_by_staff_id = ? WHERE id = ? AND remittance_status = 'pending'")
        ->execute([(int) $user['actor_id'], $id]);
    apiJson(['success' => true, 'message' => 'Cash remittance marked as collected. Super Admin can still run final audit.']);
}

if ($route === 'tickets' && $method === 'GET') {
    $sql = 'SELECT t.*, ps.store_name, r.full_name AS rider_name, o.order_number FROM complaint_tickets t
            LEFT JOIN partnership_stores ps ON ps.id = t.store_id
            LEFT JOIN riders r ON r.id = t.rider_id
            LEFT JOIN orders o ON o.id = t.order_id WHERE 1=1';
    $params = [];
    if ($scopeStoreId) {
        $sql .= ' AND t.store_id = ?';
        $params[] = $scopeStoreId;
    }
    if (!empty($_GET['status'])) {
        $sql .= ' AND t.status = ?';
        $params[] = $_GET['status'];
    }
    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiJson(['success' => true, 'tickets' => $stmt->fetchAll()]);
}

if (preg_match('#^tickets/(\d+)$#', $route, $m) && $method === 'GET') {
    $id = (int) $m[1];
    $stmt = $pdo->prepare('SELECT t.*, ps.store_name, r.full_name AS rider_name, o.order_number, o.order_total, o.photo_path FROM complaint_tickets t LEFT JOIN partnership_stores ps ON ps.id = t.store_id LEFT JOIN riders r ON r.id = t.rider_id LEFT JOIN orders o ON o.id = t.order_id WHERE t.id = ?');
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        apiJson(['success' => false, 'message' => 'Ticket not found.'], 404);
    }
    if ($scopeStoreId && (int) $ticket['store_id'] !== $scopeStoreId) {
        apiJson(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $ev = $pdo->prepare('SELECT * FROM ticket_evidence WHERE ticket_id = ?');
    $ev->execute([$id]);
    $chats = [];
    $gps = [];
    if ($ticket['order_id']) {
        $c = $pdo->prepare('SELECT * FROM order_chats WHERE order_id = ? ORDER BY sent_at');
        $c->execute([$ticket['order_id']]);
        $chats = $c->fetchAll();
        $g = $pdo->prepare('SELECT * FROM gps_tracks WHERE order_id = ? ORDER BY recorded_at');
        $g->execute([$ticket['order_id']]);
        $gps = $g->fetchAll();
    }
    apiJson(['success' => true, 'ticket' => $ticket, 'evidence' => $ev->fetchAll(), 'chats' => $chats, 'gps' => $gps]);
}

if (preg_match('#^tickets/(\d+)/resolve$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Daily ticket resolution is handled by Motobook management staff.'], 403);
    }
    $id = (int) $m[1];
    $action = $body['action'] ?? 'resolve';
    $notes = trim((string) ($body['notes'] ?? ''));
    $refundAmount = (float) ($body['refund_amount'] ?? 0);
    $maxRefund = (float) settingValue($pdo, 'max_refund_amount', '500');
    $ticketStmt = $pdo->prepare('SELECT * FROM complaint_tickets WHERE id = ?');
    $ticketStmt->execute([$id]);
    $ticket = $ticketStmt->fetch();
    if (!$ticket) {
        apiJson(['success' => false, 'message' => 'Ticket not found.'], 404);
    }

    if ($action === 'refund') {
        if ($refundAmount <= 0 || $refundAmount > $maxRefund) {
            apiJson(['success' => false, 'message' => 'Refund exceeds Super Admin operational limit of ' . formatMoney($maxRefund) . '.'], 422);
        }
        $pdo->prepare('INSERT INTO refunds (ticket_id, order_id, amount, refund_type, status, processed_by_staff_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $ticket['order_id'], $refundAmount, 'refund', 'approved', (int) $user['actor_id'], $notes]);
        if ($ticket['order_id']) {
            $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?")->execute([$ticket['order_id']]);
        }
        $pdo->prepare("UPDATE complaint_tickets SET status = 'resolved', resolution_notes = ?, assigned_staff_id = ? WHERE id = ?")
            ->execute(['Refund ' . formatMoney($refundAmount) . '. ' . $notes, (int) $user['actor_id'], $id]);
        apiJson(['success' => true, 'message' => 'Refund approved within Super Admin limits.']);
    }

    if ($action === 'replacement') {
        $pdo->prepare('INSERT INTO refunds (ticket_id, order_id, amount, refund_type, status, processed_by_staff_id, notes) VALUES (?, ?, 0, ?, ?, ?, ?)')
            ->execute([$id, $ticket['order_id'], 'replacement', 'approved', (int) $user['actor_id'], $notes]);
        $pdo->prepare("UPDATE complaint_tickets SET status = 'resolved', resolution_notes = ?, assigned_staff_id = ? WHERE id = ?")
            ->execute(['Replacement order issued. ' . $notes, (int) $user['actor_id'], $id]);
        apiJson(['success' => true, 'message' => 'Replacement order issued.']);
    }

    $status = $action === 'reject' ? 'rejected' : 'resolved';
    $pdo->prepare('UPDATE complaint_tickets SET status = ?, resolution_notes = ?, assigned_staff_id = ? WHERE id = ?')
        ->execute([$status, $notes, (int) $user['actor_id'], $id]);
    apiJson(['success' => true, 'message' => 'Ticket updated.']);
}

if ($route === 'stores' && $method === 'GET') {
    $sql = 'SELECT ps.*, sc.name AS category_name FROM partnership_stores ps LEFT JOIN store_categories sc ON sc.id = ps.category_id';
    $params = [];
    if ($scopeStoreId) {
        $sql .= ' WHERE ps.id = ?';
        $params[] = $scopeStoreId;
    }
    $sql .= ' ORDER BY ps.store_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiJson(['success' => true, 'stores' => $stmt->fetchAll()]);
}

if (preg_match('#^stores/(\d+)/pause$#', $route, $m) && $method === 'POST') {
    $storeId = (int) $m[1];
    if ($isStore && $scopeStoreId !== $storeId) {
        apiJson(['success' => false, 'message' => 'You can only pause your own store.'], 403);
    }
    $status = in_array($body['status'] ?? '', ['paused', 'offline', 'open'], true) ? $body['status'] : 'paused';
    $pdo->prepare('UPDATE partnership_stores SET status = ? WHERE id = ?')->execute([$status, $storeId]);
    apiJson(['success' => true, 'message' => 'Store status set to ' . $status . '.']);
}

if (preg_match('#^stores/(\d+)/onboard$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Onboarding assistance is for Motobook management staff.'], 403);
    }
    $storeId = (int) $m[1];
    $fields = [];
    $params = [];
    foreach (['store_name', 'branch_address', 'contact_phone', 'contact_email', 'operating_hours', 'owner_name', 'owner_email'] as $field) {
        if (isset($body[$field]) && $body[$field] !== '') {
            $fields[] = "{$field} = ?";
            $params[] = $body[$field];
        }
    }
    if (!empty($body['owner_password'])) {
        $fields[] = 'owner_password = ?';
        $params[] = password_hash((string) $body['owner_password'], PASSWORD_DEFAULT);
    }
    if ($fields) {
        $params[] = $storeId;
        $pdo->prepare('UPDATE partnership_stores SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }
    apiJson(['success' => true, 'message' => 'Store profile updated. Super Admin still owns global commission rules.']);
}

if (preg_match('#^stores/(\d+)/menu$#', $route, $m) && $method === 'GET') {
    $storeId = (int) $m[1];
    if ($isStore && $scopeStoreId !== $storeId) {
        apiJson(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $stmt = $pdo->prepare('SELECT * FROM store_menu_items WHERE store_id = ? ORDER BY category, item_name');
    $stmt->execute([$storeId]);
    apiJson(['success' => true, 'items' => $stmt->fetchAll()]);
}

if (preg_match('#^stores/(\d+)/menu$#', $route, $m) && $method === 'POST') {
    $storeId = (int) $m[1];
    if ($isStore && $scopeStoreId !== $storeId) {
        apiJson(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $pdo->prepare('INSERT INTO store_menu_items (store_id, item_name, category, price, is_available) VALUES (?, ?, ?, ?, 1)')
        ->execute([$storeId, trim((string) ($body['item_name'] ?? '')), trim((string) ($body['category'] ?? 'Main')), (float) ($body['price'] ?? 0)]);
    apiJson(['success' => true, 'message' => 'Menu item added.']);
}

if (preg_match('#^menu/(\d+)/toggle$#', $route, $m) && $method === 'POST') {
    $itemId = (int) $m[1];
    $item = $pdo->prepare('SELECT * FROM store_menu_items WHERE id = ?');
    $item->execute([$itemId]);
    $row = $item->fetch();
    if (!$row) {
        apiJson(['success' => false, 'message' => 'Item not found.'], 404);
    }
    if ($isStore && $scopeStoreId !== (int) $row['store_id']) {
        apiJson(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $pdo->prepare('UPDATE store_menu_items SET is_available = IF(is_available = 1, 0, 1) WHERE id = ?')->execute([$itemId]);
    apiJson(['success' => true, 'message' => 'Item availability updated.']);
}

if ($route === 'helpdesk' && $method === 'GET') {
    $sql = 'SELECT h.*, ps.store_name FROM merchant_help_tickets h JOIN partnership_stores ps ON ps.id = h.store_id WHERE 1=1';
    $params = [];
    if ($scopeStoreId) {
        $sql .= ' AND h.store_id = ?';
        $params[] = $scopeStoreId;
    }
    $sql .= ' ORDER BY h.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiJson(['success' => true, 'tickets' => $stmt->fetchAll()]);
}

if ($route === 'helpdesk' && $method === 'POST') {
    $storeId = $scopeStoreId ?: (int) ($body['store_id'] ?? 0);
    $number = 'MHT-' . date('ymdHis');
    $pdo->prepare('INSERT INTO merchant_help_tickets (ticket_number, store_id, subject, category, message, status) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$number, $storeId, trim((string) ($body['subject'] ?? '')), $body['category'] ?? 'other', trim((string) ($body['message'] ?? '')), 'open']);
    apiJson(['success' => true, 'message' => 'Help desk ticket submitted.', 'ticket_number' => $number]);
}

if (preg_match('#^helpdesk/(\d+)/reply$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Only Motobook management can reply to merchant tickets.'], 403);
    }
    $pdo->prepare("UPDATE merchant_help_tickets SET staff_reply = ?, status = 'resolved' WHERE id = ?")
        ->execute([trim((string) ($body['reply'] ?? '')), (int) $m[1]]);
    apiJson(['success' => true, 'message' => 'Reply sent and ticket resolved.']);
}

if ($route === 'banners' && $method === 'GET') {
    $rows = $pdo->query('SELECT b.*, ps.store_name FROM promo_banners b LEFT JOIN partnership_stores ps ON ps.id = b.store_id ORDER BY b.created_at DESC')->fetchAll();
    apiJson(['success' => true, 'banners' => $rows]);
}

if ($route === 'banners' && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Banner uploads are executed by Motobook management staff.'], 403);
    }
    $imagePath = null;
    $uploadDir = dirname(__DIR__, 2) . '/uploads/banners/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'png';
        $name = 'banner_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $name);
        $imagePath = 'uploads/banners/' . $name;
    }
    $pdo->prepare('INSERT INTO promo_banners (title, caption, image_path, hex_color, store_id, is_active, uploaded_by_staff_id) VALUES (?, ?, ?, ?, ?, 1, ?)')
        ->execute([
            trim((string) ($body['title'] ?? $_POST['title'] ?? 'Promo Banner')),
            trim((string) ($body['caption'] ?? $_POST['caption'] ?? '')),
            $imagePath,
            $body['hex_color'] ?? $_POST['hex_color'] ?? '#06b6d4',
            (int) ($body['store_id'] ?? $_POST['store_id'] ?? 0) ?: null,
            (int) $user['actor_id'],
        ]);
    apiJson(['success' => true, 'message' => 'Banner uploaded to the client app slot.']);
}

if ($route === 'promos' && $method === 'GET') {
    $sql = 'SELECT p.*, ps.store_name FROM store_promos p JOIN partnership_stores ps ON ps.id = p.store_id WHERE 1=1';
    $params = [];
    if ($scopeStoreId) {
        $sql .= ' AND p.store_id = ?';
        $params[] = $scopeStoreId;
    }
    $sql .= ' ORDER BY p.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiJson(['success' => true, 'promos' => $stmt->fetchAll()]);
}

if ($route === 'promos' && $method === 'POST') {
    $storeId = $scopeStoreId ?: (int) ($body['store_id'] ?? 0);
    if (!$storeId) {
        apiJson(['success' => false, 'message' => 'Store is required.'], 422);
    }
    $pdo->prepare('INSERT INTO store_promos (store_id, title, description, discount_percent, status, submitted_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$storeId, trim((string) ($body['title'] ?? '')), trim((string) ($body['description'] ?? '')), (float) ($body['discount_percent'] ?? 0), 'pending', $user['actor_email']]);
    apiJson(['success' => true, 'message' => 'Promo submitted for Motobook management approval.']);
}

if (preg_match('#^promos/(\d+)/review$#', $route, $m) && $method === 'POST') {
    if (!$isPlatform) {
        apiJson(['success' => false, 'message' => 'Store managers cannot approve their own promos.'], 403);
    }
    $status = ($body['status'] ?? '') === 'approved' ? 'approved' : 'rejected';
    $pdo->prepare('UPDATE store_promos SET status = ?, reviewed_by_staff_id = ?, review_notes = ? WHERE id = ?')
        ->execute([$status, (int) $user['actor_id'], trim((string) ($body['notes'] ?? '')), (int) $m[1]]);
    apiJson(['success' => true, 'message' => 'Promo ' . $status . '.']);
}

if ($route === 'staff' && $method === 'POST') {
    forbidStaffAccounts($user);
    apiJson(['success' => false, 'message' => 'Use Super Admin for staff accounts.'], 403);
}

apiJson(['success' => false, 'message' => 'Unknown API route: ' . $route], 404);
