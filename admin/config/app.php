<?php

declare(strict_types=1);

define('APP_NAME', 'Motobook Super Admin');
define('APP_URL', '/IM-101/motobook/admin');
define('CURRENCY', '₱');
define('SESSION_NAME', 'motobook_admin_session');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

date_default_timezone_set('Asia/Manila');
