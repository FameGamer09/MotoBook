<?php

declare(strict_types=1);

function motobookAdminSessionSavePath(): string
{
    $configuredPath = $_ENV['MOTOBOOK_SESSION_PATH'] ?? $_SERVER['MOTOBOOK_SESSION_PATH'] ?? getenv('MOTOBOOK_SESSION_PATH');
    $sessionPath = is_string($configuredPath) && $configuredPath !== ''
        ? $configuredPath
        : dirname(__DIR__, 2).'/storage/framework/admin-sessions';

    if (! is_dir($sessionPath) && ! mkdir($sessionPath, 0770, true) && ! is_dir($sessionPath)) {
        throw new RuntimeException('Unable to create the MotoBook admin session folder.');
    }

    if (! is_writable($sessionPath)) {
        throw new RuntimeException('The MotoBook admin session folder is not writable.');
    }

    return $sessionPath;
}
