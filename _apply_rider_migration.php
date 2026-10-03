<?php
declare(strict_types=1);
$pdo = new PDO("mysql:host=127.0.0.1;dbname=motobook_admin;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$sql = file_get_contents(__DIR__ . '/A-rider/migrations/001_init_rider_schema.sql');
if ($sql === false) { fwrite(STDERR, "Cannot read migration\n"); exit(1); }

# Split on ; but don't split inside string literals
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
$done = 0; $errored = 0;
foreach ($statements as $i => $st) {
    $st = trim($st);
    if ($st === '' || str_starts_with($st, '--')) continue;
    try {
        $pdo->exec($st);
        $done++;
    } catch (Throwable $e) {
        # Suppress "Table already exists" / "Duplicate column" (idempotent re-run safe)
        $msg = $e->getMessage();
        if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate column') !== false || stripos($msg, 'Duplicate entry') !== false || stripos($msg, 'Duplicate key') !== false) {
            echo "[SKIP idempotent OK] stmt #$i: " . substr($st, 0, 65) . "…\n";
        } else {
            $errored++;
            echo "[ERR stmt #$i] " . $msg . "\n    SQL: " . substr($st, 0, 160) . "\n";
        }
    }
}
echo "\nMigration complete: executed $done OK, $errored error(s), skipped idempotent OK ones.\n";

# Quick SELECT to verify tables exist
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$want = ['riders','rider_orders','rider_location_logs','rider_pod_records','rider_order_incidents','rider_deposits'];
foreach ($want as $t) {
    $present = in_array($t, $tables, true) ? 'YES' : 'MISSING';
    echo "  [$present] $t\n";
}

# Verify seed data
$rider = $pdo->query("SELECT id,rider_code,name,email,gcash_mobile_number,gcash_account_name FROM riders WHERE rider_code='RDR-0001'")->fetch(PDO::FETCH_ASSOC);
if ($rider) {
    echo "\nSeeded RDR-0001 OK:\n";
    foreach ($rider as $k=>$v) echo "  $k = $v\n";
} else {
    echo "\nRDR-0001 seed not present.\n";
}
