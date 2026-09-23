<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();
requirePlatform();

$pageMode = 'dispatch';
$tab = ($_GET['tab'] ?? 'kanban') === 'create' ? 'create' : 'kanban';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'reassign') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $riderId = (int)($_POST['rider_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'Staff reassignment'));
        if ($riderId > 0) {
            $ok = reassignOrderRider($orderId, $riderId, $reason);
            flash($ok ? 'success' : 'error', $ok ? 'Rider assigned/reassigned.' : 'Rider assignment failed.');
        } else {
            flash('error', 'Please select a rider.');
        }
        header('Location: ' . APP_URL . '/orders.php?tab=kanban');
        exit;
    }

    if ($action === 'update_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = (string)($_POST['new_status'] ?? '');
        $riderId = !empty($_POST['rider_id']) ? (int)$_POST['rider_id'] : null;
        $ok = updateOrderStatus($orderId, $newStatus, currentUserId(), $riderId);
        flash($ok ? 'success' : 'error', $ok ? ('Order status set to ' . $newStatus . '.') : 'Status update failed.');
        header('Location: ' . APP_URL . '/orders.php?tab=kanban');
        exit;
    }

    if ($action === 'create_direct') {
        $storeId = (int)($_POST['store_id'] ?? 0);
        if (!$storeId) {
            flash('error', 'Please select a pickup store.');
            header('Location: ' . APP_URL . '/orders.php?tab=create');
            exit;
        }
        $customer = [
            'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
            'customer_phone' => trim((string)($_POST['customer_phone'] ?? '')),
            'delivery_address' => trim((string)($_POST['delivery_address'] ?? '')),
            'notes' => trim((string)($_POST['notes'] ?? '')),
            'delivery_fee' => (float)($_POST['delivery_fee'] ?? 0),
            'payment_method' => (string)($_POST['payment_method'] ?? 'cash'),
            'payment_status' => (string)($_POST['payment_status'] ?? 'pending'),
        ];
        $rawLines = $_POST['lines'] ?? [];
        $lineItems = [];
        if (is_array($rawLines)) {
            foreach ($rawLines as $ln) {
                if (!is_array($ln)) continue;
                $menuItemId = !empty($ln['menu_item_id']) ? (int)$ln['menu_item_id'] : 0;
                $qty = max(1, (int)($ln['qty'] ?? 1));
                $line = [
                    'menu_item_id' => $menuItemId,
                    'qty' => $qty,
                    'selected_option_ids' => [],
                ];
                if (!$menuItemId) {
                    $line['item_name_snapshot'] = trim((string)($ln['item_name_snapshot'] ?? ''));
                    $line['unit_price_snapshot'] = (float)($ln['unit_price_snapshot'] ?? 0);
                }
                $optIds = $ln['selected_option_ids'] ?? [];
                if (is_array($optIds)) {
                    $line['selected_option_ids'] = array_map('intval', $optIds);
                }
                $opts = $ln['options'] ?? [];
                if (is_array($opts)) {
                    $line['options'] = $opts;
                }
                $lineItems[] = $line;
            }
        }
        $newId = createDirectOrder($customer, $storeId, $lineItems, (int)(currentUserId() ?? 0));
        if ($newId) {
            flash('success', 'Direct order #' . $newId . ' created successfully and queued for dispatch.');
            header('Location: ' . APP_URL . '/orders.php?tab=kanban');
        } else {
            flash('error', 'Order creation failed. Ensure customer name, phone, and at least 1 item are provided.');
            header('Location: ' . APP_URL . '/orders.php?tab=create');
        }
        exit;
    }
}

$stores = fetchStores();
$riders = fetchActiveRiders();

