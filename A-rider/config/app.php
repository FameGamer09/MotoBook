<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    if (PHP_VERSION_ID >= 70300) {
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
    } else {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        session_set_cookie_params(86400, '/; samesite=Lax', '', $isHttps, true);
    }
    session_name('motobook_rider_session');
    session_start();
}

define('APP_NAME', 'MotoBook Rider');
define('APP_ROOT', realpath(__DIR__.'/..'));
define('APP_BUILD_TAG', '20260930-01');

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/IM-101/motobook/A-rider/index.php';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$scriptDir = ($scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');

if (basename($scriptDir) === 'A-rider') {
    $projectBase = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');
} else {
    $projectBase = $scriptDir;
}
$projectBase = ($projectBase === '.' || $projectBase === '') ? '' : $projectBase;

define('APP_URL_BASE', ($projectBase === '' ? '' : $projectBase).'/A-rider');
define('PROJECT_URL_BASE', $projectBase === '' ? '/' : $projectBase.'/');

define('UNIFIED_LOGIN_URL', (function () use ($projectBase): string {
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return sprintf('%s://%s%s/admin/login.php', $proto, $host, $projectBase === '' ? '' : $projectBase);
})());
define('UNIFIED_LOGOUT_URL', (function () use ($projectBase): string {
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return sprintf('%s://%s%s/admin/logout.php?pool=rider', $proto, $host, $projectBase === '' ? '' : $projectBase);
})());

date_default_timezone_set('Asia/Manila');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
