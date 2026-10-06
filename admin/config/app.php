<?php

declare(strict_types=1);

define('APP_NAME', 'Motobook Super Admin');
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
$adminPathPosition = strpos($scriptName, '/admin/');
$adminUrl = $adminPathPosition === false
    ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/')
    : substr($scriptName, 0, $adminPathPosition + strlen('/admin'));
define('APP_URL', $adminUrl !== '' ? $adminUrl : '/admin');
define('CURRENCY', '₱');
define('SESSION_NAME', 'motobook_admin_session');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

date_default_timezone_set('Asia/Manila');
