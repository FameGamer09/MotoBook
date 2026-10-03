<?php
declare(strict_types=1);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/test.php';
$_SERVER['SCRIPT_NAME'] = '/test.php';
$_SERVER['REQUEST_SCHEME'] = 'http';
session_start();
$_SESSION = [];

$pages = [
    'login.php' => false,
    'dashboard.php' => true,
];
$failed = 0;
foreach ($pages as $page => $needSession) {
    if ($needSession) {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_email'] = 'admin@test.com';
        $_SESSION['admin_name'] = 'Test Admin';
    }
    $path = __DIR__ . '/admin/' . $page;
    if (!file_exists($path)) { echo "ADMIN $page: SKIP\n"; continue; }
    ob_start();
    try {
        require $path;
        $o = ob_get_clean();
    } catch (\Throwable $e) {
        $o = ob_get_clean();
        $o .= 'EX: ' . $e->getMessage() . '@' . $e->getLine();
    }
    $err = (stripos($o, 'Fatal error')!==false) || (stripos($o,'Parse error')!==false) || (stripos($o,'Uncaught')!==false) || (stripos($o,'EX:')!==false);
    $w = preg_match_all('/(Warning|Notice|Deprecated):\s*(.+?)(?:\s+in\s+.+?)?$/mi', $o, $mm) ? count(array_unique($mm[0])) : 0;
    if ($err) { $failed++; echo "ADMIN $page: ERR ".substr(strip_tags($o),0,400)."\n"; }
    else { echo "ADMIN $page: OK (".strlen($o)."B, W:$w)\n"; }
}
echo "ADMIN FAILED: $failed\n";
exit($failed > 0 ? 1 : 0);
