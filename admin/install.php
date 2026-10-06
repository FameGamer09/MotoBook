<?php

declare(strict_types=1);

/**
 * One-time database installer for Motobook Super Admin.
 * Run once: http://localhost/IM-101/motobook/admin/install.php
 * Delete or restrict access after setup.
 */

require_once __DIR__ . '/config/database.php';

$messages = [];
$errors = [];

try {
    $mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';
    $schemaFile = str_replace('\\', '/', __DIR__ . '/database/schema.sql');
    $seedFile = str_replace('\\', '/', __DIR__ . '/database/seed.sql');

    if (is_file($mysqlBin)) {
        $auth = DB_PASS !== '' ? '-p' . DB_PASS : '';
        $cmdSchema = sprintf('"%s" -u %s %s -e "SOURCE %s"', $mysqlBin, DB_USER, $auth, $schemaFile);
        $cmdSeed = sprintf('"%s" -u %s %s motobook_admin -e "SOURCE %s"', $mysqlBin, DB_USER, $auth, $seedFile);

        exec($cmdSchema, $outSchema, $codeSchema);
        exec($cmdSeed, $outSeed, $codeSeed);

        $opsFile = str_replace('\\', '/', __DIR__ . '/database/operations.sql');
        $cmdOps = sprintf('"%s" -u %s %s motobook_admin -e "SOURCE %s"', $mysqlBin, DB_USER, $auth, $opsFile);
        exec($cmdOps, $outOps, $codeOps);

        require_once __DIR__ . '/includes/migrate_ops.php';
        $pdoMigrate = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=motobook_admin;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        runOperationsMigration($pdoMigrate);

        if ($codeSchema !== 0 || $codeSeed !== 0) {
            $errors[] = 'MySQL import failed. Run schema.sql and seed.sql manually in phpMyAdmin.';
        }
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS motobook_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE motobook_admin');

        foreach ([__DIR__ . '/database/schema.sql', __DIR__ . '/database/seed.sql', __DIR__ . '/database/operations.sql'] as $file) {
            $sql = preg_replace('/^USE motobook_admin;\s*/m', '', file_get_contents($file));
            $sql = preg_replace('/^CREATE DATABASE.*?;\s*/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), '1061') === false) {
                            throw $e;
                        }
                    }
                }
            }
        }

        require_once __DIR__ . '/includes/migrate_ops.php';
        runOperationsMigration($pdo);
    }

    if (empty($errors)) {
        $messages[] = 'Database motobook_admin created and seeded successfully.';
        $messages[] = 'Login: admin@motobook.com / password';
        $messages[] = 'Shared login: /IM-101/motobook/admin/login.php';
        $messages[] = 'API: /IM-101/motobook/admin/api/v1/health';
    }
} catch (PDOException $e) {
    $errors[] = 'Connection failed: ' . $e->getMessage();
    $errors[] = 'Ensure XAMPP MySQL is running and credentials in config/database.php are correct.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — Motobook Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon">M</div>
            <h1>Database Installer</h1>
            <p>Motobook Super Admin Setup</p>
        </div>

        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
            <a href="login.php" class="btn btn-primary" style="width:100%;text-align:center;display:block;">Go to Login</a>
        <?php else: ?>
            <p class="text-muted text-center">Fix errors above and refresh this page.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
