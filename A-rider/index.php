<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';

function _rider_shell_numericize(mixed $v): mixed {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $val) { $out[$k] = _rider_shell_numericize($val); }
        return $out;
    }
    if (is_string($v) && $v !== '' && preg_match('/^-?\d+(\.\d+)?$/', $v) === 1) {
        if (strpos($v, '.') !== false) { return (float)$v; }
        if (strlen($v) >= 10 && $v[0] !== '-') { return (float)$v; }
        $i = (int)$v; if ((string)$i === $v) return $i;
        return (float)$v;
    }
    return $v;
}

$isApiCall = (bool)preg_match('#^/api/#i', ($_SERVER['PATH_INFO'] ?? '') ?: ($_SERVER['REQUEST_URI'] ?? ''));
if ($isApiCall) {
    $_SERVER['PATH_INFO'] = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $route = preg_replace('#^.*/A-rider#', '', $_SERVER['PATH_INFO'] ?? '');
    $route = '/' . ltrim($route, '/');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    require __DIR__ . '/api/index.php';
    exit;
}

$hasSession = !empty($_SESSION['rider_id']);
if (!$hasSession) {
    $redirect = UNIFIED_LOGIN_URL . '?pool=rider&redirect=' . rawurlencode(
        (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/')
    );
    header('Location: ' . $redirect, true, 302);
    exit;
}

try {
    $rider = riderRequireAuth();
    $rider = _rider_shell_numericize($rider);
} catch (Throwable $_) {
    header('Location: ' . UNIFIED_LOGIN_URL, true, 302);
    exit;
}

$manifest = [];
$manifestPath = __DIR__ . '/public/build/.vite/manifest.json';
if (is_file($manifestPath)) {
    $parsed = json_decode((string)file_get_contents($manifestPath), true);
    if (is_array($parsed)) $manifest = $parsed;
}
$jsEntryRel  = $manifest['index.html']['file'] ?? null;
$cssEntryRel = $manifest['index.html']['css'][0] ?? null;
$jsEntryRel  = $jsEntryRel  ? ltrim($jsEntryRel,  '/') : 'assets/main.js';
$cssEntryRel = $cssEntryRel ? ltrim($cssEntryRel, '/') : 'assets/main.css';
$buildBase    = rtrim(APP_URL_BASE, '/') . '/public/build/';
$jsUrl      = $buildBase . $jsEntryRel;
$cssUrl     = $buildBase . $cssEntryRel;
$buildExists = is_file(__DIR__ . '/public/build/' . $jsEntryRel);
if (!$buildExists) {
    $jsUrl  = 'http://localhost:5174/src/main.tsx';
    $cssUrl = '';
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
?><!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no, interactive-widget=resizes-content" />
    <meta name="theme-color" content="#0F172A" />
    <meta name="color-scheme" content="dark only" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="MotoBook Rider" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="application-name" content="MotoBook Rider" />
    <meta name="format-detection" content="telephone=no" />
    <title>MotoBook Rider</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="manifest" href="<?= htmlspecialchars($buildBase . 'manifest.webmanifest?v=' . APP_BUILD_TAG, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($buildBase . 'assets/rider-icon-192.svg?v=' . APP_BUILD_TAG, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($buildBase . 'assets/rider-icon-192.svg?v=' . APP_BUILD_TAG, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" />
    <?php if ($buildExists && $cssUrl): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <script>
      window.__RIDER_BOOT__ = {
        riderId: <?= (int)$rider['id'] ?>,
        riderName: <?= json_encode($rider['name'], JSON_UNESCAPED_UNICODE) ?>,
        riderEmail: <?= json_encode($rider['email'], JSON_UNESCAPED_UNICODE) ?>,
        riderStatus: <?= json_encode($rider['status'], JSON_UNESCAPED_UNICODE) ?>,
        riderCode: <?= json_encode($rider['rider_code'], JSON_UNESCAPED_UNICODE) ?>,
        vehiclePlate: <?= json_encode($rider['vehicle_plate'], JSON_UNESCAPED_UNICODE) ?>,
        unifiedLogoutUrl: <?= json_encode(UNIFIED_LOGOUT_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        apiBase: <?= json_encode(rtrim(APP_URL_BASE, '/') . '/api', JSON_UNESCAPED_SLASHES) ?>,
        buildHash: <?= json_encode(hash('xxh3', (string)($rider['id'] . '|' . ($rider['updated_at'] ?? ''))), JSON_UNESCAPED_UNICODE) ?>,
        routerBasename: <?= json_encode(rtrim(APP_URL_BASE, '/'), JSON_UNESCAPED_SLASHES) ?>,
      };
    </script>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <div id="rider-root"></div>
    <?php if (!$buildExists): ?>
    <script type="module">
      import RefreshRuntime from 'http://localhost:5174/@react-refresh';
      RefreshRuntime.injectIntoGlobalHook(window);
      window.$RefreshReg$ = () => {};
      window.$RefreshSig$ = () => (type) => type;
      window.__vite_plugin_react_preamble_installed__ = true;
    </script>
    <script type="module" src="<?= htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
    <script type="module" src="<?= htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('<?= htmlspecialchars($buildBase . 'service-worker.js?v=' . APP_BUILD_TAG, ENT_QUOTES, 'UTF-8') ?>', {
            scope: '<?= htmlspecialchars($buildBase, ENT_QUOTES, 'UTF-8') ?>'
          }).catch(function (e) { console.warn('[SW] register failed', e); });
        });
      }
    </script>
</body>
</html>
