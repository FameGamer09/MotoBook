<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

logoutAdmin();
redirect('/login.php');
