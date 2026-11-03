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
require_once __DIR__.'/session.php';

$databaseEnv = static function (string $key, ?string $fallback = null): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $fallback ?? '';
    }

    return (string) $value;
};

define('DB_HOST', $databaseEnv('MANAGEMENT_DB_HOST', $databaseEnv('DB_HOST', '127.0.0.1')));
define('DB_PORT', $databaseEnv('MANAGEMENT_DB_PORT', $databaseEnv('DB_PORT', '3306')));
define('DB_NAME', $databaseEnv('MANAGEMENT_DB_DATABASE', 'motobook_admin'));
define('DB_USER', $databaseEnv('MANAGEMENT_DB_USERNAME', $databaseEnv('DB_USERNAME', 'root')));
define('DB_PASS', $databaseEnv('MANAGEMENT_DB_PASSWORD', $databaseEnv('DB_PASSWORD', '')));
define('DB_CHARSET', 'utf8mb4');

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$scheme = $isHttps ? 'https' : 'http';
define('API_BASE', $scheme.'://'.$host.$baseUrl.'/admin/api/v1/index.php');

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(motobookManagementSessionSavePath());
    session_name(SESSION_NAME);
    if (PHP_VERSION_ID >= 70300) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isSecure = $isHttps
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
