<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

logoutManagement();
header('Location: ' . APP_URL . '/login.php');
exit;
