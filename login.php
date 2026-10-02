<?php

declare(strict_types=1);

$ssoScriptName = $_SERVER['SCRIPT_NAME'] ?? '/IM-101/motobook/login.php';
$ssoScriptDir = str_replace('\\', '/', dirname($ssoScriptName));
$ssoScriptDir = ($ssoScriptDir === '.' || $ssoScriptDir === '\\') ? '' : rtrim($ssoScriptDir, '/');
$ssoBase = ($ssoScriptDir === '' || $ssoScriptDir === '/') ? '' : $ssoScriptDir;

define('SSO_APP_NAME', 'Motobook');
define('SSO_BASE_URL', $ssoBase === '' ? '' : $ssoBase);
define('SSO_ADMIN_URL', SSO_BASE_URL.'/admin');
define('SSO_OPS_URL', SSO_BASE_URL.'/A-management');
define('SSO_POS_URL', 'http://127.0.0.1:8000/login');
define('SSO_RIDER_URL', SSO_BASE_URL.'/A-rider');

define('SSO_DB_HOST', '127.0.0.1');
define('SSO_DB_PORT', '3306');
define('SSO_DB_NAME', 'motobook_admin');
define('SSO_DB_USER', 'root');
define('SSO_DB_PASS', '');
define('SSO_DB_CHARSET', 'utf8mb4');

define('SSO_SESSION_NAME', 'motobook_sso_session');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SSO_SESSION_NAME);
    session_start();
}

date_default_timezone_set('Asia/Manila');

function sso_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            SSO_DB_HOST,
            SSO_DB_PORT,
            SSO_DB_NAME,
            SSO_DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, SSO_DB_USER, SSO_DB_PASS, $options);
    }

    return $pdo;
}

