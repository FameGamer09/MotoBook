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
        $_SESSION['ops_user_id'] = 1;
        $_SESSION['ops_user_email'] = 'staff@test.com';
        $_SESSION['ops_user_name'] = 'Staff';
        $_SESSION['ops_user_type'] = 'staff';
    }
    $path = __DIR__ . '/A-management/' . $page;
    if (!file_exists($path)) { echo "MGMT $page: SKIP\n"; continue; }
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
    if ($err) { $failed++; echo "MGMT $page: ERR ".substr(strip_tags($o),0,400)."\n"; }
    else { echo "MGMT $page: OK (".strlen($o)."B, W:$w)\n"; }
}
echo "MGMT FAILED: $failed\n";
exit($failed > 0 ? 1 : 0);
