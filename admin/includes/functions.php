<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function requireLogin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function currentAdmin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, name, email FROM super_admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);

    return $stmt->fetch() ?: null;
}

function loginAdmin(string $email, string $password): bool
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, name, email, password FROM super_admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        return false;
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];

    $update = $pdo->prepare('UPDATE super_admins SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$admin['id']]);

    return true;
}

function logoutAdmin(): void
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

function redirect(string $path): void
{
    header('Location: ' . APP_URL . $path);
    exit;
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

function formatMoney(float $amount): string
{
    return CURRENCY . number_format($amount, 2);
}

function formatDate(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) {
        return '—';
    }

    return date($format, strtotime($date));
}

function formatDateTime(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }

    return date('M d, Y h:i A', strtotime($datetime));
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function statusBadge(string $status): string
{
    $map = [
        'active' => 'badge-success',
        'inactive' => 'badge-muted',
        'on_duty' => 'badge-info',
        'suspended' => 'badge-danger',
        'online' => 'badge-success',
        'on_trip' => 'badge-warning',
        'idle' => 'badge-info',
        'offline' => 'badge-muted',
        'open' => 'badge-success',
        'closed' => 'badge-muted',
        'paused' => 'badge-warning',
        'pending' => 'badge-warning',
        'collected' => 'badge-info',
        'remitted' => 'badge-success',
        'partial' => 'badge-info',
        'on_shift' => 'badge-success',
        'off_shift' => 'badge-muted',
        'break' => 'badge-warning',
    ];

    $class = $map[$status] ?? 'badge-muted';
    $label = ucwords(str_replace('_', ' ', $status));

    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function roleLabel(string $role): string
{
    $labels = [
        'super_admin' => 'Super Admin',
        'order_approver' => 'Order Approver',
        'inventory_manager' => 'Inventory Manager',
        'support_representative' => 'Support Representative',
        'store_operator' => 'Store Operator',
    ];

    return $labels[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function renderStars(float $rating): string
{
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = '<span class="stars">';

    for ($i = 0; $i < $full; $i++) {
        $html .= '★';
    }
    if ($half) {
        $html .= '☆';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '☆';
    }

    $html .= ' <small>(' . number_format($rating, 1) . ')</small></span>';

    return $html;
}

function pageTitle(string $title): string
{
    return e($title) . ' — ' . APP_NAME;
}

function isActivePage(string $page): string
{
    $current = basename($_SERVER['PHP_SELF']);

    return $current === $page ? 'active' : '';
}