$kanbanCols = [
    'pending_dispatch' => [
        'label' => 'Pending Dispatch',
        'chip_class' => 'unassigned',
        'statuses' => ['placed', 'preparing', 'pending'],
        'rider_null' => true,
    ],
    'assigned' => [
        'label' => 'Assigned to Rider',
        'chip_class' => 'assigned',
        'statuses' => ['preparing', 'driver_assigned'],
        'rider_null' => false,
        'exclude_statuses' => ['out_for_delivery', 'delivered', 'cancelled'],
    ],
    'in_transit' => [
        'label' => 'In Transit',
        'chip_class' => 'enroute',
        'statuses' => ['out_for_delivery', 'picked_up'],
        'rider_null' => false,
    ],
    'completed' => [
        'label' => 'Completed',
        'chip_class' => 'delivered',
        'statuses' => ['delivered'],
        'rider_null' => null,
    ],
];

$pdo = getOpsDB();
$allOrdersSql = 'SELECT o.*, ps.store_name, r.full_name AS rider_name, r.rider_code
    FROM orders o
    LEFT JOIN partnership_stores ps ON ps.id = o.store_id
    LEFT JOIN riders r ON r.id = o.rider_id
    WHERE 1=1 ';
$orderParams = [];
if ($tab === 'kanban') {
    $allOrdersSql .= " AND o.order_status NOT IN ('cancelled') ";
}
$allOrdersSql .= ' ORDER BY o.created_at DESC LIMIT 250';
$stmt = $pdo->prepare($allOrdersSql);
$stmt->execute($orderParams);
$allOrders = array_map(fn($r) => decorateOrderRow($r), $stmt->fetchAll());

$bucketed = [];
foreach (array_keys($kanbanCols) as $k) $bucketed[$k] = [];

foreach ($allOrders as $o) {
    $s = $o['order_status'] ?? '';
    $riderId = (int)($o['rider_id'] ?? 0);
    if (in_array($s, $kanbanCols['pending_dispatch']['statuses'], true) && $riderId === 0) {
        $bucketed['pending_dispatch'][] = $o;
        continue;
    }
    if (in_array($s, $kanbanCols['completed']['statuses'], true)) {
        $bucketed['completed'][] = $o;
        continue;
    }
    if (in_array($s, $kanbanCols['in_transit']['statuses'], true)) {
        $bucketed['in_transit'][] = $o;
        continue;
    }
    if (in_array($s, $kanbanCols['assigned']['statuses'], true) && $riderId > 0) {
        if (!in_array($s, $kanbanCols['assigned']['exclude_statuses'] ?? [], true)) {
            $bucketed['assigned'][] = $o;
            continue;
        }
    }
    if (!in_array($s, ['cancelled'], true) && !in_array($o, $bucketed['pending_dispatch'], true) && $riderId === 0) {
        $bucketed['pending_dispatch'][] = $o;
    }
}

