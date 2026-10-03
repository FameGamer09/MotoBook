<?php
declare(strict_types=1);
$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=motobook_admin;charset=utf8mb4","root","");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['orders','rider_offers','rider_order_logs','rider_payouts','rider_shifts','rider_deposits','riders'] as $t) {
    echo "\n=== $t COLUMNS ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM `$t`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo "  {$c['Field']}\t{$c['Type']}\t{$c['Null']}\t{$c['Key']}\n";
    }
}
echo "\n=== ORDERS rows (sample 5) ===\n";
$stmt = $pdo->query("SELECT * FROM orders LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) { echo "  (empty)\n"; }
else {
    foreach ($rows as $i => $r) {
        echo "ROW[$i]: ";
        $s=[];
        foreach ($r as $k=>$v) {
            if ($v === null) $s[]="$k=NULL";
            elseif (is_string($v) && strlen($v)>50) $s[]="$k=<".strlen($v)."b>";
            else $s[]="$k=$v";
        }
        echo implode(' | ',$s)."\n";
    }
}