function sso_pos_hash_verify(string $email, string $password): bool
{
    $candidates = [
        __DIR__.'/database/database.sqlite',
        __DIR__.'/../olivaian/database/database.sqlite',
        __DIR__.'/../olivaian/../database/database.sqlite',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            try {
                $pdo = new PDO('sqlite:'.$path);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $row = $stmt->fetch();
                if ($row && ! empty($row['password']) && password_verify($password, $row['password'])) {
                    return true;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    return false;
}

function sso_begin_panel_session(string $sessionName): string
{
    $currentName = session_name() ?: SSO_SESSION_NAME;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    session_id('');
    session_name($sessionName);
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    session_regenerate_id(true);
    $_SESSION = [];

    return $currentName;
}

function sso_end_panel_session(string $currentName): void
{
    session_write_close();
    session_id('');
    session_name($currentName);
    session_start();
}

function sso_authenticate(string $email, string $password): array
{
    $email = trim($email);
    $password = (string) $password;
    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter both email and password.'];
    }

    $pdo = sso_pdo();

    // 1) Super Admin
    $stmt = $pdo->prepare('SELECT id, name, email, password FROM super_admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin) {
        if (! password_verify($password, $admin['password'])) {
            return ['ok' => false, 'message' => 'Incorrect password for super-admin account. Passwords are case-sensitive.'];
        }

        return [
            'ok' => true,
            'panel' => 'admin',
            'url' => SSO_ADMIN_URL.'/dashboard.php',
            'role' => 'super_admin',
            'label' => 'Super Admin Control Center',
            'data' => [
                'id' => (int) $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
            ],
        ];
    }

    // 2) Inactive staff
    $stmtInactive = $pdo->prepare('SELECT id, is_active FROM staff WHERE email = ? LIMIT 1');
    $stmtInactive->execute([$email]);
    $inactiveStaff = $stmtInactive->fetch();
    if ($inactiveStaff && empty($inactiveStaff['is_active'])) {
        return ['ok' => false, 'message' => 'Your staff account has been deactivated. Contact the platform super admin to be re-enabled.'];
    }

    // 3) Staff (platform / store)
    $stmt = $pdo->prepare(
        'SELECT s.id, s.full_name, s.email, s.password, s.staff_type, s.role, s.store_id, ps.store_name
         FROM staff s
         LEFT JOIN partnership_stores ps ON ps.id = s.store_id
         WHERE s.email = ? AND s.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $staff = $stmt->fetch();
    if ($staff) {
        if (! password_verify($password, $staff['password'])) {
            return ['ok' => false, 'message' => 'Incorrect password for staff account. Passwords are case-sensitive.'];
        }
        $staffType = ($staff['staff_type'] ?? 'platform') === 'store' ? 'store' : 'platform';
        if ($staffType === 'store' && ! $staff['store_id']) {
            return ['ok' => false, 'message' => 'Store staff account found but no store has been assigned. Contact the platform super admin.'];
        }
        $landing = $staffType === 'store'
            ? SSO_OPS_URL.'/menu.php'
            : SSO_OPS_URL.'/orders.php?tab=kanban';

        return [
            'ok' => true,
            'panel' => 'ops',
            'url' => $landing,
            'role' => $staffType === 'store' ? 'store_staff' : 'platform_staff',
            'label' => $staffType === 'store'
                ? 'Store Manager Workspace'.($staff['store_name'] ? " — {$staff['store_name']}" : '')
                : 'Motobook Management (Dispatch)',
            'data' => [
                'id' => (int) $staff['id'],
                'name' => $staff['full_name'],
                'email' => $staff['email'],
                'staff_type' => $staffType,
                'store_id' => $staff['store_id'] ? (int) $staff['store_id'] : null,
                'store_name' => $staff['store_name'] ?? null,
                'role' => $staff['role'] ?? null,
            ],
        ];
    }

    // 4) Store Owner
    $stmt = $pdo->prepare('SELECT * FROM partnership_stores WHERE owner_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $store = $stmt->fetch();
    if ($store) {
        if (empty($store['owner_password']) || ! password_verify($password, $store['owner_password'])) {
            return ['ok' => false, 'message' => 'Incorrect password for store-owner account. Passwords are case-sensitive.'];
        }

        return [
            'ok' => true,
            'panel' => 'ops',
            'url' => SSO_OPS_URL.'/menu.php',
            'role' => 'store_owner',
            'label' => "Store Manager Workspace — {$store['store_name']}",
            'data' => [
                'id' => (int) $store['id'],
                'name' => $store['owner_name'] ?: ($store['store_name'].' Owner'),
                'email' => $store['owner_email'],
                'store_id' => (int) $store['id'],
                'store_name' => $store['store_name'],
            ],
        ];
    }

    // 5) Rider pool (moto delivery operators)
    $columnsStmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $columnsStmt->execute(['riders']);
    $riderColumns = array_fill_keys(
        array_map('strtolower', array_column($columnsStmt->fetchAll(), 'COLUMN_NAME')),
        true
    );
    $nameColumns = array_values(array_filter(['name', 'full_name'], static fn (string $column): bool => isset($riderColumns[$column])));
    $passwordColumns = array_values(array_filter(['password_hash', 'password'], static fn (string $column): bool => isset($riderColumns[$column])));
    $nameExpression = $nameColumns
        ? 'COALESCE('.implode(', ', array_map(static fn (string $column): string => "NULLIF(`{$column}`, '')", $nameColumns)).')'
        : "''";
    $passwordExpression = $passwordColumns
        ? 'COALESCE('.implode(', ', array_map(static fn (string $column): string => "NULLIF(`{$column}`, '')", $passwordColumns)).')'
        : "''";
    $stmt = $pdo->prepare(
        "SELECT id,
                {$nameExpression} AS name,
                email,
                rider_code,
                {$passwordExpression} AS password_hash,
                CASE UPPER(status)
                    WHEN 'ACTIVE'   THEN 'ACTIVE'
                    WHEN 'ON_DUTY'  THEN 'ON_SHIFT'
                    WHEN 'ON_SHIFT' THEN 'ON_SHIFT'
                    WHEN 'SUSPENDED' THEN 'SUSPENDED'
                    WHEN 'INACTIVE' THEN 'INACTIVE'
                    ELSE 'OFFLINE'
                END AS status
         FROM riders
         WHERE email = ?
            LIMIT 1"
    );
    $stmt->execute([$email]);
    $rider = $stmt->fetch();
    if ($rider) {
        $pwHash = (string) ($rider['password_hash'] ?? '');
        $passwordMatched = false;
        if ($pwHash !== '') {
            // Support both: bcrypt hashes (new) + plaintext legacy MD5/SHA1 fallback + any PHP password_hash algo
            if (password_verify($password, $pwHash)) {
                $passwordMatched = true;
            } elseif (ctype_alnum($pwHash) && strlen($pwHash) === 32 && strcasecmp(md5($password), $pwHash) === 0) {
                $passwordMatched = true; // legacy md5
            } elseif (ctype_alnum($pwHash) && strlen($pwHash) === 40 && strcasecmp(sha1($password), $pwHash) === 0) {
                $passwordMatched = true; // legacy sha1
            } elseif (hash_equals($pwHash, $password)) {
                $passwordMatched = true; // last-resort plaintext (never stored this way, but safe fallback)
            }
        }
        if (! $passwordMatched) {
            return ['ok' => false, 'message' => 'Incorrect password for rider account. Passwords are case-sensitive.'];
        }
        if (in_array($rider['status'] ?? 'OFFLINE', ['INACTIVE', 'SUSPENDED'], true)) {
            return ['ok' => false, 'message' => 'This rider account has been deactivated. Contact the platform super admin to be re-enabled.'];
        }

        return [
            'ok' => true,
            'panel' => 'rider',
            'url' => SSO_RIDER_URL.'/',
            'role' => 'rider',
            'label' => 'MotoBook Rider Workspace',
            'data' => [
                'id' => (int) $rider['id'],
                'rider_code' => (string) $rider['rider_code'],
                'name' => (string) $rider['name'],
                'email' => (string) $rider['email'],
                'status' => (string) $rider['status'],
            ],
        ];
    }

    // 6) Laravel POS & Inventory (SQLite, served on 127.0.0.1:8000)
    if (sso_pos_hash_verify($email, $password)) {
        return [
            'ok' => true,
            'panel' => 'pos',
            'url' => SSO_POS_URL,
            'role' => 'pos',
            'label' => 'Motobook POS & Inventory Dashboard',
            'data' => ['email' => $email],
        ];
    }

    return [
        'ok' => false,
        'message' => 'No account was found with that email and password across any Motobook panel.',
    ];
}

function sso_seed_admin_session(array $data): void
{
    $current = sso_begin_panel_session('motobook_admin_session');
    $_SESSION['admin_id'] = $data['id'];
    $_SESSION['admin_name'] = $data['name'];
    $_SESSION['admin_email'] = $data['email'];
    sso_end_panel_session($current);
}

function sso_seed_ops_session(array $auth): void
{
    $current = sso_begin_panel_session('motobook_ops_session');

    if (($auth['role'] ?? null) === 'store_owner') {
        $_SESSION['ops_user_id'] = (int) $auth['data']['store_id'];
        $_SESSION['ops_user'] = [
            'id' => (int) $auth['data']['store_id'],
            'name' => $auth['data']['name'],
            'email' => $auth['data']['email'],
            'type' => 'store_owner',
            'role' => 'store_manager',
            'store_id' => (int) $auth['data']['store_id'],
            'store_name' => $auth['data']['store_name'],
            'staff_type' => 'store_owner',
        ];
    } else {
        $staffType = $auth['data']['staff_type'] ?? 'platform';
        $type = $staffType === 'store' ? 'store_staff' : 'platform_staff';
        $_SESSION['ops_user_id'] = (int) $auth['data']['id'];
        $_SESSION['ops_user'] = [
            'id' => (int) $auth['data']['id'],
            'name' => $auth['data']['name'],
            'email' => $auth['data']['email'],
            'type' => $type,
            'role' => $auth['data']['role'] ?? ($staffType === 'store' ? 'store_operator' : 'platform_operator'),
            'store_id' => ! empty($auth['data']['store_id']) ? (int) $auth['data']['store_id'] : null,
            'store_name' => $auth['data']['store_name'] ?? null,
            'staff_type' => $staffType,
        ];
    }
    sso_end_panel_session($current);
}

function sso_seed_rider_session(array $auth): void
{
    $current = sso_begin_panel_session('motobook_rider_session');
    $_SESSION['rider_id'] = (int) $auth['data']['id'];
    $_SESSION['rider'] = [
        'id' => (int) $auth['data']['id'],
        'rider_code' => (string) ($auth['data']['rider_code'] ?? ''),
        'name' => (string) ($auth['data']['name'] ?? ''),
        'email' => (string) ($auth['data']['email'] ?? ''),
        'status' => (string) ($auth['data']['status'] ?? 'OFFLINE'),
    ];
    sso_end_panel_session($current);
}

$error = '';
$panel = $_GET['from'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        $result = sso_authenticate($email, $password);
    } catch (Throwable $exception) {
        error_log('Motobook sign-in unavailable: '.$exception->getMessage());
        $result = ['ok' => false, 'message' => 'Sign-in is temporarily unavailable. Please try again later.'];
    }

    if (($result['ok'] ?? false) === true) {
        if ($result['panel'] === 'admin') {
            sso_seed_admin_session($result['data']);
        } elseif ($result['panel'] === 'ops') {
            sso_seed_ops_session($result);
        } elseif ($result['panel'] === 'rider') {
            sso_seed_rider_session($result);
        }

        $_SESSION['sso_last_panel'] = $result['panel'];
        $_SESSION['sso_last_role'] = $result['role'];
        $_SESSION['sso_last_email'] = $email;

        $redirect = isset($_GET['redirect']) && is_string($_GET['redirect']) && $_GET['redirect'] !== ''
            ? $_GET['redirect']
            : $result['url'];
        header('Location: '.$redirect);
        exit;
    }

    $error = $result['message'];
    $_POST['email'] = $email;
}

$brandLogoUrl = SSO_BASE_URL.'/public/images/825310998_1610647237377200_4391978218746131377_n.png';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#00BFFF">
    <title>Log In &mdash; <?= SSO_APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) {
                window.lucide.createIcons({
                    attrs: { 'stroke-width': 1.75, class: 'inline-block shrink-0' },
                });
            }
            var p = document.getElementById('password');
            var t = document.getElementById('toggle-password');
            if (p && t) {
                t.addEventListener('click', function () {
                    var isText = p.getAttribute('type') === 'text';
                    p.setAttribute('type', isText ? 'password' : 'text');
                    var svg = t.querySelector('svg, i');
                    if (svg && svg.setAttribute) {
                        svg.setAttribute('data-lucide', isText ? 'eye-off' : 'eye');
                    }
                    if (window.lucide && window.lucide.createIcons) {
                        window.lucide.createIcons({ attrs: { 'stroke-width': 1.75, class: 'inline-block shrink-0' } });
                    }
                });
            }
        });
    </script>
    <style>
        :root{
            --mb-blue-50:#eff9ff;
            --mb-blue-100:#dff3ff;
            --mb-blue-200:#b9e6ff;
            --mb-blue-300:#7fd2ff;
            --mb-blue-400:#39b9ff;
            --mb-blue-500:#00BFFF;
            --mb-blue-600:#009ce0;
            --mb-blue-700:#007cbf;
            --mb-blue-800:#06618e;
            --mb-blue-900:#0d4f75;
            --mb-slate-50:#f8fafc;
            --mb-slate-100:#f1f5f9;
            --mb-slate-200:#e2e8f0;
            --mb-slate-300:#cbd5e1;
            --mb-slate-400:#94a3b8;
            --mb-slate-500:#64748b;
            --mb-slate-600:#475569;
            --mb-slate-700:#334155;
            --mb-slate-800:#1e293b;
            --mb-slate-900:#0f172a;
            --mb-red-500:#ef4444;
            --mb-red-50:#fef2f2;
            --mb-white:#ffffff;
            --mb-black:#0b1220;
            --mb-shadow-sm:0 1px 2px rgba(15,23,42,.06),0 1px 3px rgba(15,23,42,.08);
            --mb-shadow-md:0 4px 12px rgba(15,23,42,.08),0 2px 4px rgba(15,23,42,.06);
            --mb-shadow-lg:0 20px 45px -15px rgba(0,191,255,.35),0 10px 25px -10px rgba(15,23,42,.12);
            --mb-radius-xs:8px;
            --mb-radius-sm:10px;
            --mb-radius-md:14px;
            --mb-radius-lg:18px;
            --mb-radius-xl:24px;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;height:100%;min-height:100%;overflow:auto}
        body{
            font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
            color:var(--mb-slate-900);
            background:
                radial-gradient(1200px 600px at 90% -10%, rgba(0,191,255,.14), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(0,191,255,.12), transparent 60%),
                linear-gradient(180deg,#ffffff 0%,#f4fbff 55%,#eaf7ff 100%);
            -webkit-font-smoothing:antialiased;
            text-rendering:optimizeLegibility;
            padding:max(12px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        }
        @supports(height: 100dvh){
            html,body{height:100dvh;min-height:100dvh}
            .page{min-height:calc(100dvh - 24px)}
        }
        .page{
            width:100%;
            min-height:calc(100vh - 24px);
            margin:0 auto;
            display:flex;
            justify-content:center;
            align-items:center;
        }
        .hero{display:none}
        .login-brand{display:flex;flex-direction:column;align-items:center;gap:6px;margin:0 0 18px}
        .brand-ico{
            width:64px;height:64px;border-radius:14px;
            object-fit:contain;
            background:rgba(255,255,255,.8);
            border:1px solid rgba(0,191,255,.18);
            box-shadow:0 10px 20px -10px rgba(0,191,255,.45);
            display:block;
            animation:float 5.4s ease-in-out infinite;
        }
        .brand-name{font-weight:800;font-size:18px;color:var(--mb-blue-900)}
        @keyframes float{
            0%,100%{transform:translateY(0) rotate(-1deg)}
            50%{transform:translateY(-8px) rotate(1deg)}
        }

        .card{
            width:100%;max-width:440px;
            background:#fff;
            border-radius:var(--mb-radius-xl);
            padding:24px 26px 18px;
            box-shadow:var(--mb-shadow-lg);
            border:1px solid rgba(203,213,225,.5);
        }
        .card h2{
            margin:0;font-size:32px;line-height:1.05;font-weight:800;letter-spacing:-.02em;
            color:var(--mb-slate-900);text-align:center;
        }
        .card .welcome{
            margin:8px 0 0;text-align:center;
            color:var(--mb-slate-600);font-size:14px;line-height:1.5;
        }
        .card .welcome span{display:block}

        .alert{
            border-radius:var(--mb-radius-md);
            padding:10px 12px;display:flex;gap:10px;align-items:flex-start;
            margin:0 0 12px;font-size:13px;line-height:1.5;
        }
        .alert svg{width:18px;height:18px;margin-top:1px;flex:0 0 18px}
        .alert-error{background:var(--mb-red-50);color:#b91c1c;border:1px solid rgba(239,68,68,.22)}
        .alert-ok{background:#efffed;color:#15803d;border:1px solid rgba(34,197,94,.25)}

        .field{margin-bottom:11px}
        .field label{
            display:block;font-size:13px;font-weight:700;color:var(--mb-slate-800);
            margin:0 0 6px;
        }
        .input-group{
            position:relative;display:flex;align-items:center;
            background:#fff;
            border:1.5px solid var(--mb-slate-300);
            border-radius:14px;
            box-shadow:var(--mb-shadow-sm);
            transition:border-color .15s ease,box-shadow .15s ease, transform .05s ease;
        }
        .input-group:hover{border-color:#9fd8f4}
        .input-group:focus-within{
            border-color:var(--mb-blue-500);
            box-shadow:0 0 0 4px rgba(0,191,255,.16), 0 4px 14px -4px rgba(0,191,255,.25);
        }
        .input-ico{
            flex:0 0 42px;height:44px;display:inline-flex;align-items:center;justify-content:center;
            color:var(--mb-slate-500);
        }
        .input-ico svg{width:18px;height:18px}
        .input{
            width:100%;height:44px;border:none;background:transparent;outline:none;
            font:inherit;color:var(--mb-slate-900);font-size:14.5px;
            padding:0 10px 0 0;
        }
        .input::placeholder{color:var(--mb-slate-400);font-weight:500}
        .input-action{
            flex:0 0 42px;height:44px;display:inline-flex;align-items:center;justify-content:center;
            background:transparent;border:none;cursor:pointer;color:var(--mb-slate-500);
            border-top-right-radius:14px;
            border-bottom-right-radius:14px;
            transition:background .15s ease, color .15s ease;
        }
        .input-action:hover{background:var(--mb-blue-50);color:var(--mb-blue-700)}
        .input-action svg{width:18px;height:18px}

        .forgot-row{
            display:flex;align-items:center;justify-content:flex-end;
            margin:-2px 0 14px;
        }
        .forgot-row a{
            text-decoration:none;color:var(--mb-blue-600);font-size:12.5px;font-weight:700;
            transition:color .15s ease;
        }
        .forgot-row a:hover{color:var(--mb-blue-800)}

        .btn-primary{
            width:100%;border:none;cursor:pointer;
            padding:12px 14px;border-radius:14px;
            color:#fff;font-weight:700;font-size:15px;letter-spacing:.2px;
            background:linear-gradient(180deg,var(--mb-blue-500),var(--mb-blue-600));
            box-shadow:0 12px 24px -10px rgba(0,191,255,.55), 0 2px 4px rgba(0,191,255,.25);
            transition:transform .08s ease, filter .15s ease, box-shadow .2s ease;
            display:inline-flex;align-items:center;justify-content:center;gap:8px;
        }
        .btn-primary:hover{filter:brightness(1.04);box-shadow:0 16px 30px -10px rgba(0,191,255,.55)}
        .btn-primary:active{transform:translateY(1px)}

        .divider{
            display:flex;align-items:center;gap:12px;margin:16px 0 12px;color:var(--mb-slate-500);
            font-size:12.5px;font-weight:600;
        }
        .divider::before,.divider::after{
            content:"";flex:1;height:1px;background:var(--mb-slate-200);
        }
        .socials{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}
        .social{
            background:#fff;border:1.5px solid var(--mb-slate-200);
            border-radius:14px;height:44px;cursor:pointer;
            display:inline-flex;align-items:center;justify-content:center;
            transition:transform .08s ease, border-color .15s ease, background .15s ease;
            color:var(--mb-slate-700);
        }
        .social:hover{transform:translateY(-1px);border-color:var(--mb-blue-500);background:var(--mb-blue-50)}
        .social svg{width:20px;height:20px}
        .social.google svg{color:#4285F4}
        .social.fb svg{color:#1877F2}
        .social.apple svg{color:#0b1220}

        .signup{
            margin:0;text-align:center;font-size:13px;color:var(--mb-slate-700);
        }
        .signup a{
            color:var(--mb-blue-600);font-weight:800;text-decoration:none;
            transition:color .15s ease;
        }
        .signup a:hover{color:var(--mb-blue-800)}
        .foot{
            margin-top:8px;text-align:center;font-size:11.5px;color:var(--mb-slate-500);line-height:1.5
        }
        .foot a{color:var(--mb-blue-700);font-weight:700;text-decoration:none}

        /* PHONE */
        @media (max-width: 640px){
            body{padding-top:max(10px,env(safe-area-inset-top));padding-right:max(12px,env(safe-area-inset-right));padding-bottom:max(10px,env(safe-area-inset-bottom));padding-left:max(12px,env(safe-area-inset-left))}
            .page{min-height:calc(100dvh - 20px)}
            .card{
                border-radius:var(--mb-radius-lg);
                padding:22px 18px 16px;
                box-shadow:0 24px 50px -18px rgba(15,23,42,.18);
            }
            .brand-ico{width:56px;height:56px}
            .card h2{font-size:28px}
            .card .welcome{font-size:13.5px}
            .input, .input-ico, .input-action{height:44px}
            .input-group{border-radius:14px}
            .btn-primary{padding:12px 14px;font-size:14.5px;border-radius:14px}
        }
        /* Very small laptops < 720px viewport height: force compact */
        @media (max-height: 719px){
            .card{padding:18px 20px 14px}
            .card h2{font-size:26px}
            .card .welcome{font-size:13px;margin:6px 0 0}
            .field{margin-bottom:9px}
            .forgot-row{margin:-1px 0 10px}
            .divider{margin:12px 0 10px}
            .socials{margin-bottom:8px}
            .foot{margin-top:6px}
        }
    </style>
</head>
<body>
<main class="page">
    <section class="hero" aria-label="Motobook brand preview">
        <div>
            <div class="topbar">
                <a class="back" href="#" aria-label="Back" onclick="history.length>1?history.back():window.location.assign('<?= SSO_BASE_URL ?>/login.php');return false;">
                    <i data-lucide="chevron-left"></i>
                </a>
                <div class="brand" aria-label="<?= SSO_APP_NAME ?> brand">
                    <img class="brand-ico" src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= SSO_APP_NAME ?> logo" aria-label="<?= SSO_APP_NAME ?> brand logo">
                    <span class="brand-name"><?= SSO_APP_NAME ?></span>
                </div>
            </div>
            <div class="hero-copy">
                <h1>Log In</h1>
                <p>Welcome back! Please <strong>sign in</strong> to your <?= SSO_APP_NAME ?> account.
                We auto-detect your role and take you straight to the correct workspace.</p>
            </div>
        </div>

        <div class="scooter-wrap" aria-hidden="true">
              <img class="scooter" alt="Motobook logo"
                  src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>"
                 loading="eager" decoding="async"
                 onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22><defs><linearGradient id=%22g%22 x1=%220%22 y1=%220%22 x2=%221%22 y2=%221%22><stop offset=%220%25%22 stop-color=%22%237FD2FF%22/><stop offset=%22100%25%22 stop-color=%22%2300BFFF%22/></linearGradient></defs><circle cx=%22120%22 cy=%22220%22 r=%2242%22 fill=%22%231f2937%22/><circle cx=%22280%22 cy=%22220%22 r=%2242%22 fill=%22%231f2937%22/><circle cx=%22120%22 cy=%22220%22 r=%2222%22 fill=%22%2394a3b8%22/><circle cx=%22280%22 cy=%22220%22 r=%2222%22 fill=%22%2394a3b8%22/><path d=%22M120 220 C140 160, 220 140, 280 180 L280 220 L120 220 Z%22 fill=%22url(#g)%22 stroke=%22%2306618e%22 stroke-width=%224%22/><rect x=%22180%22 y=%22125%22 width=%2290%22 height=%2270%22 rx=%2210%22 fill=%22%2300BFFF%22 stroke=%22%2306618e%22 stroke-width=%224%22/><text x=%22225%22 y=%22165%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-weight=%22800%22 font-size=%2218%22 fill=%22white%22>MOTOBOOK</text><circle cx=%22215%22 cy=%22100%22 r=%2228%22 fill=%22%2300BFFF%22 stroke=%22%2306618e%22 stroke-width=%223%22/><circle cx=%22215%22 cy=%22100%22 r=%2218%22 fill=%22%23FFE4C4%22/></svg>';this.classList.add('scooter-fallback');">
        </div>

        <div class="features" aria-label="What you can do after signing in">
            <div class="feat">
                <span class="feat-ic" aria-hidden="true"><i data-lucide="shield-check"></i></span>
                <div>
                    <h4>Super Admin</h4>
                    <p>Manage riders, staff, partner stores &amp; global rules.</p>
                </div>
            </div>
            <div class="feat">
                <span class="feat-ic" aria-hidden="true"><i data-lucide="truck"></i></span>
                <div>
                    <h4>Management Staff</h4>
                    <p>Live dispatch, remittances, tickets, promo approvals.</p>
                </div>
            </div>
            <div class="feat">
                <span class="feat-ic" aria-hidden="true"><i data-lucide="store"></i></span>
                <div>
                    <h4>Store Manager</h4>
                    <p>Edit menus, availability and promos locked to your store.</p>
                </div>
            </div>
            <div class="feat">
                <span class="feat-ic" aria-hidden="true"><i data-lucide="scan-line"></i></span>
                <div>
                    <h4>POS &amp; Inventory</h4>
                    <p>Cash register, stock, and transaction history.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="card" aria-label="Sign in form">
        <div class="login-brand" aria-label="<?= SSO_APP_NAME ?> brand">
            <img class="brand-ico" src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= SSO_APP_NAME ?> logo">
            <span class="brand-name"><?= SSO_APP_NAME ?></span>
        </div>
        <h2>Log In</h2>
        <p class="welcome">
            <span>Welcome back! Please login</span>
            <span>to your account.</span>
        </p>

        <?php if ($error !== '') { ?>
            <div class="alert alert-error" role="alert">
                <i data-lucide="alert-circle"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php } ?>
        <?php if ($panel === 'signedout') { ?>
            <div class="alert alert-ok" role="status">
                <i data-lucide="check-circle-2"></i>
                <span>You have been signed out of all <?= SSO_APP_NAME ?> panels.</span>
            </div>
        <?php } ?>

        <form method="POST" action="" novalidate>
            <div class="field">
                <label for="email">Email or Phone Number</label>
                <div class="input-group">
                    <span class="input-ico" aria-hidden="true"><i data-lucide="user-2"></i></span>
                    <input class="input" id="email" name="email" type="text" inputmode="email" autocomplete="username" required
                           placeholder="Enter your Email or Phone Number"
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-group">
                    <span class="input-ico" aria-hidden="true"><i data-lucide="lock"></i></span>
                    <input class="input" id="password" name="password" type="password" autocomplete="current-password" required
                           placeholder="Enter your password">
                    <button class="input-action" id="toggle-password" type="button"
                            aria-label="Show or hide password" aria-pressed="false">
                        <i data-lucide="eye-off"></i>
                    </button>
                </div>
            </div>

            <div class="forgot-row">
                <a href="#" onclick="return false;" aria-disabled="true">Forget Password?</a>
            </div>

            <button type="submit" class="btn-primary" aria-label="Log in to <?= SSO_APP_NAME ?>">
                Log In
            </button>
        </form>

        <div class="divider" role="separator" aria-label="Other sign-in options">or continue with</div>

        <div class="socials" aria-label="Third-party sign in (coming soon)">
            <button type="button" class="social google" aria-label="Continue with Google"
                    onclick="alert('Google sign-in will be enabled in a future release. Use your email and password.');">
                <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 33 29.4 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 5.9 1.1 8.1 2.9l5.7-5.7C34.2 5.1 29.4 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21c10.5 0 20-8 20.9-18.3.1-.6.1-1.2.1-1.8v-.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 18.9 13 24 13c3.1 0 5.9 1.1 8.1 2.9l5.7-5.7C34.2 5.1 29.4 3 24 3 16.3 3 9.7 7.1 6.3 14.7z"/><path fill="#4CAF50" d="M24 45c5.3 0 10.1-2 13.8-5.3l-6.4-5.3c-2 1.5-4.5 2.3-7.4 2.3-5.4 0-9.9-3.6-11.5-8.5l-6.5 5C9.5 40 16.1 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.4l6.4 5.3C40.4 36 45 30.7 45 24c0-1.3-.1-2.5-.4-3.5z"/></svg>
            </button>
            <button type="button" class="social fb" aria-label="Continue with Facebook"
                    onclick="alert('Facebook sign-in will be enabled in a future release. Use your email and password.');">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" fill="currentColor">
                    <path d="M13.5 22v-8.5h2.8l.4-3.2h-3.2V7.2c0-.9.3-1.6 1.7-1.6H17V2.7c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.4v2.5H8v3.2h2.3V22h3.2z"/>
                </svg>
            </button>
            <button type="button" class="social apple" aria-label="Continue with Apple"
                    onclick="alert('Apple sign-in will be enabled in a future release. Use your email and password.');">
                <i data-lucide="apple"></i>
            </button>
        </div>

        <p class="signup">
            Don't have an account? <a href="#" onclick="alert('Self-sign up is disabled. Contact the platform Super Admin (admin@motobook.com) to request an account.');return false;">Sign Up</a>
        </p>
        <p class="foot">
            One unified sign-in for every <?= SSO_APP_NAME ?> panel. If you get stuck, contact the Super Admin
            or return to <a href="<?= SSO_BASE_URL ?>/login.php">this login page</a>.
        </p>
    </section>
</main>
</body>
</html>
