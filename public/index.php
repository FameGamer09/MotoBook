<?php

// The legacy admin panel lives outside the public document root. Dispatch only
// its explicitly supported entry points before Laravel loads global helpers.
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$riderRoot = realpath(dirname(__DIR__).'/A-rider');

if ($requestPath === '/A-rider/api' || str_starts_with($requestPath, '/A-rider/api/')) {
    $riderApiPath = $riderRoot !== false ? realpath($riderRoot.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'index.php') : false;

    if ($riderApiPath === false || ! is_file($riderApiPath)) {
        http_response_code(404);
        exit;
    }

    $_SERVER['SCRIPT_NAME'] = '/A-rider/api/index.php';
    $_SERVER['PHP_SELF'] = '/A-rider/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $riderApiPath;

    require $riderApiPath;
    exit;
}

if (str_starts_with($requestPath, '/A-rider/public/build/')) {
    $riderBuildRelativePath = substr($requestPath, strlen('/A-rider/public/build/'));
    $riderBuildRoot = $riderRoot !== false ? realpath($riderRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build') : false;
    $riderBuildPath = $riderBuildRoot !== false
        ? realpath($riderBuildRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $riderBuildRelativePath))
        : false;
    $riderAssetTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'webmanifest' => 'application/manifest+json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $riderAssetType = $riderAssetTypes[strtolower(pathinfo($riderBuildRelativePath, PATHINFO_EXTENSION))] ?? null;

    if ($riderBuildPath === false || $riderBuildRoot === false || ! is_file($riderBuildPath) || ! str_starts_with($riderBuildPath, $riderBuildRoot.DIRECTORY_SEPARATOR) || $riderAssetType === null) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: '.$riderAssetType);
    header('X-Content-Type-Options: nosniff');
    readfile($riderBuildPath);
    exit;
}

if ($requestPath === '/A-rider' || str_starts_with($requestPath, '/A-rider/')) {
    $riderEntryPath = $riderRoot !== false ? realpath($riderRoot.DIRECTORY_SEPARATOR.'index.php') : false;

    if ($riderEntryPath === false || ! is_file($riderEntryPath)) {
        http_response_code(404);
        exit;
    }

    $_SERVER['SCRIPT_NAME'] = '/A-rider/index.php';
    $_SERVER['PHP_SELF'] = '/A-rider/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $riderEntryPath;

    require $riderEntryPath;
    exit;
}

$managementScripts = [
    '/A-management' => 'index.php',
    '/A-management/' => 'index.php',
    '/A-management/index.php' => 'index.php',
    '/A-management/dashboard.php' => 'dashboard.php',
    '/A-management/helpdesk.php' => 'helpdesk.php',
    '/A-management/menu.php' => 'menu.php',
    '/A-management/orders.php' => 'orders.php',
    '/A-management/promos.php' => 'promos.php',
    '/A-management/remittance.php' => 'remittance.php',
    '/A-management/riders.php' => 'riders.php',
    '/A-management/settings.php' => 'settings.php',
    '/A-management/stores.php' => 'stores.php',
    '/A-management/tickets.php' => 'tickets.php',
    '/A-management/banners.php' => 'banners.php',
];
$managementRoot = realpath(dirname(__DIR__).'/A-management');

if (isset($managementScripts[$requestPath]) && $managementRoot !== false) {
    $managementScriptPath = realpath($managementRoot.DIRECTORY_SEPARATOR.$managementScripts[$requestPath]);

    if ($managementScriptPath === false || ! is_file($managementScriptPath) || ! str_starts_with($managementScriptPath, $managementRoot.DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit;
    }

    $_SERVER['SCRIPT_NAME'] = $requestPath;
    $_SERVER['PHP_SELF'] = $requestPath;
    $_SERVER['SCRIPT_FILENAME'] = $managementScriptPath;

    require $managementScriptPath;
    exit;
}

if (str_starts_with($requestPath, '/A-management/assets/')) {
    $assetRelativePath = substr($requestPath, strlen('/A-management/assets/'));
    $assetRoot = $managementRoot !== false ? realpath($managementRoot.DIRECTORY_SEPARATOR.'assets') : false;
    $assetPath = $assetRoot !== false
        ? realpath($assetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $assetRelativePath))
        : false;
    $contentTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $contentType = $contentTypes[strtolower(pathinfo($assetRelativePath, PATHINFO_EXTENSION))] ?? null;

    if ($assetPath === false || $assetRoot === false || ! is_file($assetPath) || ! str_starts_with($assetPath, $assetRoot.DIRECTORY_SEPARATOR) || $contentType === null) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: '.$contentType);
    header('X-Content-Type-Options: nosniff');
    readfile($assetPath);
    exit;
}

$adminScripts = [
    '/admin' => 'index.php',
    '/admin/' => 'index.php',
    '/admin/index.php' => 'index.php',
    '/admin/login.php' => 'login.php',
    '/admin/logout.php' => 'logout.php',
    '/admin/dashboard.php' => 'dashboard.php',
    '/admin/pos.php' => 'pos.php',
    '/admin/orders.php' => 'orders.php',
    '/admin/riders.php' => 'riders.php',
    '/admin/staff.php' => 'staff.php',
    '/admin/stores.php' => 'stores.php',
    '/admin/settings.php' => 'settings.php',
    '/admin/proof_viewer.php' => 'proof_viewer.php',
    '/admin/forgot-password.php' => 'forgot-password.php',
    '/admin/register.php' => 'register.php',
    '/admin/reset-password.php' => 'reset-password.php',
    '/admin/api/analytics.php' => 'api/analytics.php',
    '/admin/api/charts.php' => 'api/charts.php',
    '/admin/api/export.php' => 'api/export.php',
    '/admin/api/export_pdf.php' => 'api/export_pdf.php',
    '/admin/api/rider_detail.php' => 'api/rider_detail.php',
    '/admin/api/store_detail.php' => 'api/store_detail.php',
    '/admin/api/v1/index.php' => 'api/v1/index.php',
];
$adminRoot = realpath(dirname(__DIR__).'/admin');

if (isset($adminScripts[$requestPath]) && $adminRoot !== false) {
    $adminScriptPath = realpath($adminRoot.DIRECTORY_SEPARATOR.$adminScripts[$requestPath]);

    if ($adminScriptPath === false || ! is_file($adminScriptPath) || ! str_starts_with($adminScriptPath, $adminRoot.DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit;
    }

    $_SERVER['SCRIPT_NAME'] = $requestPath;
    $_SERVER['PHP_SELF'] = $requestPath;
    $_SERVER['SCRIPT_FILENAME'] = $adminScriptPath;

    require $adminScriptPath;
    exit;
}

if (str_starts_with($requestPath, '/admin/assets/')) {
    $assetRelativePath = substr($requestPath, strlen('/admin/assets/'));
    $assetRoot = $adminRoot !== false ? realpath($adminRoot.DIRECTORY_SEPARATOR.'assets') : false;
    $assetPath = $assetRoot !== false
        ? realpath($assetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $assetRelativePath))
        : false;
    $contentTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $contentType = $contentTypes[strtolower(pathinfo($assetRelativePath, PATHINFO_EXTENSION))] ?? null;

    if ($assetPath === false || $assetRoot === false || ! is_file($assetPath) || ! str_starts_with($assetPath, $assetRoot.DIRECTORY_SEPARATOR) || $contentType === null) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: '.$contentType);
    header('X-Content-Type-Options: nosniff');
    readfile($assetPath);
    exit;
}

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
