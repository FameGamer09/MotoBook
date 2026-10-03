<?php

declare(strict_types=1);

$ssoScriptName = $_SERVER['SCRIPT_NAME'] ?? '/IM-101/motobook/logout.php';
$ssoScriptDir  = str_replace('\\', '/', dirname($ssoScriptName));
$ssoScriptDir  = ($ssoScriptDir === '.' || $ssoScriptDir === '\\') ? '' : rtrim($ssoScriptDir, '/');
$ssoBase       = ($ssoScriptDir === '' || $ssoScriptDir === '/') ? '' : $ssoScriptDir;
define('SSO_BASE_URL', $ssoBase === '' ? '' : $ssoBase);

$sessionNames = [
    'motobook_sso_session',
    'motobook_admin_session',
    'motobook_ops_session',
];

$current = session_name() ?: null;
$openedAny = false;

foreach ($sessionNames as $name) {
    if (session_id() !== '') {
        session_write_close();
    }
    session_name($name);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        $openedAny = true;
    }
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

// Also try Laravel session cookie (standard 'laravel_session')
$laravelName = 'laravel_session';
if (session_id() !== '') { session_write_close(); }
session_name($laravelName);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie($laravelName, '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

if ($current && $current !== $laravelName) {
    if (session_id() !== '') { session_write_close(); }
    session_name($current);
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
}

header('Location: ' . SSO_BASE_URL . '/login.php?from=signedout');
exit;
