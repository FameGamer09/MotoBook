<?php

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$publicRoot = realpath(__DIR__.'/public');
$publicFile = realpath(__DIR__.'/public'.str_replace('/', DIRECTORY_SEPARATOR, $uri));

if ($publicFile && $publicRoot && str_starts_with($publicFile, $publicRoot.DIRECTORY_SEPARATOR) && is_file($publicFile)) {
    return false;
}

$riderRoot = realpath(__DIR__.'/A-rider');

if ($uri === '/A-rider/api' || str_starts_with($uri, '/A-rider/api/')) {
    $riderApiPath = $riderRoot !== false ? realpath($riderRoot.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'index.php') : false;

    if ($riderApiPath === false || ! is_file($riderApiPath)) {
        http_response_code(404);

        return true;
    }

    $_SERVER['SCRIPT_NAME'] = '/A-rider/api/index.php';
    $_SERVER['PHP_SELF'] = '/A-rider/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $riderApiPath;

    require $riderApiPath;

    return true;
}

if (str_starts_with($uri, '/A-rider/public/build/')) {
    $riderBuildRelativePath = substr($uri, strlen('/A-rider/public/build/'));
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

        return true;
    }

    header('Content-Type: '.$riderAssetType);
    header('X-Content-Type-Options: nosniff');
    readfile($riderBuildPath);

    return true;
}

if ($uri === '/A-rider' || str_starts_with($uri, '/A-rider/')) {
    $riderEntryPath = $riderRoot !== false ? realpath($riderRoot.DIRECTORY_SEPARATOR.'index.php') : false;

    if ($riderEntryPath === false || ! is_file($riderEntryPath)) {
        http_response_code(404);

        return true;
    }

    $_SERVER['SCRIPT_NAME'] = '/A-rider/index.php';
    $_SERVER['PHP_SELF'] = '/A-rider/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $riderEntryPath;

    require $riderEntryPath;

    return true;
}

$legacyScripts = [
    '/A-management/' => '/A-management/index.php',
    '/A-management' => '/A-management/index.php',
    '/A-management/index.php',
    '/A-management/dashboard.php',
    '/A-management/helpdesk.php',
    '/A-management/menu.php',
    '/A-management/orders.php',
    '/A-management/promos.php',
    '/A-management/remittance.php',
    '/A-management/riders.php',
    '/A-management/settings.php',
    '/A-management/stores.php',
    '/A-management/tickets.php',
    '/A-management/banners.php',
    '/admin/' => '/admin/index.php',
    '/admin' => '/admin/index.php',
    '/admin/index.php',
    '/admin/login.php',
    '/admin/logout.php',
    '/admin/dashboard.php',
    '/admin/pos.php',
    '/admin/orders.php',
    '/admin/riders.php',
    '/admin/staff.php',
    '/admin/stores.php',
    '/admin/settings.php',
    '/admin/proof_viewer.php',
    '/admin/forgot-password.php',
    '/admin/register.php',
    '/admin/reset-password.php',
    '/admin/api/analytics.php',
    '/admin/api/charts.php',
    '/admin/api/export.php',
    '/admin/api/export_pdf.php',
    '/admin/api/rider_detail.php',
    '/admin/api/store_detail.php',
    '/admin/api/v1/index.php',
];

$legacyScript = $legacyScripts[$uri] ?? (in_array($uri, $legacyScripts, true) ? $uri : null);

if ($legacyScript !== null) {
    $scriptPath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, $legacyScript);

    if (is_file($scriptPath)) {
        $_SERVER['SCRIPT_NAME'] = $legacyScript;
        $_SERVER['PHP_SELF'] = $legacyScript;
        $_SERVER['SCRIPT_FILENAME'] = $scriptPath;

        require $scriptPath;

        return true;
    }
}

if (str_starts_with($uri, '/A-management/assets/')) {
    $assetRelativePath = substr($uri, strlen('/A-management/assets/'));
    $assetRoot = realpath(__DIR__.'/A-management/assets');
    $assetPath = $assetRoot
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

    if ($assetPath && $assetRoot && str_starts_with($assetPath, $assetRoot.DIRECTORY_SEPARATOR) && is_file($assetPath) && $contentType !== null) {
        header('Content-Type: '.$contentType);
        header('X-Content-Type-Options: nosniff');
        readfile($assetPath);

        return true;
    }

    http_response_code(404);

    return true;
}

if (str_starts_with($uri, '/admin/assets/')) {
    $assetRelativePath = substr($uri, strlen('/admin/assets/'));
    $assetRoot = realpath(__DIR__.'/admin/assets');
    $assetPath = realpath(__DIR__.'/admin/assets/'.str_replace('/', DIRECTORY_SEPARATOR, $assetRelativePath));
    $extension = strtolower(pathinfo($assetRelativePath, PATHINFO_EXTENSION));
    $contentType = match ($extension) {
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        default => null,
    };

    if ($assetPath && $assetRoot && str_starts_with($assetPath, $assetRoot.DIRECTORY_SEPARATOR) && is_file($assetPath) && $contentType !== null) {
        header('Content-Type: '.$contentType);
        header('X-Content-Type-Options: nosniff');
        readfile($assetPath);

        return true;
    }

    http_response_code(404);

    return true;
}

$formattedDateTime = date('D M j H:i:s Y');
$remoteAddress = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1').':'.($_SERVER['REMOTE_PORT'] ?? '0');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require __DIR__.'/public/index.php';
