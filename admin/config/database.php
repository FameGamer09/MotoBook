<?php

declare(strict_types=1);

$databaseEnv = static function (string $key, ?string $fallback = null): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $fallback ?? '';
    }

    return (string) $value;
};

define('DB_HOST', $databaseEnv('MANAGEMENT_DB_HOST', $databaseEnv('DB_HOST', '127.0.0.1')));
define('DB_PORT', $databaseEnv('MANAGEMENT_DB_PORT', $databaseEnv('DB_PORT', '3306')));
define('DB_NAME', $databaseEnv('MANAGEMENT_DB_DATABASE', 'motobook_admin'));
define('DB_USER', $databaseEnv('MANAGEMENT_DB_USERNAME', $databaseEnv('DB_USERNAME', 'root')));
define('DB_PASS', $databaseEnv('MANAGEMENT_DB_PASSWORD', $databaseEnv('DB_PASSWORD', '')));
define('DB_CHARSET', 'utf8mb4');

function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