$catalogStoreId = (int)($_GET['store_id'] ?? ($stores[0]['id'] ?? 0));
$catalog = [];
$catalogGroups = [];
if ($catalogStoreId > 0) {
    $items = fetchMenuWithCounts($catalogStoreId);
    $byCat = [];
    foreach ($items as $it) {
        if (empty($it['is_available'])) continue;
        $cat = (string)($it['category'] ?? 'Uncategorized');
        if (!isset($byCat[$cat])) $byCat[$cat] = [];
        $byCat[$cat][] = $it;
    }
    $catalog = $byCat;
    $allItemIds = [];
    foreach ($items as $it) $allItemIds[] = (int)$it['id'];
    if ($allItemIds) {
        $in = implode(',', array_fill(0, count($allItemIds), '?'));
        $gStmt = $pdo->prepare('SELECT g.id AS group_id, g.item_id, g.group_name, g.selection_type, g.min_select, g.max_select, g.is_required,
            o.id AS option_id, o.option_name, o.price_delta, o.is_available
            FROM store_menu_item_option_groups g
            LEFT JOIN store_menu_item_options o ON o.group_id = g.id
            WHERE g.item_id IN (' . $in . ')
            ORDER BY g.item_id, g.sort_order, g.id, o.sort_order, o.id');
        $gStmt->execute($allItemIds);
        $rows = $gStmt->fetchAll();
        foreach ($rows as $r) {
            $iid = (int)$r['item_id'];
            $gid = (int)$r['group_id'];
            if (!isset($catalogGroups[$iid])) $catalogGroups[$iid] = [];
            if (!isset($catalogGroups[$iid][$gid])) {
                $catalogGroups[$iid][$gid] = [
                    'group_id' => $gid,
                    'group_name' => $r['group_name'],
                    'selection_type' => $r['selection_type'],
                    'min_select' => (int)$r['min_select'],
                    'max_select' => (int)$r['max_select'],
                    'is_required' => (int)$r['is_required'],
                    'options' => [],
                ];
            }
            if ($r['option_id'] && !empty($r['is_available'])) {
                $catalogGroups[$iid][$gid]['options'][] = [
                    'option_id' => (int)$r['option_id'],
                    'option_name' => $r['option_name'],
                    'price_delta' => (float)$r['price_delta'],
                ];
            }
        }
    }
}

$pageTitle = 'Dispatch & Rider Board';
include __DIR__ . '/includes/header.php';
?>

<div class="dispatch-tabs card" style="margin-bottom:1rem;padding:0.25rem 0.5rem;display:inline-flex;gap:0.25rem;">
    <a class="mode-tab<?= $tab === 'kanban' ? ' active' : '' ?>" href="?tab=kanban" data-mode="dispatch" style="font-size:0.9rem;">
        📋 Active Logistics Board
    </a>
    <a class="mode-tab<?= $tab === 'create' ? ' active' : '' ?>" href="?tab=create" data-mode="dispatch" style="font-size:0.9rem;">
        📒 Direct Order Entry
    </a>
</div>

<?php if ($tab === 'kanban'): ?>
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h2 style="margin:0;">🛵 Live Dispatch Board</h2>
            <small class="text-muted">4-stage logistics pipeline · Auto-refresh every 30s</small>
        </div>
        <a href="?tab=create" class="btn btn-primary btn-sm">+ Create Direct Order</a>
    </div>
    <div class="card-body">
        <div class="kanban dispatch-dense">
            <?php foreach ($kanbanCols as $colKey => $colConf): ?>
            <div class="kanban-col kanban-<?= e($colKey) ?>" data-col="<?= e($colKey) ?>">
                <div class="kanban-col-head">
                    <h3><?= e($colConf['label']) ?></h3>
                    <span class="courier-chip <?= e($colConf['chip_class']) ?>" style="margin-left:auto;"><?= count($bucketed[$colKey]) ?></span>
                </div>
                <div class="kanban-col-body">
                    <?php if (empty($bucketed[$colKey])): ?>
                        <div class="courier-card courier-empty">
                            <p class="text-muted" style="margin:0;text-align:center;padding:1rem 0.5rem;font-size:0.85rem;">No orders in this stage.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($bucketed[$colKey] as $order): ?>
                    <?php
                        $status = $order['order_status'] ?? '';
                        $riderId = (int)($order['rider_id'] ?? 0);
                        if ($colKey === 'pending_dispatch') $chip = 'unassigned';
                        elseif ($colKey === 'assigned') $chip = 'assigned';
                        elseif ($colKey === 'in_transit') $chip = 'enroute';
                        else $chip = 'delivered';
                        $isDelayed = !empty($order['is_delayed']) && $colKey !== 'completed';
                    ?>
                    <article class="courier-card<?= $isDelayed ? ' delayed' : '' ?>">
                        <header class="courier-card-head">
                            <div>
                                <div class="courier-card-order">#<?= e($order['order_number']) ?></div>
                                <div class="courier-card-store"><?= e($order['store_name'] ?? 'Store') ?></div>
                            </div>
                            <span class="courier-chip <?= $chip ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span>
                        </header>
                        <section class="courier-card-body">
                            <p style="margin:0 0 0.25rem 0;"><strong>👤 <?= e($order['customer_name']) ?></strong> · 📞 <?= e($order['customer_phone'] ?? '') ?></p>
                            <?php if (!empty($order['delivery_address'])): ?>
                                <p class="text-muted" style="margin:0 0 0.35rem 0;font-size:0.8rem;line-height:1.35;">📍 <?= e($order['delivery_address']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order['notes'])): ?>
                                <p style="margin:0 0 0.35rem 0;font-size:0.78rem;"><em>📝 <?= e($order['notes']) ?></em></p>
                            <?php endif; ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.35rem;">
                                <div>
                                    <div class="courier-card-rider">
                                        Rider: <?= $riderId ? e($order['rider_name'] ?? 'Assigned') : '<em>Unassigned</em>' ?>
                                        <?php if ($riderId && !empty($order['rider_code'])): ?>
                                            <span class="text-muted">(<?= e($order['rider_code']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isDelayed): ?>
                                        <span class="badge badge-danger" style="margin-top:0.25rem;">DELAY · <?= (int)($order['age_minutes'] ?? 0) ?>m</span>
                                    <?php endif; ?>
                                </div>
                                <div class="courier-card-total">₱<?= formatMoney((float)($order['order_total'] ?? 0)) ?></div>
                            </div>
                        </section>
                        <footer class="courier-card-foot">
                            <?php if ($colKey !== 'completed'): ?>
                                <form method="POST" class="rider-assign-form" style="margin:0 0 0.6rem 0;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reassign">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <div style="display:flex;gap:0.3rem;align-items:center;">
                                        <select class="form-control" name="rider_id" required style="flex:1;min-width:0;font-size:0.82rem;">
                                            <option value=""><?= $riderId ? 'Reassign rider…' : 'Assign rider…' ?></option>
                                            <?php foreach ($riders as $rd): ?>
                                                <option value="<?= (int)$rd['id'] ?>" <?= $riderId === (int)$rd['id'] ? 'selected' : '' ?>>
                                                    <?= e($rd['full_name']) ?> (<?= e($rd['duty_status'] ?? 'on') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary-outline btn-sm"><?= $riderId ? '↻' : '✓' ?></button>
                                    </div>
                                    <input class="form-control" name="reason" placeholder="Reason (optional)" style="margin-top:0.3rem;font-size:0.78rem;">
                                </form>
                            <?php endif; ?>
                            <div class="courier-card-actions">
                                <?php if ($colKey === 'pending_dispatch' || $colKey === 'assigned'): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                        <input type="hidden" name="new_status" value="out_for_delivery">
                                        <button type="submit" class="btn btn-warning btn-sm">🚚 Mark In Transit</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($colKey === 'in_transit'): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                        <input type="hidden" name="new_status" value="delivered">
                                        <button type="submit" class="btn btn-success btn-sm">✅ Mark Delivered</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($colKey === 'completed'): ?>
                                    <span class="text-muted" style="font-size:0.8rem;">
                                        Delivered at: <?= !empty($order['delivered_at']) ? e(formatDateTime($order['delivered_at'])) : '—' ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($colKey !== 'completed'): ?>
                                <form method="POST" style="margin-left:auto;" onsubmit="return confirm('Cancel order #<?= e($order['order_number']) ?>?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="new_status" value="cancelled">
                                    <button type="submit" class="btn btn-danger-outline btn-sm" title="Cancel order">✕ Cancel</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </footer>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php else: ?>
<div class="notebook-panel" id="notebookPanel">
    <div class="notebook-left card">
        <div class="card-header">
            <h2>📒 Direct Order Entry</h2>
            <small class="text-muted">Create a new manual dispatch order for a customer</small>
        </div>
        <div class="card-body">
            <form id="directOrderForm" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_direct">

                <div class="notebook-section">
                    <h3 class="notebook-section-title">Customer &amp; Delivery</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Customer Name <span class="req">*</span></label>
                            <input class="form-control" name="customer_name" required placeholder="Juan Dela Cruz">
                        </div>
                        <div class="form-group">
                            <label>Contact Number <span class="req">*</span></label>
                            <input class="form-control" name="customer_phone" required placeholder="0917 000 0000">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pickup Store <span class="req">*</span></label>
                            <select class="form-control" name="store_id" id="pickupStoreSelect" required>
                                <option value="">Select store…</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $catalogStoreId ? 'selected' : '' ?>><?= e($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Delivery Fee (₱)</label>
                            <input class="form-control" name="delivery_fee" id="deliveryFeeInput" type="number" step="0.01" min="0" value="49">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Delivery Address</label>
                        <textarea class="form-control" name="delivery_address" rows="2" placeholder="House/Unit, Street, Barangay, City…"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select class="form-control" name="payment_method">
                                <option value="cash">Cash on Delivery</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select class="form-control" name="payment_status">
                                <option value="pending">Pending (Collect on Delivery)</option>
                                <option value="paid">Paid in Advance</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes / Special Instructions</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Ring doorbell twice, leave at gate, extra utensils…"></textarea>
                    </div>
                </div>

                <div class="notebook-section" id="lineItemsSection">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                        <h3 class="notebook-section-title" style="margin:0;">Items <span class="req">*</span></h3>
                        <button type="button" class="btn btn-primary-outline btn-sm" id="btnAddOrderLine">+ Add Item</button>
                    </div>
                    <div id="orderLinesContainer"></div>
                </div>

                <template id="tplOrderLine">
                    <div class="order-line-row" data-line-idx="0">
                        <div class="order-line-head">
                            <select class="form-control order-line-menu" required style="flex:1;min-width:0;">
                                <option value="">Select an item…</option>
                            </select>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn qty-minus">−</button>
                                <input type="number" class="qty-input" name="" min="1" value="1" readonly>
                                <button type="button" class="qty-btn qty-plus">+</button>
                            </div>
                            <button type="button" class="btn btn-danger-outline btn-xs btn-remove-line" title="Remove line">✕</button>
                        </div>
                        <div class="order-line-opts" style="margin-top:0.5rem;"></div>
                        <div class="order-line-totals" style="text-align:right;margin-top:0.35rem;">
                            <small class="text-muted order-line-unit">₱0.00 × 1</small>
                            <span class="order-line-subtotal">= ₱0.00</span>
                        </div>
                    </div>
                </template>

                <div class="notebook-summary card" style="margin-top:1rem;">
                    <div class="summary-row"><span>Items Subtotal</span><span id="subtotalDisplay">₱0.00</span></div>
                    <div class="summary-row"><span>Delivery Fee</span><span id="feeDisplay">₱0.00</span></div>
                    <div class="summary-row summary-grand"><span>GRAND TOTAL</span><span id="grandDisplay">₱0.00</span></div>
                </div>

                <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">🚚 Create &amp; Queue for Dispatch</button>
                </div>
            </form>
        </div>
    </div>

    <aside class="notebook-right card">
        <div class="card-header">
            <h2>🏪 Store Catalog</h2>
            <small class="text-muted">Pick from menu items with variants</small>
        </div>
        <div class="card-body catalog-side">
            <?php if (!$catalogStoreId): ?>
                <p class="text-muted">Select a pickup store first.</p>
            <?php elseif (empty($catalog)): ?>
                <p class="text-muted">No available menu items for this store yet. Create items in the Menu Manager.</p>
            <?php else: ?>
                <?php foreach ($catalog as $cat => $items): ?>
                    <div class="catalog-group">
                        <h4 class="catalog-group-title"><?= e($cat) ?></h4>
                        <?php foreach ($items as $it): ?>
                            <button type="button" class="catalog-item"
                                data-id="<?= (int)$it['id'] ?>"
                                data-name="<?= e($it['item_name']) ?>"
                                data-price="<?= (float)$it['price'] ?>"
                                title="Add to order">
                                <span class="catalog-item-name"><?= e($it['item_name']) ?></span>
                                <span class="catalog-item-price">₱<?= formatMoney((float)$it['price']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script id="catalogGroupsData" type="application/json"><?= json_encode($catalogGroups) ?></script>
<script id="catalogItemsData" type="application/json"><?= json_encode($catalog) ?></script>
<?php endif; ?>

<meta http-equiv="refresh" content="30;url=<?= e(APP_URL) ?>/orders.php?tab=<?= e($tab) ?>">
<?php include __DIR__ . '/includes/footer.php'; ?>
