<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=motobook_admin;charset=utf8mb4","root","",[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "DB_OK\n";
    echo "Tables: " . implode(", ", array_map(fn($r) => $r[0], $pdo->query("SHOW TABLES")->fetchAll()));
} catch (Throwable $e) {
    echo "DB_ERR: " . $e->getMessage() . "\n";
}
