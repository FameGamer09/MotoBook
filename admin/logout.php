<?php

declare(strict_types=1);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/logout.php');
$adminPosition = strrpos($scriptName, '/admin/');
$baseUrl = $adminPosition === false ? '' : rtrim(substr($scriptName, 0, $adminPosition), '/');
$baseUrl = $baseUrl === '' ? '' : $baseUrl;
$sessionNames = [
    'motobook_sso_session',
    'motobook_admin_session',
    'motobook_ops_session',
    'motobook_rider_session',
    'laravel_session',
];
$isSecure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

foreach ($sessionNames as $sessionName) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    session_id('');
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    $_SESSION = [];
    session_destroy();
    session_write_close();

    setcookie($sessionName, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

header('Location: '.$baseUrl.'/admin/login.php?from=signedout', true, 303);
exit;
