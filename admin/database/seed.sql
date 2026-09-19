USE motobook_admin;

-- Default Super Admin (password: password)
INSERT INTO super_admins (name, email, password) VALUES
('Super Admin', 'admin@motobook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO store_categories (name) VALUES
('Fast Food'), ('Grocery'), ('Pharmacy'), ('Restaurant'), ('Convenience Store');

INSERT INTO partnership_stores (store_name, category_id, branch_address, latitude, longitude, contact_phone, contact_email, operating_hours, commission_rate, status, owner_name, owner_email, total_orders) VALUES
('Jollibee', 1, '123 Rizal Ave, Manila', 14.599512, 120.984222, '09171234567', 'jollibee.manila@email.com', '06:00 - 23:00', 12.00, 'open', 'Juan Dela Cruz', 'owner.jollibee@email.com', 245),
('McDonald''s', 1, '456 EDSA, Quezon City', 14.676041, 121.043700, '09181234567', 'mcdo.qc@email.com', '07:00 - 22:00', 10.00, 'open', 'Maria Santos', 'owner.mcdo@email.com', 189),
('Mercury Drug', 3, '789 Taft Ave, Pasay', 14.537752, 120.989589, '09191234567', 'mercury.pasay@email.com', '08:00 - 21:00', 8.00, 'open', 'Pedro Reyes', 'owner.mercury@email.com', 98),
('Local Grocery Hub', 2, '321 Commonwealth Ave, QC', 14.676041, 121.043700, '09201234567', 'grocery.qc@email.com', '07:00 - 20:00', 15.00, 'paused', 'Ana Lopez', 'owner.grocery@email.com', 56),
('Greenwich Pizza', 1, '555 Ayala Ave, Makati', 14.554729, 121.024445, '09211234567', 'greenwich.makati@email.com', '10:00 - 22:00', 11.00, 'open', 'Carlos Mendoza', 'owner.greenwich@email.com', 134);

INSERT INTO riders (rider_code, full_name, email, phone, password, vehicle_type, vehicle_plate, license_number, status, duty_status, total_deliveries, avg_rating) VALUES
('RDR-001', 'Rider One Santos', 'rider1@motobook.com', '09171111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Motorcycle', 'ABC 1234', 'N01-123456', 'on_duty', 'on_trip', 320, 4.85),
('RDR-002', 'Rider Two Garcia', 'rider2@motobook.com', '09172222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Motorcycle', 'DEF 5678', 'N02-234567', 'on_duty', 'online', 210, 4.72),
('RDR-003', 'Rider Three Reyes', 'rider3@motobook.com', '09173333333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bicycle', 'N/A', 'N03-345678', 'active', 'idle', 145, 4.90),
('RDR-004', 'Rider Four Cruz', 'rider4@motobook.com', '09174444444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Motorcycle', 'GHI 9012', 'N04-456789', 'suspended', 'offline', 89, 3.20),
('RDR-005', 'Rider Five Tan', 'rider5@motobook.com', '09175555555', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Motorcycle', 'JKL 3456', 'N05-567890', 'inactive', 'offline', 52, 4.50);

INSERT INTO staff (staff_code, full_name, email, phone, password, store_id, role, shift_status, is_active, last_active_at) VALUES
('STF-001', 'Alice Admin', 'alice@motobook.com', '09281111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'order_approver', 'on_shift', 1, NOW()),
('STF-002', 'Bob Inventory', 'bob@motobook.com', '09282222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'inventory_manager', 'on_shift', 1, NOW()),
('STF-003', 'Charlie Support', 'charlie@motobook.com', '09283333333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'support_representative', 'break', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('STF-004', 'Diana Operator', 'diana@motobook.com', '09284444444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'store_operator', 'on_shift', 1, NOW()),
('STF-005', 'Eddie Operator', 'eddie@motobook.com', '09285555555', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'store_operator', 'off_shift', 0, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Sample orders (today and past week)
INSERT INTO orders (order_number, store_id, rider_id, customer_name, order_total, delivery_fee, commission_amount, payment_method, payment_status, order_status, created_at) VALUES
('ORD-20260814-001', 1, 1, 'John Customer', 450.00, 50.00, 54.00, 'cash', 'paid', 'delivered', CURDATE()),
('ORD-20260814-002', 2, 1, 'Jane Doe', 320.00, 45.00, 32.00, 'cash', 'paid', 'delivered', CURDATE()),
('ORD-20260814-003', 1, 2, 'Mark Lee', 580.00, 55.00, 69.60, 'gcash', 'paid', 'delivered', CURDATE()),
('ORD-20260814-004', 3, 2, 'Sarah Kim', 890.00, 60.00, 71.20, 'maya', 'paid', 'delivered', CURDATE()),
('ORD-20260814-005', 5, 1, 'Tom Wilson', 275.00, 40.00, 30.25, 'cash', 'paid', 'delivered', CURDATE()),
('ORD-20260813-001', 2, 3, 'Lisa Park', 410.00, 50.00, 41.00, 'card', 'paid', 'delivered', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
('ORD-20260812-001', 1, 2, 'Mike Chen', 620.00, 55.00, 74.40, 'cash', 'paid', 'delivered', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
('ORD-20260811-001', 5, 3, 'Nina Rose', 350.00, 45.00, 38.50, 'gcash', 'paid', 'delivered', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
('ORD-20260810-001', 3, 1, 'Oscar Tan', 720.00, 60.00, 57.60, 'cash', 'paid', 'delivered', DATE_SUB(CURDATE(), INTERVAL 4 DAY)),
('ORD-20260809-001', 2, 2, 'Paula Lim', 490.00, 50.00, 49.00, 'maya', 'paid', 'delivered', DATE_SUB(CURDATE(), INTERVAL 5 DAY));

INSERT INTO rider_daily_collections (rider_id, collection_date, orders_completed, total_collected, commission_earned, remittance_status) VALUES
(1, CURDATE(), 12, 1000.00, 120.00, 'pending'),
(2, CURDATE(), 6, 500.00, 60.00, 'remitted'),
(3, CURDATE(), 4, 350.00, 42.00, 'pending'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 10, 850.00, 102.00, 'remitted'),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 8, 620.00, 74.40, 'remitted');

INSERT INTO payment_logs (order_id, rider_id, payment_method, amount, reference_number, status) VALUES
(3, 2, 'gcash', 580.00, 'GC-20260814-003', 'success'),
(4, 2, 'maya', 890.00, 'MY-20260814-004', 'success'),
(6, 3, 'card', 410.00, 'CC-20260813-001', 'success'),
(8, 3, 'gcash', 350.00, 'GC-20260811-001', 'success'),
(10, 2, 'maya', 490.00, 'MY-20260809-001', 'success');

INSERT INTO customer_reviews (order_id, rider_id, store_id, customer_name, rating, comment, is_flagged) VALUES
(1, 1, 1, 'John Customer', 5, 'Fast delivery and food was still hot!', 0),
(2, 1, 2, 'Jane Doe', 4, 'Good service, rider was polite.', 0),
(3, 2, 1, 'Mark Lee', 5, 'Excellent! Arrived ahead of schedule.', 0),
(4, 2, 3, 'Sarah Kim', 3, 'Delivery was late by 15 minutes.', 0),
(5, 1, 5, 'Tom Wilson', 5, 'Perfect handling of my order.', 0),
(6, 3, 2, 'Lisa Park', 2, 'Rider was rude and package was damaged.', 1),
(7, 2, 1, 'Mike Chen', 4, 'Decent experience overall.', 0),
(8, 3, 5, 'Nina Rose', 5, 'Very professional rider!', 0),
(9, 1, 3, 'Oscar Tan', 1, 'Wrong items delivered. Very disappointed.', 1),
(10, 2, 2, 'Paula Lim', 4, 'Smooth transaction.', 0);
