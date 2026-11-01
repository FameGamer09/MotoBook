<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/admin/config/session.php';

function motobookManagementSessionSavePath(): string
{
    return motobookAdminSessionSavePath();
}
