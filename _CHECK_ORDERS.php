<?php
declare(strict_types=1);
$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=motobook_admin;charset=utf8mb4","root","");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("SELECT id, order_code, rider_id, order_status, payment_method, cod_amount,
  payout_amount, store_name, customer_address, gcash_reference FROM orders
  WHERE order_code IN ('MB-88491','MB-88492')");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
echo "\nTotal: ".count($rows)." orders\n";
foreach ($rows as $r) {
    echo "  {$r['order_code']} → rider_id={$r['rider_id']} status={$r['order_status']} pay={$r['payment_method']} cod=".($r['cod_amount']??'NULL')." store={$r['store_name']}\n";
}
