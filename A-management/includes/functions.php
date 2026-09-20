<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

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
    return empty($_SESSION['ops_user_id']) ? null : (int)$_SESSION['ops_user_id'];
}

function currentUserStoreId(): ?int
{
    $u = currentUser();
    if (!$u) return null;
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
    if (!$u) return '';
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
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requirePlatform(): void
{
    requireOpsLogin();
    if (!isPlatformStaff()) {
        flash('error', 'This workspace is for Motobook management staff only.');
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

function loginManagement(string $email, string $password): bool
{
    $email = trim($email);
    $password = (string)$password;
    if ($email === '' || $password === '') return false;

    $pdo = getOpsDB();

    $stmt = $pdo->prepare('SELECT id, name, email, password FROM super_admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['ops_user_id'] = (int)$admin['id'];
        $_SESSION['ops_user'] = [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'type' => 'super_admin',
            'role' => 'super_admin',
            'store_id' => null,
            'store_name' => null,
        ];
        $pdo->prepare('UPDATE super_admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
        return true;
    }

    $stmt = $pdo->prepare('SELECT s.*, ps.store_name FROM staff s LEFT JOIN partnership_stores ps ON ps.id = s.store_id WHERE s.email = ? AND s.is_active = 1');
    $stmt->execute([$email]);
    $staff = $stmt->fetch();
    if ($staff && password_verify($password, $staff['password'])) {
        $type = ($staff['staff_type'] ?? 'platform') === 'store' ? 'store_staff' : 'platform_staff';
        $_SESSION['ops_user_id'] = (int)$staff['id'];
        $_SESSION['ops_user'] = [
            'id' => (int)$staff['id'],
            'name' => $staff['full_name'],
            'email' => $staff['email'],
            'type' => $type,
            'role' => $staff['role'],
            'store_id' => $staff['store_id'] ? (int)$staff['store_id'] : null,
            'store_name' => $staff['store_name'],
        ];
        $pdo->prepare('UPDATE staff SET last_active_at = NOW() WHERE id = ?')->execute([$staff['id']]);
        return true;
    }

    $stmt = $pdo->prepare('SELECT * FROM partnership_stores WHERE owner_email = ?');
    $stmt->execute([$email]);
    $store = $stmt->fetch();
    if ($store && !empty($store['owner_password']) && password_verify($password, $store['owner_password'])) {
        $_SESSION['ops_user_id'] = (int)$store['id'];
        $_SESSION['ops_user'] = [
            'id' => (int)$store['id'],
            'name' => $store['owner_name'] ?: ($store['store_name'] . ' Owner'),
            'email' => $store['owner_email'],
            'type' => 'store_owner',
            'role' => 'store_manager',
            'store_id' => (int)$store['id'],
            'store_name' => $store['store_name'],
        ];
        return true;
    }

    return false;
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
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
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
    return CURRENCY . number_format($amount, 2);
}

function formatDateTime(?string $datetime): string
{
    if (!$datetime) return '—';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatDate(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) return '—';
    return date($format, strtotime($date));
}

function redirectOps(string $path): void
{
    header('Location: ' . APP_URL . $path);
    exit;
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
    return '<span class="badge ' . $class . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
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
    return e($title) . ' — ' . APP_NAME;
}

function renderStars(float $rating): string
{
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = '<span class="stars">';
    for ($i = 0; $i < $full; $i++) $html .= '★';
    if ($half) $html .= '☆';
    for ($i = 0; $i < $empty; $i++) $html .= '☆';
    $html .= ' <small>(' . number_format($rating, 1) . ')</small></span>';
    return $html;
}

function delayThreshold(): int
{
    return max(5, (int)settingValue('delay_threshold_minutes', '35'));
}

function settingValue(string $key, string $default = '0'): string
{
    $pdo = getOpsDB();
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return (string)($row['setting_value'] ?? $default);
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
        foreach ($rows as $r) $map[$r['setting_key']] = $r;
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function storeScopeWhere(): string
{
    $sid = currentUserStoreId();
    return $sid ? ' AND store_id = ' . $sid : '';
}

function dashboardKpis(): array
{
    $pdo = getOpsDB();
    $threshold = delayThreshold();
    $storeFilter = storeScopeWhere();
    $isPlatform = isPlatformStaff();

    $sqlStore = $storeFilter;
    $activeOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('delivered','cancelled') {$sqlStore}")->fetchColumn();
    $delayed = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE (order_status = 'delayed' OR (order_status NOT IN ('delivered','cancelled') AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > {$threshold})) {$sqlStore}")->fetchColumn();
    $openTickets = (int)$pdo->query("SELECT COUNT(*) FROM complaint_tickets WHERE status IN ('open','in_review')" . $sqlStore)->fetchColumn();
    $pendingPromos = (int)$pdo->query("SELECT COUNT(*) FROM store_promos WHERE status = 'pending'" . $sqlStore)->fetchColumn();
    $pendingRemit = 0;
    $ridersOnShift = 0;
    if ($isPlatform) {
        $pendingRemit = (int)$pdo->query("SELECT COUNT(*) FROM rider_daily_collections WHERE remittance_status = 'pending' AND collection_date = CURDATE()")->fetchColumn();
        $ridersOnShift = (int)$pdo->query("SELECT COUNT(*) FROM rider_shifts WHERE shift_date = CURDATE() AND shift_status IN ('clocked_in','active','on_break')")->fetchColumn();
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
    if (!empty($row['created_at'])) {
        $age = (int)((time() - strtotime((string)$row['created_at'])) / 60);
    }
    $row['age_minutes'] = $age;
    $row['is_delayed'] = !$terminal && (($row['order_status'] ?? '') === 'delayed' || $age > $threshold);
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
    $sql .= ' ORDER BY o.created_at DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(fn($r) => decorateOrderRow($r), $stmt->fetchAll());
}

function fetchOrderById(int $id): ?array
{
    $pdo = getOpsDB();
    $stmt = $pdo->prepare('SELECT o.*, ps.store_name, r.full_name AS rider_name FROM orders o LEFT JOIN partnership_stores ps ON ps.id = o.store_id LEFT JOIN riders r ON r.id = o.rider_id WHERE o.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if (currentUserStoreId() && (int)$row['store_id'] !== currentUserStoreId()) return null;
    return decorateOrderRow($row);
}

function reassignOrderRider(int $orderId, int $riderId, string $reason = ''): bool
{
    $pdo = getOpsDB();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE orders SET rider_id = ?, updated_at = NOW() WHERE id = ?')->execute([$riderId, $orderId]);
        $pdo->prepare("INSERT INTO order_events (order_id, actor_type, actor_id, event_type, notes, created_at) VALUES (?,'staff',?,?,?,NOW())")->execute([
            $orderId, currentUserId() ?: 0, 'rider_reassigned', 'Rider reassigned: ' . ($reason ?: 'Staff action'),
        ]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

function fetchActiveRiders(): array
{
    $pdo = getOpsDB();
    return $pdo->query("SELECT r.*, (SELECT shift_status FROM rider_shifts WHERE rider_id = r.id AND shift_date = CURDATE() ORDER BY id DESC LIMIT 1) AS shift_status FROM riders r WHERE r.status NOT IN ('suspended','inactive') ORDER BY r.full_name")->fetchAll();
}

function fetchTodayShifts(): array
{
    $pdo = getOpsDB();
    return $pdo->query("SELECT rs.*, r.full_name, r.rider_code FROM rider_shifts rs LEFT JOIN riders r ON r.id = rs.rider_id WHERE rs.shift_date = CURDATE() ORDER BY rs.id DESC")->fetchAll();
}

function logRiderIncident(int $riderId, string $type, string $notes): bool
{
    $pdo = getOpsDB();
    try {
        $pdo->prepare("INSERT INTO rider_incidents (rider_id, incident_type, notes, reported_by, reported_at) VALUES (?,?,?,?,NOW())")->execute([
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
    if (!$t) return null;
    if (currentUserStoreId() && (int)$t['store_id'] !== currentUserStoreId()) return null;
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
        $sql .= ' WHERE ps.id = ' . currentUserStoreId();
    }
    $sql .= ' ORDER BY ps.store_name';
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        $fallback = 'SELECT * FROM partnership_stores ps';
        if (currentUserStoreId()) $fallback .= ' WHERE ps.id = ' . currentUserStoreId();
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
        $allowed = ['store_name','branch_address','contact_phone','contact_email','operating_hours','owner_name','owner_email'];
        $updates = [];
        $params = [];
        foreach ($allowed as $f) {
            if (isset($fields[$f])) {
                $updates[] = "{$f} = ?";
                $params[] = $fields[$f];
            }
        }
        if (!empty($fields['owner_password'])) {
            $updates[] = 'owner_password = ?';
            $params[] = password_hash($fields['owner_password'], PASSWORD_DEFAULT);
        }
        if (!$updates) return true;
        $updates[] = 'updated_at = NOW()';
        $params[] = $storeId;
        $pdo->prepare('UPDATE partnership_stores SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
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
        $sql .= ' AND m.store_id = ' . currentUserStoreId();
    }
    $sql .= ' ORDER BY m.id DESC LIMIT 100';
    return $pdo->query($sql)->fetchAll();
}

function createHelpdeskTicket(string $subject, string $category, string $message): bool
{
    $sid = currentUserStoreId();
    if (!$sid) return false;
    $pdo = getOpsDB();
    try {
        $num = 'HD-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
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
        if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $uploadDir = dirname(__DIR__, 2) . '/admin/uploads/banners';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $name = 'banner_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name'] ?? 'img.png'));
            $dest = $uploadDir . '/' . $name;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $imagePath = 'uploads/banners/' . $name;
            }
        }
        $pdo->prepare('INSERT INTO promo_banners (title, caption, hex_color, store_id, image_path, is_active, created_at) VALUES (?,?,?,?,?,1,NOW())')->execute([
            $fields['title'], $fields['caption'] ?? '', $fields['hex_color'] ?? '#06b6d4', (int)($fields['store_id'] ?? 0) ?: null, $imagePath,
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
        $sql .= ' WHERE p.store_id = ' . currentUserStoreId();
    }
    $sql .= ' ORDER BY p.id DESC LIMIT 200';
    return $pdo->query($sql)->fetchAll();
}

function submitPromo(array $fields): bool
{
    $sid = currentUserStoreId();
    if (!$sid) return false;
    $pdo = getOpsDB();
    try {
        $submitter = currentUser()['name'] ?? 'Store';
        $pdo->prepare('INSERT INTO store_promos (store_id, title, description, discount_percent, status, submitted_by, created_at, updated_at) VALUES (?,?,?,?,?,\'pending\',?,NOW(),NOW())')->execute([
            $sid, $fields['title'], $fields['description'] ?? '', (float)($fields['discount_percent'] ?? 0), $submitter,
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
    $stmt = $pdo->prepare("SELECT rdc.*, r.full_name, r.rider_code FROM rider_daily_collections rdc LEFT JOIN riders r ON r.id = rdc.rider_id WHERE rdc.collection_date = ? ORDER BY rdc.id DESC");
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
    $bucket = (string)($raw ?? '');
    if ($bucket === 'picked_up') $bucket = 'out_for_delivery';
    if ($bucket === 'pending') $bucket = 'placed';
    $allowed = ['placed','preparing','driver_assigned','out_for_delivery','delayed'];
    return in_array($bucket, $allowed, true) ? $bucket : 'placed';
}
