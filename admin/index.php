<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('/dashboard.php');
}

redirect('/login.php');
