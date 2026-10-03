<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/smoke.php';
$_SERVER['SCRIPT_NAME'] = '/smoke.php';
$_SERVER['PHP_SELF'] = '/smoke.php';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTPS'] = null;
$_GET = [];
$_POST = [];
$_COOKIE = [];
session_start();

$pages = [
    'admin/login.php' => [],
    'A-management/login.php' => [],
];

$results = [];

foreach ($pages as $page => $_SERVER_OVERRIDES) {
    foreach ($_SERVER_OVERRIDES as $k => $v) {
        $_SERVER[$k] = $v;
    }
    $path = __DIR__ . '/' . $page;
    if (!file_exists($path)) {
        $results[$page] = 'SKIP (missing file)';
        continue;
    }
    ob_start();
    try {
        $return = require $path;
        $out = ob_get_clean();
        $hasError = (stripos($out, 'Fatal error') !== false)
            || (stripos($out, 'Parse error') !== false)
            || (stripos($out, 'Uncaught Error') !== false)
            || (stripos($out, 'Call to undefined') !== false);
        $warnings = [];
        if (preg_match_all('/(Warning|Notice|Deprecated):\s*(.+?)(?:\s+in\s+.+?)?$/mi', $out, $m)) {
            $warnings = array_unique(array_map('trim', $m[0]));
        }
        if ($hasError) {
            $snippet = substr(strip_tags($out), 0, 600);
            $results[$page] = 'ERROR: ' . $snippet;
        } else {
            $ok = 'OK (' . strlen($out) . 'B)';
            if ($warnings) {
                $ok .= ' W:' . count($warnings);
            }
            $results[$page] = $ok;
        }
    } catch (\Throwable $e) {
        $out = ob_get_clean();
        $results[$page] = 'EXCEPTION: ' . get_class($e) . ' - ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
}

// Now test protected pages with mocked session
$adminSessionPages = [
    'admin/dashboard.php' => [
        'admin_id' => 1,
        'admin_email' => 'test@motobook.com',
        'admin_name' => 'Test Admin',
    ],
];

foreach ($adminSessionPages as $page => $sessionData) {
    $_SESSION = [];
    foreach ($sessionData as $k => $v) {
        $_SESSION[$k] = $v;
    }
    $_SESSION['motobook_admin_session'] = 'mocked';
    $path = __DIR__ . '/' . $page;
    if (!file_exists($path)) {
        $results[$page] = 'SKIP (missing file)';
        continue;
    }
    ob_start();
    try {
        require $path;
        $out = ob_get_clean();
        $hasError = (stripos($out, 'Fatal error') !== false)
            || (stripos($out, 'Parse error') !== false)
            || (stripos($out, 'Uncaught Error') !== false)
            || (stripos($out, 'Call to undefined') !== false);
        $warnings = [];
        if (preg_match_all('/(Warning|Notice|Deprecated):\s*(.+?)(?:\s+in\s+.+?)?$/mi', $out, $m)) {
            $warnings = array_unique(array_map('trim', $m[0]));
        }
        if ($hasError) {
            $snippet = substr(strip_tags($out), 0, 800);
            $results[$page] = 'ERROR: ' . $snippet;
        } else {
            $ok = 'OK (' . strlen($out) . 'B)';
            if ($warnings) {
                $ok .= ' W:' . count($warnings) . ' ' . implode(' | ', array_slice($warnings, 0, 2));
            }
            $results[$page] = $ok;
        }
    } catch (\Throwable $e) {
        $out = ob_get_clean();
        $results[$page] = 'EXCEPTION: ' . get_class($e) . ' - ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
}

// Management protected pages
$mgmtSessionPages = [
    'A-management/dashboard.php' => [
        'ops_user_id' => 1,
        'ops_user_email' => 'staff@motobook.com',
        'ops_user_name' => 'Staff User',
        'ops_user_type' => 'staff',
    ],
];

foreach ($mgmtSessionPages as $page => $sessionData) {
    $_SESSION = [];
    foreach ($sessionData as $k => $v) {
        $_SESSION[$k] = $v;
    }
    $_SESSION['motobook_ops_session'] = 'mocked';
    $path = __DIR__ . '/' . $page;
    if (!file_exists($path)) {
        $results[$page] = 'SKIP (missing file)';
        continue;
    }
    ob_start();
    try {
        require $path;
        $out = ob_get_clean();
        $hasError = (stripos($out, 'Fatal error') !== false)
            || (stripos($out, 'Parse error') !== false)
            || (stripos($out, 'Uncaught Error') !== false)
            || (stripos($out, 'Call to undefined') !== false);
        $warnings = [];
        if (preg_match_all('/(Warning|Notice|Deprecated):\s*(.+?)(?:\s+in\s+.+?)?$/mi', $out, $m)) {
            $warnings = array_unique(array_map('trim', $m[0]));
        }
        if ($hasError) {
            $snippet = substr(strip_tags($out), 0, 800);
            $results[$page] = 'ERROR: ' . $snippet;
        } else {
            $ok = 'OK (' . strlen($out) . 'B)';
            if ($warnings) {
                $ok .= ' W:' . count($warnings) . ' ' . implode(' | ', array_slice($warnings, 0, 2));
            }
            $results[$page] = $ok;
        }
    } catch (\Throwable $e) {
        $out = ob_get_clean();
        $results[$page] = 'EXCEPTION: ' . get_class($e) . ' - ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
}

echo PHP_EOL . '========== SMOKE TEST RESULTS ==========' . PHP_EOL;
$failed = 0;
foreach ($results as $page => $status) {
    $ok = strpos($status, 'OK') === 0 || strpos($status, 'SKIP') === 0;
    if (!$ok) $failed++;
    printf("%-40s %s\n", $page, $status);
}
echo PHP_EOL . 'Failed: ' . $failed . ' / ' . count($results) . PHP_EOL;
exit($failed > 0 ? 1 : 0);
