<?php

declare(strict_types=1);

define('APP_NAME', 'Motobook Management');
define('APP_URL', '/IM-101/motobook/A-management');
define('ADMIN_URL', '/IM-101/motobook/admin');
define('CURRENCY', '₱');
define('SESSION_NAME', 'motobook_ops_session');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'motobook_admin');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('API_BASE', $scheme . '://' . $host . '/IM-101/motobook/admin/api/v1/index.php');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

date_default_timezone_set('Asia/Manila');
