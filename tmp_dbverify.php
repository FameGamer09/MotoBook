<?php
declare(strict_types=1);
require_once __DIR__ . '/A-management/includes/functions.php';
$pdo = getOpsDB();
$row = $pdo->query('SELECT id, store_id, item_name, category, price, description, is_available, groups_json FROM store_menu_items WHERE item_name LIKE "%2-pc Chickenjoy%" ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;
if ($row) {
    $gStmt = $pdo->prepare('SELECT g.group_name, g.selection_type, g.min_select, g.max_select, g.is_required, COUNT(o.id) oCount FROM store_menu_item_option_groups g LEFT JOIN store_menu_item_options o ON o.group_id=g.id WHERE g.item_id = ? GROUP BY g.id ORDER BY g.id');
    $gStmt->execute([$row['id']]);
    foreach ($gStmt->fetchAll(PDO::FETCH_ASSOC) as $g) { echo " GROUP: " . json_encode($g) . PHP_EOL; }
    $oStmt = $pdo->prepare('SELECT g.group_name, o.option_name, o.price_delta, o.is_available FROM store_menu_item_option_groups g JOIN store_menu_item_options o ON o.group_id=g.id WHERE g.item_id = ? ORDER BY g.id, o.id');
    $oStmt->execute([$row['id']]);
    foreach ($oStmt->fetchAll(PDO::FETCH_ASSOC) as $o) { echo "  OPTION: " . json_encode($o) . PHP_EOL; }
}
