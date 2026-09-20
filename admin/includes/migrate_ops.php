<?php

declare(strict_types=1);

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function runOperationsMigration(PDO $pdo): void
{
    $opsSql = file_get_contents(dirname(__DIR__) . '/database/operations.sql');
    $opsSql = preg_replace('/^USE motobook_admin;\s*/m', '', $opsSql) ?? $opsSql;
    $opsSql = preg_replace('/--.*$/m', '', $opsSql) ?? $opsSql;

    foreach (array_filter(array_map('trim', explode(';', $opsSql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }

    if (!columnExists($pdo, 'staff', 'staff_type')) {
        $pdo->exec("ALTER TABLE staff ADD COLUMN staff_type ENUM('platform','store') NOT NULL DEFAULT 'platform' AFTER role");
    }

    if (!columnExists($pdo, 'orders', 'customer_phone')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name');
    }

    if (!columnExists($pdo, 'orders', 'eta_minutes')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN eta_minutes INT UNSIGNED NULL AFTER order_status');
    }

    if (!columnExists($pdo, 'orders', 'assigned_at')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN assigned_at DATETIME NULL AFTER eta_minutes');
    }

    if (!columnExists($pdo, 'orders', 'photo_path')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN photo_path VARCHAR(255) NULL AFTER assigned_at');
    }

    if (!columnExists($pdo, 'rider_daily_collections', 'collected_by_staff_id')) {
        $pdo->exec('ALTER TABLE rider_daily_collections ADD COLUMN collected_by_staff_id INT UNSIGNED NULL AFTER remitted_by');
    }

    if (!columnExists($pdo, 'rider_daily_collections', 'collected_at')) {
        $pdo->exec('ALTER TABLE rider_daily_collections ADD COLUMN collected_at DATETIME NULL AFTER collected_by_staff_id');
    }

    $pdo->exec("
        ALTER TABLE orders MODIFY order_status ENUM(
            'pending','placed','preparing','driver_assigned','out_for_delivery','delayed','picked_up','delivered','cancelled'
        ) DEFAULT 'placed'
    ");

    $pdo->exec("
        ALTER TABLE rider_daily_collections MODIFY remittance_status ENUM(
            'pending','collected','remitted','partial'
        ) DEFAULT 'pending'
    ");

    $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    $staffRows = [
        ['STF-OPS', 'Lea Operations', 'ops@motobook.com', '09301110001', $hash, null, 'order_approver', 'platform', 'on_shift', 1],
        ['STF-SUP', 'Marco Support', 'support@motobook.com', '09301110002', $hash, null, 'support_representative', 'platform', 'on_shift', 1],
        ['STF-JBE', 'Jessa Jollibee Manager', 'jollibee.manager@motobook.com', '09301110003', $hash, 1, 'store_operator', 'store', 'on_shift', 1],
        ['STF-MCD', 'Paolo McDo Manager', 'mcdo.manager@motobook.com', '09301110004', $hash, 2, 'store_operator', 'store', 'on_shift', 1],
    ];

    $findStaff = $pdo->prepare('SELECT id FROM staff WHERE email = ?');
    $insertStaff = $pdo->prepare('
        INSERT INTO staff (staff_code, full_name, email, phone, password, store_id, role, staff_type, shift_status, is_active, last_active_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $updateStaff = $pdo->prepare('
        UPDATE staff SET full_name = ?, phone = ?, password = ?, store_id = ?, role = ?, staff_type = ?, shift_status = ?, is_active = 1, last_active_at = NOW()
        WHERE id = ?
    ');

    foreach ($staffRows as $row) {
        [$code, $name, $email, $phone, $pass, $storeId, $role, $type, $shift, $active] = $row;
        $findStaff->execute([$email]);
        $existingId = $findStaff->fetchColumn();
        if ($existingId) {
            $updateStaff->execute([$name, $phone, $pass, $storeId, $role, $type, $shift, $existingId]);
        } else {
            $insertStaff->execute([$code, $name, $email, $phone, $pass, $storeId, $role, $type, $shift, $active]);
        }
    }

    $pdo->exec("UPDATE staff SET staff_type = 'store' WHERE store_id IS NOT NULL AND (staff_type IS NULL OR staff_type = 'platform') AND role = 'store_operator'");
    $pdo->exec("UPDATE staff SET staff_type = 'platform' WHERE store_id IS NULL AND email IN ('alice@motobook.com','charlie@motobook.com')");

    $liveOrders = [
        ['ORD-LIVE-001', 1, 1, 'Kim Santos', '09180001111', 385.00, 49.00, 46.20, 'cash', 'paid', 'placed', 40, null],
        ['ORD-LIVE-002', 1, 2, 'Rico Alvarez', '09180002222', 520.00, 49.00, 62.40, 'gcash', 'paid', 'preparing', 35, null],
        ['ORD-LIVE-003', 2, 1, 'Mia Cruz', '09180003333', 290.00, 49.00, 29.00, 'cash', 'paid', 'driver_assigned', 30, date('Y-m-d H:i:s')],
        ['ORD-LIVE-004', 5, 3, 'Ben Lim', '09180004444', 410.00, 49.00, 45.10, 'maya', 'paid', 'out_for_delivery', 25, date('Y-m-d H:i:s', strtotime('-20 minutes'))],
        ['ORD-LIVE-005', 3, 2, 'Ava Reyes', '09180005555', 675.00, 49.00, 54.00, 'cash', 'paid', 'delayed', 35, date('Y-m-d H:i:s', strtotime('-50 minutes'))],
    ];

    $orderStmt = $pdo->prepare('
        INSERT IGNORE INTO orders (order_number, store_id, rider_id, customer_name, customer_phone, order_total, delivery_fee, commission_amount, payment_method, payment_status, order_status, eta_minutes, assigned_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 18 MINUTE))
    ');
    foreach ($liveOrders as $order) {
        $orderStmt->execute($order);
    }

    $pdo->exec("UPDATE orders SET created_at = DATE_SUB(NOW(), INTERVAL 55 MINUTE) WHERE order_number = 'ORD-LIVE-005'");

    $shiftStmt = $pdo->prepare('
        INSERT IGNORE INTO rider_shifts (rider_id, shift_date, clocked_in_at, shift_status)
        VALUES (?, CURDATE(), DATE_SUB(NOW(), INTERVAL 4 HOUR), ?)
    ');
    $shiftStmt->execute([1, 'active']);
    $shiftStmt->execute([2, 'active']);
    $shiftStmt->execute([3, 'on_break']);

    $pdo->prepare('INSERT IGNORE INTO rider_incidents (rider_id, incident_type, notes, logged_by_staff_id) VALUES (1, ?, ?, 1)')
        ->execute(['safety_briefing', 'Completed morning safety briefing and bag check.']);

    $ticketStmt = $pdo->prepare('
        INSERT IGNORE INTO complaint_tickets (ticket_number, order_id, store_id, rider_id, customer_name, customer_phone, category, description, status)
        VALUES (?, (SELECT id FROM orders WHERE order_number = ? LIMIT 1), ?, ?, ?, ?, ?, ?, ?)
    ');
    $ticketStmt->execute(['TKT-001', 'ORD-LIVE-005', 3, 2, 'Ava Reyes', '09180005555', 'cold_food', 'Food arrived cold after a long wait at the pharmacy pickup.', 'open']);
    $ticketStmt->execute(['TKT-002', 'ORD-20260814-002', 2, 1, 'Jane Doe', '09180006666', 'missing_item', 'Missing fries from the McDonald\'s order.', 'in_review']);
    $ticketStmt->execute(['TKT-003', 'ORD-20260813-001', 2, 3, 'Lisa Park', '09180007777', 'rider_behavior', 'Rider was rude during handover.', 'open']);

    $pdo->exec("
        INSERT IGNORE INTO ticket_evidence (ticket_id, evidence_type, content)
        SELECT id, 'note', 'Customer uploaded a photo of sealed bag; item missing inside.' FROM complaint_tickets WHERE ticket_number = 'TKT-002'
    ");

    $chatCount = (int) $pdo->query('SELECT COUNT(*) FROM order_chats')->fetchColumn();
    if ($chatCount === 0) {
        $chatStmt = $pdo->prepare('
            INSERT INTO order_chats (order_id, sender_role, sender_name, message)
            SELECT id, ?, ?, ? FROM orders WHERE order_number = ? LIMIT 1
        ');
        $chatStmt->execute(['customer', 'Ava Reyes', 'Hi, still waiting. Is the rider stuck?', 'ORD-LIVE-005']);
        $chatStmt->execute(['rider', 'Rider Two Garcia', 'Traffic on Taft. Five more minutes.', 'ORD-LIVE-005']);
        $chatStmt->execute(['staff', 'Lea Operations', 'We are monitoring this order. Sorry for the delay.', 'ORD-LIVE-005']);
    }

    $gpsCount = (int) $pdo->query('SELECT COUNT(*) FROM gps_tracks')->fetchColumn();
    if ($gpsCount === 0) {
        $gpsStmt = $pdo->prepare('
            INSERT INTO gps_tracks (order_id, rider_id, latitude, longitude, recorded_at)
            SELECT id, 2, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE) FROM orders WHERE order_number = ? LIMIT 1
        ');
        $gpsStmt->execute([14.537800, 120.989600, 12, 'ORD-LIVE-005']);
        $gpsStmt->execute([14.545000, 120.992000, 6, 'ORD-LIVE-005']);
        $gpsStmt->execute([14.552000, 120.998000, 1, 'ORD-LIVE-004']);
    }

    $menuStmt = $pdo->prepare('INSERT IGNORE INTO store_menu_items (store_id, item_name, category, price, is_available) VALUES (?, ?, ?, ?, ?)');
    $menuStmt->execute([1, 'Chickenjoy Bucket', 'Fast Food', 499.00, 1]);
    $menuStmt->execute([1, 'Jolly Spaghetti', 'Fast Food', 89.00, 1]);
    $menuStmt->execute([1, 'Yumburger', 'Fast Food', 66.00, 1]);
    $menuStmt->execute([2, 'McChicken', 'Fast Food', 95.00, 1]);
    $menuStmt->execute([2, 'Fries Large', 'Sides', 89.00, 0]);

    $helpStmt = $pdo->prepare('
        INSERT IGNORE INTO merchant_help_tickets (ticket_number, store_id, subject, category, message, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $helpStmt->execute(['MHT-001', 1, 'Cannot toggle Chickenjoy availability', 'menu_help', 'The item switch is not saving on our tablet.', 'open']);
    $helpStmt->execute(['MHT-002', 2, 'App glitch on incoming orders', 'app_glitch', 'Orders keep ringing even after we accept them.', 'in_progress']);

    $bannerStmt = $pdo->prepare('
        INSERT IGNORE INTO promo_banners (title, caption, hex_color, store_id, is_active)
        VALUES (?, ?, ?, ?, 1)
    ');
    $bannerStmt->execute(['Jollibee 20% Off Chickenjoy', 'Limited-time partner campaign', '#E11D2F', 1]);
    $bannerStmt->execute(['Motobook Free Delivery Weekends', 'Platform promo for selected stores', '#0891B2', null]);

    $promoStmt = $pdo->prepare('
        INSERT IGNORE INTO store_promos (store_id, title, description, discount_percent, status, submitted_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $promoStmt->execute([1, 'Chickenjoy Tuesday', '20% off buckets every Tuesday', 20.00, 'pending', 'Jessa Jollibee Manager']);
    $promoStmt->execute([2, 'McCafé Morning', '15% off drinks before 10 AM', 15.00, 'pending', 'Paolo McDo Manager']);

    $pdo->prepare('
        INSERT IGNORE INTO rider_daily_collections (rider_id, collection_date, orders_completed, total_collected, commission_earned, remittance_status)
        VALUES (1, CURDATE(), 12, 1000.00, 120.00, ?)
    ')->execute(['pending']);
}
