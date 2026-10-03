<?php
declare(strict_types=1);
define('SSO_BASE_URL', '/IM-101/motobook');

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . SSO_BASE_URL . '/admin/dashboard.php');
    exit;
}

header('Location: ' . SSO_BASE_URL . '/login.php?from=admin');
exit;
