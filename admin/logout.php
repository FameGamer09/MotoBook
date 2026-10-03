<?php
declare(strict_types=1);

define('SSO_BASE_URL', '/IM-101/motobook');

$sessionNames = [
    'motobook_admin_session',
    'motobook_ops_session',
    'motobook_sso_session',
];

foreach ($sessionNames as $name) {
    if (session_id() !== '') { @session_write_close(); }
    session_name($name);
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie($name, '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
}

header('Location: ' . SSO_BASE_URL . '/login.php?from=signedout');
exit;
