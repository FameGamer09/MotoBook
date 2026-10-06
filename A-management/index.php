<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['ops_user_id']) && !empty($_SESSION['ops_user'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

header('Location: ' . ADMIN_URL . '/login.php');
exit;
