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
    $res = tryLoginAdmin($email, $password);
    return $res['ok'];
}

function tryLoginAdmin(string $email, string $password): array
{
    $email = trim($email);
    $password = (string)$password;
    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter email and password.'];
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, name, email, password FROM super_admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin) {
        if (!password_verify($password, $admin['password'])) {
            return ['ok' => false, 'message' => 'Incorrect super-admin password. Remember: passwords are case-sensitive.'];
        }
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email']= $admin['email'];
        $update = $pdo->prepare('UPDATE super_admins SET last_login_at = NOW() WHERE id = ?');
        $update->execute([$admin['id']]);

        return ['ok' => true];
    }

    $hint = '';
    try {
        $checkStaff = $pdo->prepare('SELECT COUNT(*) AS c FROM staff WHERE email = ? LIMIT 1');
        $checkStaff->execute([$email]);
        $staffCount = (int)($checkStaff->fetch()['c'] ?? 0);
        if ($staffCount > 0) {
            $hint = 'That email is registered as a Management Staff account, not a Super Admin. '
                  . 'Use the shared MotoBook sign-in page for your Management account.';
        } else {
            $checkOwner = $pdo->prepare('SELECT COUNT(*) AS c FROM partnership_stores WHERE owner_email = ? LIMIT 1');
            $checkOwner->execute([$email]);
            $ownerCount = (int)($checkOwner->fetch()['c'] ?? 0);
            if ($ownerCount > 0) {
                $hint = 'That email is registered as a Partnership Store owner account, not a Super Admin. '
                      . 'Use the shared MotoBook sign-in page for your Management account.';
            }
        }
    } catch (Throwable $e) {
        // ignore — hint is optional
    }

    $message = 'No super-admin account matches that email on this control center.';
    if (!$hint) {
        $hint = 'Use the shared MotoBook sign-in page for admin, management, and rider accounts. '
              . 'The Point-of-Sale / Inventory app uses its own login at http://127.0.0.1:8000/login.';
    }
    return ['ok' => false, 'message' => $message, 'hint' => $hint];
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
    $html = '<span class="stars" aria-label="Rating: ' . number_format($rating, 1) . ' out of 5">';
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
