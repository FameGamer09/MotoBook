<?php

declare(strict_types=1);

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../../A-rider/includes/dispatch.php';

function getOpsDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

function currentUser(): ?array
{
    if (empty($_SESSION['ops_user_id'])) {
        return null;
    }

    return $_SESSION['ops_user'] ?? null;
}

function currentUserId(): ?int
{
    return empty($_SESSION['ops_user_id']) ? null : (int) $_SESSION['ops_user_id'];
}

function currentUserStoreId(): ?int
{
    $u = currentUser();
    if (! $u) {
        return null;
    }

    return $u['store_id'] ?? null;
}

function isPlatformStaff(): bool
{
    $type = currentUser()['type'] ?? '';

    return in_array($type, ['platform_staff', 'super_admin'], true);
}

function isStoreStaff(): bool
{
    $type = currentUser()['type'] ?? '';

    return in_array($type, ['store_staff', 'store_owner'], true);
}

function userTypeLabel(): string
{
    $u = currentUser();
    if (! $u) {
        return '';
    }
    $map = [
        'super_admin' => 'Super Administrator',
        'platform_staff' => 'Motobook Operations',
        'store_staff' => 'Store Staff',
        'store_owner' => 'Store Owner',
    ];

    return $map[$u['type'] ?? ''] ?? 'Staff';
}

function requireOpsLogin(): void
{
    if (empty($_SESSION['ops_user_id']) || empty($_SESSION['ops_user'])) {
        header('Location: '.ADMIN_URL.'/login.php');
        exit;
    }
}

function requirePlatform(): void
{
    requireOpsLogin();
    if (! isPlatformStaff()) {
        flash('error', 'This workspace is for Motobook management staff only.');
        header('Location: '.APP_URL.'/dashboard.php');
        exit;
    }
}

function loginManagement(string $email, string $password): bool
{
    $res = tryLoginManagement($email, $password);

    return $res['ok'];
}

