<?php
declare(strict_types=1);
define('SSO_BASE_URL', '/IM-101/motobook');

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['ops_user_id'])) {
    $u = $_SESSION['ops_user'] ?? null;
    $type = $u['type'] ?? '';
    switch ($type) {
        case 'platform_staff':
            header('Location: ' . SSO_BASE_URL . '/A-management/orders.php?tab=kanban');
            exit;
        case 'store_staff':
        case 'store_owner':
            header('Location: ' . SSO_BASE_URL . '/A-management/menu.php');
            exit;
        case 'super_admin':
        default:
            header('Location: ' . SSO_BASE_URL . '/A-management/dashboard.php');
            exit;
    }
}

header('Location: ' . SSO_BASE_URL . '/login.php?from=management');
exit;
