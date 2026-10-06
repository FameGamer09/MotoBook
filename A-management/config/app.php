<?php

declare(strict_types=1);

define('APP_NAME', 'Motobook Management');
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/A-management/index.php');
$managementMarker = '/A-management/';
$managementPosition = strpos($scriptName, $managementMarker);
$appUrl = $managementPosition === false
    ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/').'/A-management'
    : substr($scriptName, 0, $managementPosition + strlen('/A-management'));
$baseUrl = rtrim(substr($appUrl, 0, -strlen('/A-management')), '/');
define('APP_URL', $appUrl);
define('ADMIN_URL', $baseUrl.'/admin');
define('CURRENCY', '₱');
define('SESSION_NAME', 'motobook_ops_session');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'motobook_admin');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('API_BASE', $scheme.'://'.$host.$baseUrl.'/admin/api/v1/index.php');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    if (PHP_VERSION_ID >= 70300) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isSecure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (stripos($host, 'localhost') === false && ! filter_var($host, FILTER_VALIDATE_IP));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', false, true);
    }
    session_start();
}

date_default_timezone_set('Asia/Manila');