function tryLoginManagement(string $email, string $password): array
{
    $email = trim($email);
    $password = (string) $password;
    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter your email and password.'];
    }

    $pdo = getOpsDB();

    $stmt = $pdo->prepare('SELECT id, name, email, password FROM super_admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin) {
        if (! password_verify($password, $admin['password'])) {
            return ['ok' => false, 'message' => 'Incorrect password for admin account. Remember: passwords are case-sensitive.'];
        }
        $_SESSION['ops_user_id'] = (int) $admin['id'];
        $_SESSION['ops_user'] = [
            'id' => (int) $admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'type' => 'super_admin',
            'role' => 'super_admin',
            'store_id' => null,
            'store_name' => null,
        ];
        $pdo->prepare('UPDATE super_admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);

        return ['ok' => true];
    }

    $stmtInactive = $pdo->prepare('SELECT id, is_active FROM staff WHERE email = ? LIMIT 1');
    $stmtInactive->execute([$email]);
    $inactiveStaff = $stmtInactive->fetch();
    if ($inactiveStaff && empty($inactiveStaff['is_active'])) {
        return ['ok' => false, 'message' => 'Your staff account has been deactivated. Please contact the platform super admin to be re-enabled.'];
    }

    $stmt = $pdo->prepare('SELECT s.*, ps.store_name FROM staff s LEFT JOIN partnership_stores ps ON ps.id = s.store_id WHERE s.email = ? AND s.is_active = 1');
    $stmt->execute([$email]);
    $staff = $stmt->fetch();
    if ($staff) {
        if (! password_verify($password, $staff['password'])) {
            return ['ok' => false, 'message' => 'Incorrect password for staff account. Remember: passwords are case-sensitive.'];
        }
        $staffType = ($staff['staff_type'] ?? 'platform') === 'store' ? 'store' : 'platform';
        $role = $staff['role'] ?? 'store_operator';
        $type = $staffType === 'store' ? 'store_staff' : 'platform_staff';
        $assignedStoreId = $staff['store_id'] ? (int) $staff['store_id'] : null;
        if ($staffType === 'store' && ! $assignedStoreId) {
            return ['ok' => false, 'message' => 'Store staff account found, but no store has been assigned. Contact the platform super admin to assign your store.'];
        }
        $_SESSION['ops_user_id'] = (int) $staff['id'];
        $_SESSION['ops_user'] = [
            'id' => (int) $staff['id'],
            'name' => $staff['full_name'],
            'email' => $staff['email'],
            'type' => $type,
            'role' => $role,
            'store_id' => $assignedStoreId,
            'store_name' => $assignedStoreId ? ($staff['store_name'] ?? null) : null,
            'staff_type' => $staffType,
        ];
        $pdo->prepare('UPDATE staff SET last_active_at = NOW() WHERE id = ?')->execute([$staff['id']]);

        return ['ok' => true];
    }

    $stmt = $pdo->prepare('SELECT * FROM partnership_stores WHERE owner_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $store = $stmt->fetch();
    if ($store) {
        if (empty($store['owner_password']) || ! password_verify($password, $store['owner_password'])) {
            return ['ok' => false, 'message' => 'Incorrect password for store-owner account. Remember: passwords are case-sensitive.'];
        }
        $_SESSION['ops_user_id'] = (int) $store['id'];
        $_SESSION['ops_user'] = [
            'id' => (int) $store['id'],
            'name' => $store['owner_name'] ?: ($store['store_name'].' Owner'),
            'email' => $store['owner_email'],
            'type' => 'store_owner',
            'role' => 'store_manager',
            'store_id' => (int) $store['id'],
            'store_name' => $store['store_name'],
            'staff_type' => 'store_owner',
        ];

        return ['ok' => true];
    }

    return [
        'ok' => false,
        'message' => 'No account exists with that email on this Management Staff Panel.',
        'hint' => 'This panel is only for Super Admin / Platform Dispatch Staff / Store Staff / Store Owners. '
                   .'If you are logging in to the Point-of-Sale / Inventory dashboard, use the Laravel app at http://127.0.0.1:8000/login '
                   .'(POS uses separate accounts like admin@example.com or cashier@example.com).',
    ];
}

function logoutManagement(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool
{
    return $token !== null && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrfToken()).'">';
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;

        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney(float $amount): string
{
    return CURRENCY.number_format($amount, 2);
}

function formatDateTime(?string $datetime): string
{
    if (! $datetime) {
        return '—';
    }

    return date('M d, Y h:i A', strtotime($datetime));
}

function formatDate(?string $date, string $format = 'M d, Y'): string
{
    if (! $date) {
        return '—';
    }

    return date($format, strtotime($date));
}

function redirectOps(string $path): void
{
    header('Location: '.APP_URL.$path);
    exit;
}

function redirectOpsAfterLogin(): void
{
    $user = $_SESSION['ops_user'] ?? null;
    $type = $user['type'] ?? '';
    switch ($type) {
        case 'platform_staff':
            redirectOps('/orders.php?tab=kanban');
        case 'store_staff':
        case 'store_owner':
            redirectOps('/menu.php');
        case 'super_admin':
        default:
            redirectOps('/dashboard.php');
    }
}

function requireDispatchAccess(): void
{
    requireOpsLogin();
    if (! isPlatformStaff()) {
        flash('error', 'Dispatch is only available to Motobook management staff. Store accounts are restricted to Store Workspace.');
        redirectOps('/menu.php');
    }
}

function requireStoreAccess(): void
{
    requireOpsLogin();
}

function statusBadge(string $status): string
{
    $map = [
        'placed' => 'badge-info',
        'preparing' => 'badge-warning',
        'driver_assigned' => 'badge-info',
        'out_for_delivery' => 'badge-success',
        'delayed' => 'badge-danger',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-muted',
        'pending' => 'badge-warning',
        'collected' => 'badge-info',
        'remitted' => 'badge-success',
        'open' => 'badge-warning',
        'in_review' => 'badge-info',
        'in_progress' => 'badge-info',
        'resolved' => 'badge-success',
        'rejected' => 'badge-danger',
        'approved' => 'badge-success',
        'paused' => 'badge-warning',
        'offline' => 'badge-muted',
        'active' => 'badge-success',
        'on_break' => 'badge-warning',
        'clocked_in' => 'badge-info',
        'clocked_out' => 'badge-muted',
        'online' => 'badge-success',
        'on_trip' => 'badge-warning',
        'idle' => 'badge-info',
        'on_duty' => 'badge-info',
        'suspended' => 'badge-danger',
        'inactive' => 'badge-muted',
        'on_shift' => 'badge-success',
        'off_shift' => 'badge-muted',
    ];
    $class = $map[strtolower($status)] ?? 'badge-muted';

    return '<span class="badge '.$class.'">'.e(ucwords(str_replace('_', ' ', $status))).'</span>';
}

function roleLabel(string $role): string
{
    $labels = [
        'super_admin' => 'Super Admin',
        'order_approver' => 'Order Approver',
        'inventory_manager' => 'Inventory Manager',
        'support_representative' => 'Support Representative',
        'store_operator' => 'Store Operator',
        'operations_manager' => 'Operations Manager',
        'store_manager' => 'Store Manager',
    ];

    return $labels[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function isActivePage(string $page): string
{
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function pageTitle(string $title): string
{
    return e($title).' — '.APP_NAME;
}

function renderStars(float $rating): string
{
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = '<span class="stars" aria-label="Rating: '.number_format($rating, 1).' out of 5">';
    $starSvg = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
    for ($i = 0; $i < $full; $i++) {
        $html .= str_replace('<svg', '<svg class="star-full"', $starSvg);
    }
    if ($half) {
        $html .= str_replace('<svg', '<svg class="star-half"', $starSvg);
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= str_replace('<svg', '<svg class="star-empty"', $starSvg);
    }
    $html .= ' <small>('.number_format($rating, 1).')</small></span>';

    return $html;
}

function delayThreshold(): int
{
    return max(5, (int) settingValue('delay_threshold_minutes', '35'));
}

function settingValue(string $key, string $default = '0'): string
{
    $pdo = getOpsDB();
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        return (string) ($row['setting_value'] ?? $default);
    } catch (Throwable $e) {
        return $default;
    }
}

function allSettings(): array
{
    $pdo = getOpsDB();
    try {
        $rows = $pdo->query('SELECT setting_key, setting_value, setting_label, is_locked_for_staff FROM platform_settings')->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = $r;
        }

        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function storeScopeWhere(): string
{
    $sid = currentUserStoreId();

    return $sid ? ' AND store_id = '.$sid : '';
}

function dashboardKpis(): array
{
    $pdo = getOpsDB();
    $threshold = delayThreshold();
    $storeFilter = storeScopeWhere();
    $isPlatform = isPlatformStaff();

    $sqlStore = $storeFilter;
    $activeOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('delivered','cancelled') {$sqlStore}")->fetchColumn();
    $delayed = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE (order_status = 'delayed' OR (order_status NOT IN ('delivered','cancelled') AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > {$threshold})) {$sqlStore}")->fetchColumn();
    $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM complaint_tickets WHERE status IN ('open','in_review')".$sqlStore)->fetchColumn();
    $pendingPromos = (int) $pdo->query("SELECT COUNT(*) FROM store_promos WHERE status = 'pending'".$sqlStore)->fetchColumn();
    $pendingRemit = 0;
    $ridersOnShift = 0;
    if ($isPlatform) {
        $pendingRemit = (int) $pdo->query("SELECT COUNT(*) FROM rider_daily_collections WHERE remittance_status = 'pending' AND collection_date = CURDATE()")->fetchColumn();
        $ridersOnShift = (int) $pdo->query("SELECT COUNT(*) FROM rider_shifts WHERE shift_date = CURDATE() AND shift_status IN ('clocked_in','active','on_break')")->fetchColumn();
    }

    return [
        'active_orders' => $activeOrders,
        'delayed_orders' => $delayed,
        'open_tickets' => $openTickets,
        'pending_remittance' => $pendingRemit,
        'riders_on_shift' => $ridersOnShift,
        'pending_promos' => $pendingPromos,
    ];
}

function decorateOrderRow(array $row): array
{
    $threshold = delayThreshold();
    $terminal = in_array($row['order_status'] ?? '', ['delivered', 'cancelled'], true);
    $age = 0;
    if (! empty($row['created_at'])) {
        $age = (int) ((time() - strtotime((string) $row['created_at'])) / 60);
    }
    $row['age_minutes'] = $age;
    $row['is_delayed'] = ! $terminal && (($row['order_status'] ?? '') === 'delayed' || $age > $threshold);
    $row['display_status'] = $row['is_delayed'] && ($row['order_status'] ?? '') !== 'delayed' ? 'delayed' : ($row['order_status'] ?? '');

    return $row;
}

function fetchOrders(?string $status = null, int $limit = 100): array
{
    $pdo = getOpsDB();
    $threshold = delayThreshold();
    $sql = 'SELECT o.*, ps.store_name, r.full_name AS rider_name, r.duty_status AS rider_duty
            FROM orders o
            LEFT JOIN partnership_stores ps ON ps.id = o.store_id
            LEFT JOIN riders r ON r.id = o.rider_id
            WHERE 1=1';
    $params = [];
    if (currentUserStoreId()) {
        $sql .= ' AND o.store_id = ?';
        $params[] = currentUserStoreId();
    }
    $statusFilter = $status ?? 'all';
    if ($statusFilter !== 'all' && $statusFilter !== '') {
        if ($statusFilter === 'delayed') {
            $sql .= " AND o.order_status NOT IN ('delivered','cancelled') AND (o.order_status = 'delayed' OR TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > ?)";
            $params[] = $threshold;
        } else {
            $sql .= ' AND o.order_status = ?';
            $params[] = $statusFilter;
        }
    } else {
        $sql .= " AND o.order_status NOT IN ('delivered','cancelled')";
    }
    $sql .= ' ORDER BY o.created_at DESC LIMIT '.$limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map(fn ($r) => decorateOrderRow($r), $stmt->fetchAll());
}

function fetchOrderById(int $id): ?array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT o.*, ps.store_name, r.full_name AS rider_name FROM orders o LEFT JOIN partnership_stores ps ON ps.id = o.store_id LEFT JOIN riders r ON r.id = o.rider_id WHERE o.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (! $row) {
        return null;
    }
    if (currentUserStoreId() && (int) $row['store_id'] !== currentUserStoreId()) {
        return null;
    }

    return decorateOrderRow($row);
}

function reassignOrderRider(int $orderId, int $riderId, string $reason = ''): bool
{
    $pdo = getOpsDB();
    $pdo->beginTransaction();
    try {
        $check = $pdo->prepare('SELECT rider_id, assigned_at FROM orders WHERE id = ?');
        $check->execute([$orderId]);
        $existing = $check->fetch();
        $isFirstAssign = $existing && empty($existing['rider_id']);
        $eventType = $isFirstAssign ? 'rider_assigned' : 'rider_reassigned';
        $eventNote = ($isFirstAssign ? 'Rider assigned: ' : 'Rider reassigned: ').($reason ?: 'Staff action');
        if ($isFirstAssign) {
            $pdo->prepare('UPDATE orders SET rider_id = ?, assigned_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$riderId, $orderId]);
        } else {
            $pdo->prepare('UPDATE orders SET rider_id = ?, updated_at = NOW() WHERE id = ?')->execute([$riderId, $orderId]);
        }
        $pdo->prepare("INSERT INTO order_events (order_id, actor_type, actor_id, event_type, notes, created_at) VALUES (?,'staff',?,?,?,NOW())")->execute([
            $orderId, currentUserId() ?: 0, $eventType, $eventNote,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();

        return false;
    }
    try {
        if (function_exists('apiPost')) {
            apiPost("orders/{$orderId}/rider-sync", ['rider_id' => $riderId]);
        }
    } catch (Throwable $_) {
    }
    try {
        if (function_exists('syncLegacyOrderToRiderLocal')) {
            syncLegacyOrderToRiderLocal($orderId, $riderId);
        } else {
            $pdo = getOpsDB();
            $has = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_orders'");
            $has->execute();
            if (((int) $has->fetchColumn()) > 0) {
                syncLegacyOrderToRiderLocal($orderId, $riderId);
            }
        }
    } catch (Throwable $_) {
    }

    return true;
}

function syncLegacyOrderToRiderLocal(int $orderId, ?int $riderId, ?float $dropoffLat = null, ?float $dropoffLng = null): ?array
{
    $pdo = getOpsDB();
    $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_orders'");
    $check->execute();
    if (((int) $check->fetchColumn()) <= 0) {
        return null;
    }
    $stmtLegacy = $pdo->prepare('SELECT o.*, ps.store_name AS merchant_name,
        ps.branch_address AS merchant_address, ps.latitude AS merchant_lat, ps.longitude AS merchant_lng
        FROM orders o LEFT JOIN partnership_stores ps ON ps.id = o.store_id WHERE o.id = ? LIMIT 1');
    $stmtLegacy->execute([$orderId]);
    $legacy = $stmtLegacy->fetch();
    if (! $legacy) {
        return null;
    }
    $stmtItems = $pdo->prepare('SELECT item_name_snapshot AS name, qty, unit_price_snapshot AS unit_price, subtotal_snapshot AS subtotal
        FROM order_items WHERE order_id = ? ORDER BY id');
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll();
    $itemsJson = $items ? json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $orderCode = ! empty($legacy['order_number']) ? (string) $legacy['order_number'] : ('MB-SYNC-'.$orderId);
    $deliveryFee = (float) ($legacy['delivery_fee'] ?? 0);
    $orderTotal = (float) ($legacy['order_total'] ?? 0);
    $payMethod = strtolower((string) ($legacy['payment_method'] ?? 'cash'));
    $codAmount = $payMethod === 'cash' ? $orderTotal : 0.0;
    $payout = max(0.0, $deliveryFee);
    $tip = 0.0;
    $now = date('Y-m-d H:i:s');
    $expires = $riderId === null ? date('Y-m-d H:i:s', time() + 60) : null;
    $state = $riderId === null ? 'OFFER_RECEIVED' : 'ACCEPTED';
    $acceptedAt = $riderId === null ? null : $now;
    $merchantOrderId = strtoupper(substr('MB'.substr(md5((string) $orderId), 0, 6), 0, 8));
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
         items_json, special_notes, state, accepted_at, offer_received_at, offer_expires_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            rider_id = VALUES(rider_id),
            merchant_name = VALUES(merchant_name), merchant_lat = VALUES(merchant_lat), merchant_lng = VALUES(merchant_lng),
            merchant_address = VALUES(merchant_address), dropoff_name = VALUES(dropoff_name),
            dropoff_lat = COALESCE(VALUES(dropoff_lat), dropoff_lat),
            dropoff_lng = COALESCE(VALUES(dropoff_lng), dropoff_lng),
            dropoff_address = VALUES(dropoff_address), dropoff_phone = VALUES(dropoff_phone),
            payout_amount = VALUES(payout_amount), cod_amount = VALUES(cod_amount), items_json = VALUES(items_json),
            special_notes = VALUES(special_notes),
            state = CASE WHEN state IN ('COMPLETED','CANCELLED','OFFER_EXPIRED','OFFER_REJECTED') THEN state ELSE VALUES(state) END,
            accepted_at = CASE WHEN VALUES(rider_id) IS NULL THEN NULL ELSE COALESCE(accepted_at, VALUES(accepted_at), NOW()) END,
            offer_received_at = VALUES(offer_received_at), offer_expires_at = VALUES(offer_expires_at), updated_at = NOW()")
        ->execute([
            $riderId, $orderCode, $merchantOrderId, $storeId, $orderId, 'INHOUSE',
            $mName, $mLat, $mLng, $mAddr, $dName, null, null, $dAddr, $dPhone,
            0.0, 0, $payout, $tip, $codAmount, $itemsJson, $notes, $state, $acceptedAt, $now, $expires,
        ]);
    if ($dropoffLat !== null && $dropoffLng !== null) {
        $pdo->prepare('UPDATE rider_orders SET dropoff_lat = ?, dropoff_lng = ? WHERE shared_order_id = ?')
            ->execute([$dropoffLat, $dropoffLng, $orderId]);
    }
    $find = $pdo->prepare('SELECT id FROM rider_orders WHERE shared_order_id = ? LIMIT 1');
    $find->execute([$orderId]);
    $row = $find->fetch();
    $roId = $row ? (int) $row['id'] : null;
    if ($roId) {
        $actor = currentUser()['email'] ?? '';
        $pdo->prepare("INSERT IGNORE INTO order_events (order_id, event_type, notes, actor_email, created_at)
            VALUES (?, 'rider_sync', CONCAT('Synced to rider_orders #', ?), ?, NOW())")
            ->execute([$orderId, $roId, $actor]);
    }

    return ['rider_order_id' => $roId, 'order_code' => $orderCode, 'state' => $state];
}

function parseGoogleMapsCoordinates(string $location): ?array
{
    $location = rawurldecode(trim($location));
    if ($location === '') {
        return null;
    }

    $patterns = [
        '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/i',
        '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        '/(?:^|[?&=])(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)(?:$|[,&\s])/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $location, $matches) !== 1) {
            continue;
        }

        $lat = (float) $matches[1];
        $lng = (float) $matches[2];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat !== 0.0 || $lng !== 0.0)) {
            return ['lat' => $lat, 'lng' => $lng];
        }
    }

    return null;
}

function fetchActiveRiders(): array
{
    $pdo = getOpsDB();

    return $pdo->query("SELECT r.*, (SELECT shift_status FROM rider_shifts WHERE rider_id = r.id AND shift_date = CURDATE() ORDER BY id DESC LIMIT 1) AS shift_status FROM riders r WHERE r.status NOT IN ('suspended','inactive') ORDER BY r.full_name")->fetchAll();
}

function fetchTodayShifts(): array
{
    $pdo = getOpsDB();

    return $pdo->query('SELECT rs.*, r.full_name, r.rider_code FROM rider_shifts rs LEFT JOIN riders r ON r.id = rs.rider_id WHERE rs.shift_date = CURDATE() ORDER BY rs.id DESC')->fetchAll();
}

function logRiderIncident(int $riderId, string $type, string $notes): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare('INSERT INTO rider_incidents (rider_id, incident_type, notes, reported_by, reported_at) VALUES (?,?,?,?,NOW())')->execute([
            $riderId, $type, $notes, currentUserId() ?: 0,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchRiderIncidents(int $limit = 50): array
{
    $pdo = getOpsDB();

    return $pdo->query("SELECT ri.*, r.full_name AS rider_name FROM rider_incidents ri LEFT JOIN riders r ON r.id = ri.rider_id ORDER BY ri.id DESC LIMIT {$limit}")->fetchAll();
}

function fetchTickets(?string $status = null): array
{
    $pdo = getOpsDB();
    $sql = 'SELECT t.*, ps.store_name, r.full_name AS rider_name, o.order_number
            FROM complaint_tickets t
            LEFT JOIN partnership_stores ps ON ps.id = t.store_id
            LEFT JOIN riders r ON r.id = t.rider_id
            LEFT JOIN orders o ON o.id = t.order_id
            WHERE 1=1';
    $params = [];
    if (currentUserStoreId()) {
        $sql .= ' AND t.store_id = ?';
        $params[] = currentUserStoreId();
    }
    if ($status) {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY t.id DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function fetchTicketById(int $id): ?array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT t.*, ps.store_name, r.full_name AS rider_name, o.order_number, o.order_total
        FROM complaint_tickets t
        LEFT JOIN partnership_stores ps ON ps.id = t.store_id
        LEFT JOIN riders r ON r.id = t.rider_id
        LEFT JOIN orders o ON o.id = t.order_id
        WHERE t.id = ?');
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (! $t) {
        return null;
    }
    if (currentUserStoreId() && (int) $t['store_id'] !== currentUserStoreId()) {
        return null;
    }

    return $t;
}

function resolveTicket(int $id, string $action, string $notes, float $refundAmount = 0): bool
{
    $pdo = getOpsDB();
    try {
        $status = $action === 'reject' ? 'rejected' : 'resolved';
        $pdo->prepare('UPDATE complaint_tickets SET status = ?, resolution_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?')->execute([
            $status, $notes, currentUserId() ?: 0, $id,
        ]);
        if ($action === 'refund' && $refundAmount > 0) {
            $pdo->prepare('UPDATE complaint_tickets SET refund_amount = ? WHERE id = ?')->execute([$refundAmount, $id]);
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchStores(): array
{
    $pdo = getOpsDB();
    $sql = 'SELECT ps.*,
            (SELECT name FROM store_categories sc WHERE sc.id = ps.category_id LIMIT 1) AS category_name,
            (SELECT COUNT(*) FROM orders o WHERE o.store_id = ps.id) AS total_orders
            FROM partnership_stores ps';
    if (currentUserStoreId()) {
        $sql .= ' WHERE ps.id = '.currentUserStoreId();
    }
    $sql .= ' ORDER BY ps.store_name';
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        $fallback = 'SELECT * FROM partnership_stores ps';
        if (currentUserStoreId()) {
            $fallback .= ' WHERE ps.id = '.currentUserStoreId();
        }
        $fallback .= ' ORDER BY ps.store_name';

        return $pdo->query($fallback)->fetchAll();
    }
}

function setStoreStatus(int $storeId, string $status): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare('UPDATE partnership_stores SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $storeId]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function updateStoreOnboarding(int $storeId, array $fields): bool
{
    $pdo = getOpsDB();
    try {
        $allowed = ['store_name', 'branch_address', 'contact_phone', 'contact_email', 'operating_hours', 'owner_name', 'owner_email'];
        $updates = [];
        $params = [];
        foreach ($allowed as $f) {
            if (isset($fields[$f])) {
                $updates[] = "{$f} = ?";
                $params[] = $fields[$f];
            }
        }
        if (! empty($fields['owner_password'])) {
            $updates[] = 'owner_password = ?';
            $params[] = password_hash($fields['owner_password'], PASSWORD_DEFAULT);
        }
        if (! $updates) {
            return true;
        }
        $updates[] = 'updated_at = NOW()';
        $params[] = $storeId;
        $pdo->prepare('UPDATE partnership_stores SET '.implode(', ', $updates).' WHERE id = ?')->execute($params);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchMenu(int $storeId): array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT * FROM store_menu_items WHERE store_id = ? ORDER BY category, item_name');
    $stmt->execute([$storeId]);

    return $stmt->fetchAll();
}

function toggleMenuItem(int $itemId): bool
{
    $pdo = getOpsDB();
    try {
        $row = $pdo->prepare('SELECT store_id, is_available FROM store_menu_items WHERE id = ?')->fetch();
        $pdo->prepare('UPDATE store_menu_items SET is_available = CASE WHEN is_available = 1 THEN 0 ELSE 1 END, updated_at = NOW() WHERE id = ?')->execute([$itemId]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function addMenuItem(int $storeId, string $name, string $category, float $price): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare('INSERT INTO store_menu_items (store_id, item_name, category, price, is_available, created_at, updated_at) VALUES (?,?,?,?,1,NOW(),NOW())')->execute([$storeId, $name, $category, $price]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchHelpdeskTickets(): array
{
    $pdo = getOpsDB();
    $sql = 'SELECT m.*, ps.store_name FROM merchant_help_tickets m LEFT JOIN partnership_stores ps ON ps.id = m.store_id WHERE 1=1';
    if (currentUserStoreId()) {
        $sql .= ' AND m.store_id = '.currentUserStoreId();
    }
    $sql .= ' ORDER BY m.id DESC LIMIT 100';

    return $pdo->query($sql)->fetchAll();
}

function createHelpdeskTicket(string $subject, string $category, string $message): bool
{
    $sid = currentUserStoreId();
    if (! $sid) {
        return false;
    }
    $pdo = getOpsDB();
    try {
        $num = 'HD-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO merchant_help_tickets (ticket_number, store_id, category, subject, message, status, created_at, updated_at) VALUES (?,?,?,?,?,\'open\',NOW(),NOW())')->execute([
            $num, $sid, $category, $subject, $message,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function replyHelpdeskTicket(int $id, string $reply): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare('UPDATE merchant_help_tickets SET staff_reply = ?, status = \'resolved\', updated_at = NOW() WHERE id = ?')->execute([
            $reply, $id,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchBanners(): array
{
    $pdo = getOpsDB();

    return $pdo->query('SELECT b.*, ps.store_name FROM promo_banners b LEFT JOIN partnership_stores ps ON ps.id = b.store_id ORDER BY b.created_at DESC, b.id DESC')->fetchAll();
}

function createBanner(array $fields, ?array $file = null): bool
{
    $pdo = getOpsDB();
    try {
        $imagePath = '';
        if ($file && ! empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $uploadDir = dirname(__DIR__, 2).'/admin/uploads/banners';
            if (! is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $name = 'banner_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name'] ?? 'img.png'));
            $dest = $uploadDir.'/'.$name;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $imagePath = 'uploads/banners/'.$name;
            }
        }
        $pdo->prepare('INSERT INTO promo_banners (title, caption, hex_color, store_id, image_path, is_active, created_at) VALUES (?,?,?,?,?,1,NOW())')->execute([
            $fields['title'], $fields['caption'] ?? '', $fields['hex_color'] ?? '#06b6d4', (int) ($fields['store_id'] ?? 0) ?: null, $imagePath,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchPromos(): array
{
    $pdo = getOpsDB();
    $sql = 'SELECT p.*, ps.store_name, p.submitted_by AS submitted_by_name, rs.full_name AS reviewed_by_name
            FROM store_promos p
            LEFT JOIN partnership_stores ps ON ps.id = p.store_id
            LEFT JOIN staff rs ON rs.id = p.reviewed_by_staff_id';
    if (currentUserStoreId()) {
        $sql .= ' WHERE p.store_id = '.currentUserStoreId();
    }
    $sql .= ' ORDER BY p.id DESC LIMIT 200';

    return $pdo->query($sql)->fetchAll();
}

function submitPromo(array $fields): bool
{
    $sid = currentUserStoreId();
    if (! $sid) {
        return false;
    }
    $pdo = getOpsDB();
    try {
        $submitter = currentUser()['name'] ?? 'Store';
        $pdo->prepare('INSERT INTO store_promos (store_id, title, description, discount_percent, status, submitted_by, created_at, updated_at) VALUES (?,?,?,?,?,\'pending\',?,NOW(),NOW())')->execute([
            $sid, $fields['title'], $fields['description'] ?? '', (float) ($fields['discount_percent'] ?? 0), $submitter,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function reviewPromo(int $id, string $status, string $notes): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare('UPDATE store_promos SET status = ?, review_notes = ?, reviewed_by_staff_id = ?, updated_at = NOW() WHERE id = ?')->execute([
            $status, $notes, currentUserId() ?: null, $id,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchRemittance(string $date): array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT rdc.*, r.full_name, r.rider_code FROM rider_daily_collections rdc LEFT JOIN riders r ON r.id = rdc.rider_id WHERE rdc.collection_date = ? ORDER BY rdc.id DESC');
    $stmt->execute([$date]);

    return $stmt->fetchAll();
}

function markCollectionCollected(int $id): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare('UPDATE rider_daily_collections SET remittance_status = \'collected\', collected_by_staff_id = ?, collected_at = NOW() WHERE id = ?')->execute([
            currentUserId() ?: 0, $id,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function orderBucket(?string $raw): string
{
    $bucket = (string) ($raw ?? '');
    if ($bucket === 'picked_up') {
        $bucket = 'out_for_delivery';
    }
    if ($bucket === 'pending') {
        $bucket = 'placed';
    }
    $allowed = ['placed', 'preparing', 'driver_assigned', 'out_for_delivery', 'delayed'];

    return in_array($bucket, $allowed, true) ? $bucket : 'placed';
}

function canDeleteMenu(?array $user): bool
{
    if (! $user) {
        return false;
    }
    $type = $user['type'] ?? '';
    if ($type === 'super_admin') {
        return true;
    }
    if ($type === 'platform_staff') {
        $role = $user['role'] ?? '';

        return in_array($role, ['inventory_manager', 'order_approver'], true);
    }
    if ($type === 'store_owner') {
        return true;
    }

    return false;
}

function fetchMenuWithCounts(int $storeId): array
{
    $pdo = getOpsDB();
    $sql = 'SELECT i.id, i.store_id, i.item_name, i.category, i.price, i.image_path, i.description, i.groups_json, i.is_available, i.created_at, i.updated_at,
                   (SELECT COUNT(*) FROM store_menu_item_option_groups g WHERE g.item_id = i.id) AS group_count,
                   (SELECT COUNT(*) FROM store_menu_item_options o
                      INNER JOIN store_menu_item_option_groups g ON g.id = o.group_id
                      WHERE g.item_id = i.id) AS option_count
            FROM store_menu_items i
            WHERE i.store_id = ?
            ORDER BY i.category, i.item_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$storeId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        if (! empty($row['groups_json']) && is_string($row['groups_json'])) {
            $dec = json_decode($row['groups_json'], true);
            $row['groups_json'] = is_array($dec) ? $dec : [];
        } else {
            $row['groups_json'] = [];
        }
    }
    unset($row);

    return $rows;
}

function fetchItemWithOptions(int $itemId): ?array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT * FROM store_menu_items WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (! $item) {
        return null;
    }
    $gStmt = $pdo->prepare('SELECT * FROM store_menu_item_option_groups WHERE item_id = ? ORDER BY sort_order, id');
    $gStmt->execute([$itemId]);
    $groups = $gStmt->fetchAll();
    $oStmt = $pdo->prepare('SELECT * FROM store_menu_item_options WHERE group_id = ? ORDER BY sort_order, id');
    foreach ($groups as &$g) {
        $oStmt->execute([$g['id']]);
        $g['options'] = $oStmt->fetchAll();
    }
    unset($g);
    $item['groups'] = $groups;

    return $item;
}

function saveItemWithOptions(int $storeId, array $itemData, array $groups): int|false
{
    $pdo = getOpsDB();
    $pdo->beginTransaction();
    try {
        $name = trim((string) ($itemData['item_name'] ?? ''));
        $category = trim((string) ($itemData['category'] ?? ''));
        $price = (float) ($itemData['price'] ?? 0);
        $description = (string) ($itemData['description'] ?? '');
        $imagePath = isset($itemData['image_path']) ? (string) $itemData['image_path'] : null;
        $isAvailable = isset($itemData['is_available']) ? (int) (bool) $itemData['is_available'] : 1;
        $itemId = isset($itemData['id']) ? (int) $itemData['id'] : 0;
        if ($name === '' || $category === '' || $price < 0) {
            $pdo->rollBack();

            return false;
        }
        $cleanGroups = [];
        $groupSort = 0;
        foreach ($groups as $g) {
            $groupName = trim((string) ($g['group_name'] ?? ''));
            if ($groupName === '') {
                continue;
            }
            $selType = ($g['selection_type'] ?? 'radio') === 'checkbox' ? 'checkbox' : 'radio';
            $minSel = max(0, (int) ($g['min_select'] ?? 0));
            $maxSel = max($minSel, (int) ($g['max_select'] ?? 1));
            if ($selType === 'radio') {
                $minSel = min($minSel, 1);
                $maxSel = 1;
            }
            $isReq = ! empty($g['is_required']) ? 1 : 0;
            $cleanOptions = [];
            $options = $g['options'] ?? [];
            if (is_array($options)) {
                $optSort = 0;
                foreach ($options as $o) {
                    $optName = trim((string) ($o['option_name'] ?? ''));
                    if ($optName === '') {
                        continue;
                    }
                    $delta = (float) ($o['price_delta'] ?? 0);
                    $avail = 1;
                    if (array_key_exists('is_available', $o)) {
                        $avail = (int) (bool) $o['is_available'];
                    }
                    $cleanOptions[] = [
                        'option_name' => $optName,
                        'price_delta' => round($delta, 2),
                        'is_available' => $avail,
                        'sort_order' => $optSort++,
                    ];
                }
            }
            if (empty($cleanOptions)) {
                continue;
            }
            $cleanGroups[] = [
                'group_name' => $groupName,
                'selection_type' => $selType,
                'min_select' => $minSel,
                'max_select' => $maxSel,
                'is_required' => $isReq,
                'sort_order' => $groupSort++,
                'options' => $cleanOptions,
            ];
        }
        $groupsJson = $cleanGroups ? json_encode($cleanGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($itemId > 0) {
            $scope = isStoreStaff() ? 'AND store_id = '.(int) $storeId : '';
            $check = $pdo->prepare('SELECT id, image_path FROM store_menu_items WHERE id = ? '.$scope);
            $check->execute([$itemId]);
            $existing = $check->fetch();
            if (! $existing) {
                $pdo->rollBack();

                return false;
            }
            if ($imagePath === null) {
                $imagePath = $existing['image_path'];
            }
            $pdo->prepare('UPDATE store_menu_items
                SET item_name = ?, category = ?, price = ?, description = ?, groups_json = ?, image_path = ?, is_available = ?, updated_at = NOW()
                WHERE id = ?')->execute([$name, $category, $price, $description, $groupsJson, $imagePath, $isAvailable, $itemId]);
            $pdo->prepare('DELETE g, o FROM store_menu_item_option_groups g
                LEFT JOIN store_menu_item_options o ON o.group_id = g.id
                WHERE g.item_id = ?')->execute([$itemId]);
        } else {
            $pdo->prepare('INSERT INTO store_menu_items
                (store_id, item_name, category, price, description, groups_json, image_path, is_available, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$storeId, $name, $category, $price, $description, $groupsJson, $imagePath, $isAvailable]);
            $itemId = (int) $pdo->lastInsertId();
        }
        foreach ($cleanGroups as $g) {
            $pdo->prepare('INSERT INTO store_menu_item_option_groups
                (item_id, group_name, selection_type, min_select, max_select, is_required, sort_order, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,NOW(),NOW())')->execute([
                $itemId, $g['group_name'], $g['selection_type'], $g['min_select'], $g['max_select'], $g['is_required'], $g['sort_order'],
            ]);
            $groupId = (int) $pdo->lastInsertId();
            foreach ($g['options'] as $o) {
                $pdo->prepare('INSERT INTO store_menu_item_options
                    (group_id, option_name, price_delta, is_available, sort_order, created_at, updated_at)
                    VALUES (?,?,?,?,?,NOW(),NOW())')->execute([
                    $groupId, $o['option_name'], $o['price_delta'], $o['is_available'], $o['sort_order'],
                ]);
            }
        }
        $pdo->commit();

        return $itemId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('saveItemWithOptions failed: '.$e->getMessage());

        return false;
    }
}

function deleteMenuItem(int $id, ?int $storeScope): bool
{
    $pdo = getOpsDB();
    try {
        $scope = $storeScope ? 'AND store_id = '.(int) $storeScope : '';
        $stmt = $pdo->prepare('DELETE FROM store_menu_items WHERE id = ? '.$scope);
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchOrderWithItems(int $id): ?array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT o.*, ps.store_name, r.full_name AS rider_name FROM orders o
        LEFT JOIN partnership_stores ps ON ps.id = o.store_id
        LEFT JOIN riders r ON r.id = o.rider_id
        WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (! $order) {
        return null;
    }
    if (currentUserStoreId() && (int) $order['store_id'] !== currentUserStoreId()) {
        return null;
    }
    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll();
    $optsStmt = $pdo->prepare('SELECT * FROM order_item_options WHERE order_item_id = ? ORDER BY id');
    foreach ($items as &$it) {
        $optsStmt->execute([$it['id']]);
        $it['options'] = $optsStmt->fetchAll();
    }
    unset($it);
    $order['items'] = $items;

    return decorateOrderRow($order);
}

function generateOrderNumber(): string
{
    $pdo = getOpsDB();
    $pdo->prepare('INSERT IGNORE INTO orders (order_number) VALUES (?)')->execute(['__seq_hint__']);
    $seq = (int) $pdo->lastInsertId();
    if ($seq === 0) {
        $seq = random_int(100000, 999999);
    }

    return 'MB-'.date('Ymd').'-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
}

function createDirectOrder(array $customer, int $storeId, array $lineItems, int $placedByStaffId): int|false
{
    $pdo = getOpsDB();
    $pdo->beginTransaction();
    try {
        $customerName = trim((string) ($customer['customer_name'] ?? ''));
        $customerPhone = trim((string) ($customer['customer_phone'] ?? ''));
        $deliveryAddress = trim((string) ($customer['delivery_address'] ?? ''));
        $notes = trim((string) ($customer['notes'] ?? ''));
        if ($customerName === '' || $customerPhone === '') {
            $pdo->rollBack();

            return false;
        }
        $subtotal = 0.0;
        $validLines = [];
        $menuStmt = $pdo->prepare('SELECT id, item_name, price FROM store_menu_items WHERE id = ? AND store_id = ?');
        $groupStmt = $pdo->prepare('SELECT g.id AS group_id, g.group_name, g.selection_type, g.min_select, g.max_select, g.is_required,
            o.id AS option_id, o.option_name, o.price_delta, o.is_available
            FROM store_menu_item_option_groups g
            LEFT JOIN store_menu_item_options o ON o.group_id = g.id
            WHERE g.item_id = ?
            ORDER BY g.sort_order, g.id, o.sort_order, o.id');
        foreach ($lineItems as $line) {
            $menuItemId = ! empty($line['menu_item_id']) ? (int) $line['menu_item_id'] : 0;
            $qty = max(1, (int) ($line['qty'] ?? 1));
            $itemName = '';
            $unitPrice = 0.0;
            $selectedOptionIds = $line['selected_option_ids'] ?? [];
            $optionDeltas = [];
            if ($menuItemId > 0) {
                $menuStmt->execute([$menuItemId, $storeId]);
                $mi = $menuStmt->fetch();
                if (! $mi) {
                    continue;
                }
                $itemName = $mi['item_name'];
                $unitPrice = (float) $mi['price'];
                $groupStmt->execute([$menuItemId]);
                $rows = $groupStmt->fetchAll();
                $groups = [];
                foreach ($rows as $r) {
                    if (empty($groups[$r['group_id']])) {
                        $groups[$r['group_id']] = [
                            'group_id' => (int) $r['group_id'],
                            'group_name' => $r['group_name'],
                            'options' => [],
                        ];
                    }
                    if ($r['option_id']) {
                        $groups[$r['group_id']]['options'][(int) $r['option_id']] = [
                            'option_id' => (int) $r['option_id'],
                            'option_name' => $r['option_name'],
                            'price_delta' => (float) $r['price_delta'],
                        ];
                    }
                }
                if (! is_array($selectedOptionIds)) {
                    $selectedOptionIds = [];
                }
                $selectedOptionIds = array_map('intval', $selectedOptionIds);
                foreach ($groups as $g) {
                    foreach ($g['options'] as $oid => $opt) {
                        if (in_array($oid, $selectedOptionIds, true)) {
                            $unitPrice += (float) $opt['price_delta'];
                            $optionDeltas[] = [
                                'group_name' => $g['group_name'],
                                'option_name' => $opt['option_name'],
                                'price_delta' => (float) $opt['price_delta'],
                            ];
                        }
                    }
                }
            } else {
                $itemName = trim((string) ($line['item_name_snapshot'] ?? 'Custom Item'));
                $unitPrice = (float) ($line['unit_price_snapshot'] ?? 0);
                $opts = $line['options'] ?? [];
                if (is_array($opts)) {
                    foreach ($opts as $o) {
                        $d = (float) ($o['price_delta'] ?? 0);
                        $unitPrice += $d;
                        $optionDeltas[] = [
                            'group_name' => (string) ($o['group_name'] ?? ''),
                            'option_name' => (string) ($o['option_name'] ?? ''),
                            'price_delta' => $d,
                        ];
                    }
                }
            }
            if ($itemName === '') {
                continue;
            }
            $lineSub = round($unitPrice * $qty, 2);
            $subtotal += $lineSub;
            $validLines[] = [
                'menu_item_id' => $menuItemId ?: null,
                'item_name_snapshot' => $itemName,
                'qty' => $qty,
                'unit_price_snapshot' => $unitPrice,
                'subtotal_snapshot' => $lineSub,
                'options' => $optionDeltas,
            ];
        }
        if (! $validLines) {
            $pdo->rollBack();

            return false;
        }
        $deliveryFee = (float) ($customer['delivery_fee'] ?? 0);
        if ($deliveryFee <= 0) {
            try {
                $defaultFee = (float) settingValue('default_delivery_fee', '49');
                $deliveryFee = max(0, $defaultFee);
            } catch (Throwable $e) {
                $deliveryFee = 49.0;
            }
        }
        $orderTotal = round($subtotal + $deliveryFee, 2);
        $commissionRate = 0.0;
        try {
            $commissionRate = (float) settingValue('default_commission_rate', '0');
        } catch (Throwable $e) {
        }
        $commissionAmount = round($orderTotal * ($commissionRate / 100), 2);
        $orderNumber = 'MB-'.date('Ymd-His').'-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO orders
            (order_number, store_id, customer_name, customer_phone, delivery_address, notes, placed_by_staff_id, placed_at,
             order_total, delivery_fee, commission_amount, payment_method, payment_status, order_status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,?, \'placed\', NOW(), NOW())')->execute([
            $orderNumber, $storeId, $customerName, $customerPhone, $deliveryAddress, $notes,
            $placedByStaffId ?: null,
            $orderTotal, $deliveryFee, $commissionAmount,
            (string) ($customer['payment_method'] ?? 'cash'),
            (string) ($customer['payment_status'] ?? 'pending'),
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare('INSERT INTO order_items
            (order_id, menu_item_id, item_name_snapshot, qty, unit_price_snapshot, subtotal_snapshot, created_at)
            VALUES (?,?,?,?,?,?,NOW())');
        $optStmt = $pdo->prepare('INSERT INTO order_item_options
            (order_item_id, group_name_snapshot, option_name_snapshot, price_delta_snapshot)
            VALUES (?,?,?,?)');
        foreach ($validLines as $vl) {
            $itemStmt->execute([
                $orderId, $vl['menu_item_id'], $vl['item_name_snapshot'], $vl['qty'],
                $vl['unit_price_snapshot'], $vl['subtotal_snapshot'],
            ]);
            $orderItemId = (int) $pdo->lastInsertId();
            foreach ($vl['options'] as $od) {
                $optStmt->execute([
                    $orderItemId,
                    (string) ($od['group_name'] ?? ''),
                    (string) ($od['option_name'] ?? ''),
                    (float) $od['price_delta'],
                ]);
            }
        }
        $actorType = isPlatformStaff() ? 'staff' : (isStoreStaff() ? 'store' : 'staff');
        $pdo->prepare('INSERT INTO order_events (order_id, actor_type, actor_id, event_type, notes, created_at) VALUES (?,?,?,?,?,NOW())')->execute([
            $orderId, $actorType, $placedByStaffId ?: 0, 'order_created_direct', 'Direct order created from dispatch panel',
        ]);
        $pdo->commit();
        $coordinates = parseGoogleMapsCoordinates((string) ($customer['google_maps_location'] ?? ''));
        try {
            syncLegacyOrderToRiderLocal($orderId, null, $coordinates['lat'] ?? null, $coordinates['lng'] ?? null);
            autoAssignPendingRiderOrders($pdo);
        } catch (Throwable $exception) {
            error_log('Direct order rider dispatch failed: '.$exception->getMessage());
        }

        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
}

function updateOrderStatus(int $id, string $newStatus, ?int $actorId = null, ?int $riderId = null): bool
{
    $pdo = getOpsDB();
    $validStatuses = ['pending', 'placed', 'preparing', 'driver_assigned', 'out_for_delivery', 'delayed', 'picked_up', 'delivered', 'cancelled'];
    if (! in_array($newStatus, $validStatuses, true)) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $actorId = $actorId ?? currentUserId() ?? 0;
        $sets = ['order_status = ?', 'updated_at = NOW()'];
        $params = [$newStatus];
        $extraTs = '';
        $eventType = 'status_changed';
        $eventNote = 'Status changed to '.$newStatus;
        switch ($newStatus) {
            case 'placed':
                $sets[] = 'placed_at = COALESCE(placed_at, NOW())';
                break;
            case 'driver_assigned':
                $sets[] = 'assigned_at = COALESCE(assigned_at, NOW())';
                if ($riderId) {
                    $sets[] = 'rider_id = ?';
                    $params[] = $riderId;
                }
                $eventType = 'rider_assigned';
                $eventNote = 'Rider assigned and status set to driver_assigned';
                break;
            case 'out_for_delivery':
            case 'picked_up':
                $sets[] = 'in_transit_at = COALESCE(in_transit_at, NOW())';
                $eventType = 'out_for_delivery';
                $eventNote = 'Order marked in transit / picked up by rider';
                break;
            case 'delivered':
                $sets[] = 'in_transit_at = COALESCE(in_transit_at, NOW())';
                $sets[] = 'delivered_at = COALESCE(delivered_at, NOW())';
                $sets[] = "payment_status = CASE WHEN payment_status = 'pending' THEN 'paid' ELSE payment_status END";
                $eventType = 'delivered';
                $eventNote = 'Order marked as delivered';
                break;
            case 'cancelled':
                $eventType = 'cancelled';
                $eventNote = 'Order cancelled';
                break;
        }
        $params[] = $id;
        $sql = 'UPDATE orders SET '.implode(', ', $sets).' WHERE id = ?';
        $pdo->prepare($sql)->execute($params);
        $actorType = isPlatformStaff() ? 'staff' : (isStoreStaff() ? 'store' : 'system');
        $pdo->prepare('INSERT INTO order_events (order_id, actor_type, actor_id, event_type, notes, created_at) VALUES (?,?,?,?,?,NOW())')->execute([
            $id, $actorType, $actorId, $eventType, $eventNote,
        ]);
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
}
