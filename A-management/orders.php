<?php

declare(strict_types=1);

require_once __DIR__.'/includes/functions.php';
requireOpsLogin();
requirePlatform();

$pageMode = 'dispatch';
$tab = ($_GET['tab'] ?? 'kanban') === 'create' ? 'create' : 'kanban';
$validStages = ['pending_dispatch', 'assigned', 'in_transit', 'completed'];
$selectedStage = in_array($_GET['stage'] ?? '', $validStages, true) ? (string) $_GET['stage'] : 'pending_dispatch';
$selectedStageLabel = $selectedStage === 'assigned' ? 'Assign to Rider' : [
    'pending_dispatch' => 'Pending Dispatch',
    'in_transit' => 'In Transit',
    'completed' => 'Completed',
][$selectedStage];
$requestedDate = isset($_GET['completed_date']) && is_string($_GET['completed_date'])
    ? $_GET['completed_date']
    : date('Y-m-d');
$completedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) === 1
    && checkdate((int) substr($requestedDate, 5, 2), (int) substr($requestedDate, 8, 2), (int) substr($requestedDate, 0, 4))
    ? $requestedDate
    : date('Y-m-d');
$stageBackUrl = APP_URL.'/orders.php?tab=kanban&stage='.rawurlencode($selectedStage)
    .($selectedStage === 'completed' ? '&completed_date='.rawurlencode($completedDate) : '');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'reassign') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $riderId = (int) ($_POST['rider_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? 'Staff reassignment'));
        if ($riderId > 0) {
            $ok = reassignOrderRider($orderId, $riderId, $reason);
            flash($ok ? 'success' : 'error', $ok ? 'Rider assigned/reassigned.' : 'Rider assignment failed.');
        } else {
            flash('error', 'Please select a rider.');
        }
        header('Location: '.$stageBackUrl);
        exit;
    }

    if ($action === 'update_status') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $newStatus = (string) ($_POST['new_status'] ?? '');
        $riderId = ! empty($_POST['rider_id']) ? (int) $_POST['rider_id'] : null;
        $ok = updateOrderStatus($orderId, $newStatus, currentUserId(), $riderId);
        flash($ok ? 'success' : 'error', $ok ? ('Order status set to '.$newStatus.'.') : 'Status update failed.');
        header('Location: '.$stageBackUrl);
        exit;
    }

    if ($action === 'create_direct') {
        $storeId = (int) ($_POST['store_id'] ?? 0);
        if (! $storeId) {
            flash('error', 'Please select a pickup store.');
            header('Location: '.APP_URL.'/orders.php?tab=create');
            exit;
        }
        $customer = [
            'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
            'customer_phone' => trim((string) ($_POST['customer_phone'] ?? '')),
            'delivery_address' => trim((string) ($_POST['delivery_address'] ?? '')),
            'google_maps_location' => trim((string) ($_POST['google_maps_location'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'delivery_fee' => (float) ($_POST['delivery_fee'] ?? 0),
            'payment_method' => (string) ($_POST['payment_method'] ?? 'cash'),
            'payment_status' => (string) ($_POST['payment_status'] ?? 'pending'),
        ];
        $mapsLocation = trim((string) ($_POST['google_maps_location'] ?? ''));
        if ($mapsLocation !== '' && parseGoogleMapsCoordinates($mapsLocation) === null) {
            flash('error', 'Paste a full Google Maps pin link or enter coordinates as latitude, longitude.');
            header('Location: '.APP_URL.'/orders.php?tab=create');
            exit;
        }
        $customer['google_maps_location'] = $mapsLocation;
        $rawLines = $_POST['lines'] ?? [];
        $lineItems = [];
        if (is_array($rawLines)) {
            foreach ($rawLines as $ln) {
                if (! is_array($ln)) {
                    continue;
                }
                $menuItemId = ! empty($ln['menu_item_id']) ? (int) $ln['menu_item_id'] : 0;
                $qty = max(1, (int) ($ln['qty'] ?? 1));
                $line = [
                    'menu_item_id' => $menuItemId,
                    'qty' => $qty,
                    'selected_option_ids' => [],
                ];
                if (! $menuItemId) {
                    $line['item_name_snapshot'] = trim((string) ($ln['item_name_snapshot'] ?? ''));
                    $line['unit_price_snapshot'] = (float) ($ln['unit_price_snapshot'] ?? 0);
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
        $newId = createDirectOrder($customer, $storeId, $lineItems, (int) (currentUserId() ?? 0));
        if ($newId) {
            flash('success', 'Direct order #'.$newId.' created successfully and queued for dispatch.');
            header('Location: '.APP_URL.'/orders.php?tab=kanban');
        } else {
            flash('error', 'Order creation failed. Ensure customer name, phone, and at least 1 item are provided.');
            header('Location: '.APP_URL.'/orders.php?tab=create');
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
$completedDailyCount = 0;
if ($tab === 'kanban' && $selectedStage === 'completed') {
    $completedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM orders
        WHERE order_status = 'delivered'
            AND DATE(COALESCE(delivered_at, updated_at, created_at)) = ?");
    $completedCountStmt->execute([$completedDate]);
    $completedDailyCount = (int) $completedCountStmt->fetchColumn();
}
$allOrdersSql = 'SELECT o.*, ps.store_name, r.full_name AS rider_name, r.rider_code
    FROM orders o
    LEFT JOIN partnership_stores ps ON ps.id = o.store_id
    LEFT JOIN riders r ON r.id = o.rider_id
    WHERE 1=1 ';
$orderParams = [];
if ($tab === 'kanban') {
    $allOrdersSql .= " AND o.order_status NOT IN ('cancelled') ";
    if ($selectedStage === 'completed') {
        $allOrdersSql .= " AND o.order_status = 'delivered'
            AND DATE(COALESCE(o.delivered_at, o.updated_at, o.created_at)) = ? ";
        $orderParams[] = $completedDate;
    }
}
$allOrdersSql .= ' ORDER BY o.created_at DESC LIMIT 250';
$stmt = $pdo->prepare($allOrdersSql);
$stmt->execute($orderParams);
$allOrders = array_map(fn ($r) => decorateOrderRow($r), $stmt->fetchAll());

$bucketed = [];
foreach (array_keys($kanbanCols) as $k) {
    $bucketed[$k] = [];
}

foreach ($allOrders as $o) {
    $s = $o['order_status'] ?? '';
    $riderId = (int) ($o['rider_id'] ?? 0);
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
        if (! in_array($s, $kanbanCols['assigned']['exclude_statuses'] ?? [], true)) {
            $bucketed['assigned'][] = $o;

            continue;
        }
    }
    if (! in_array($s, ['cancelled'], true) && ! in_array($o, $bucketed['pending_dispatch'], true) && $riderId === 0) {
        $bucketed['pending_dispatch'][] = $o;
    }
}

$catalogStoreId = (int) ($_GET['store_id'] ?? ($stores[0]['id'] ?? 0));
$catalog = [];
$catalogGroups = [];
if ($catalogStoreId > 0) {
    $items = fetchMenuWithCounts($catalogStoreId);
    $byCat = [];
    foreach ($items as $it) {
        if (empty($it['is_available'])) {
            continue;
        }
        $cat = (string) ($it['category'] ?? 'Uncategorized');
        if (! isset($byCat[$cat])) {
            $byCat[$cat] = [];
        }
        $byCat[$cat][] = $it;
    }
    $catalog = $byCat;
    $allItemIds = [];
    foreach ($items as $it) {
        $allItemIds[] = (int) $it['id'];
    }
    if ($allItemIds) {
        $in = implode(',', array_fill(0, count($allItemIds), '?'));
        $gStmt = $pdo->prepare('SELECT g.id AS group_id, g.item_id, g.group_name, g.selection_type, g.min_select, g.max_select, g.is_required,
            o.id AS option_id, o.option_name, o.price_delta, o.is_available
            FROM store_menu_item_option_groups g
            LEFT JOIN store_menu_item_options o ON o.group_id = g.id
            WHERE g.item_id IN ('.$in.')
            ORDER BY g.item_id, g.sort_order, g.id, o.sort_order, o.id');
        $gStmt->execute($allItemIds);
        $rows = $gStmt->fetchAll();
        foreach ($rows as $r) {
            $iid = (int) $r['item_id'];
            $gid = (int) $r['group_id'];
            if (! isset($catalogGroups[$iid])) {
                $catalogGroups[$iid] = [];
            }
            if (! isset($catalogGroups[$iid][$gid])) {
                $catalogGroups[$iid][$gid] = [
                    'group_id' => $gid,
                    'group_name' => $r['group_name'],
                    'selection_type' => $r['selection_type'],
                    'min_select' => (int) $r['min_select'],
                    'max_select' => (int) $r['max_select'],
                    'is_required' => (int) $r['is_required'],
                    'options' => [],
                ];
            }
            if ($r['option_id'] && ! empty($r['is_available'])) {
                $catalogGroups[$iid][$gid]['options'][] = [
                    'option_id' => (int) $r['option_id'],
                    'option_name' => $r['option_name'],
                    'price_delta' => (float) $r['price_delta'],
                ];
            }
        }
    }
}

$pageTitle = 'Dispatch & Rider Board';
include __DIR__.'/includes/header.php';
?>

<div class="dispatch-tabs card" style="margin-bottom:1rem;padding:0.25rem 0.5rem;display:inline-flex;gap:0.25rem;">
    <a class="mode-tab<?= $tab === 'kanban' ? ' active' : '' ?>" href="?tab=kanban" data-mode="dispatch" style="font-size:0.9rem;">
        Logistics Board
    </a>
    <a class="mode-tab<?= $tab === 'create' ? ' active' : '' ?>" href="?tab=create" data-mode="dispatch" style="font-size:0.9rem;">
        Direct Order Entry
    </a>
</div>

<?php if ($tab === 'kanban') { ?>
<div class="card dispatch-stage-panel">
    <div class="card-header dispatch-stage-toolbar">
        <div class="dispatch-stage-heading">
            <h2>Live Dispatch Board</h2>
            <small class="text-muted">Select an order stage to review its details.</small>
        </div>
        <div class="dispatch-stage-controls">
            <form method="GET" class="dispatch-stage-filter">
                <input type="hidden" name="tab" value="kanban">
                <label for="dispatchStage">Order stage</label>
                <select class="form-control" name="stage" id="dispatchStage" onchange="this.form.submit()">
                    <?php foreach ($kanbanCols as $stageKey => $stageConfig) { ?>
                        <option value="<?= e($stageKey) ?>" <?= $selectedStage === $stageKey ? 'selected' : '' ?>><?= e($stageKey === 'assigned' ? 'Assign to Rider' : $stageConfig['label']) ?></option>
                    <?php } ?>
                </select>
                <?php if ($selectedStage === 'completed') { ?>
                    <label for="completedDate">Completed on</label>
                    <input class="form-control dispatch-date-input" type="date" name="completed_date" id="completedDate" value="<?= e($completedDate) ?>" onchange="this.form.submit()">
                <?php } ?>
                <noscript><button type="submit" class="btn btn-outline btn-sm">Apply</button></noscript>
            </form>
            <a href="?tab=create" class="btn btn-primary btn-sm">+ Create Direct Order</a>
        </div>
    </div>
    <div class="card-body">
        <div class="dispatch-stage-summary">
            <div><strong><?= e($selectedStageLabel) ?></strong><span class="text-muted"> · <?= $selectedStage === 'completed' ? $completedDailyCount : count($bucketed[$selectedStage]) ?> orders</span></div>
            <?php if ($selectedStage === 'completed') { ?>
                <span class="text-muted">Completed on <?= e(date('F j, Y', strtotime($completedDate))) ?>: <strong><?= $completedDailyCount ?></strong></span>
            <?php } ?>
        </div>
        <div class="dispatch-stage-table-wrap">
            <table class="dispatch-stage-table">
                <thead><tr>
                    <th>Order</th><th>Customer</th><th>Delivery address</th><th>Status</th><th>Rider</th><th>Total</th>
                    <th><?= $selectedStage === 'completed' ? 'Delivered' : 'Placed' ?></th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($bucketed[$selectedStage])) { ?>
                    <tr><td colspan="8" class="dispatch-stage-empty">No orders<?= $selectedStage === 'completed' ? ' completed on this date' : ' in this stage' ?>.</td></tr>
                <?php } ?>
                <?php foreach ($bucketed[$selectedStage] as $order) {
                    $riderId = (int) ($order['rider_id'] ?? 0);
                    $status = (string) ($order['order_status'] ?? '');
                    $chip = $selectedStage === 'pending_dispatch' ? 'unassigned' : ($selectedStage === 'assigned' ? 'assigned' : ($selectedStage === 'in_transit' ? 'enroute' : 'delivered'));
                    $orderDate = $selectedStage === 'completed'
                        ? ($order['delivered_at'] ?? $order['updated_at'] ?? $order['created_at'] ?? null)
                        : ($order['created_at'] ?? null);
                    ?>
                    <tr>
                        <td><strong>#<?= e($order['order_number']) ?></strong><small><?= e($order['store_name'] ?? 'Store') ?></small></td>
                        <td><strong><?= e($order['customer_name'] ?? 'Customer') ?></strong><small><?= e($order['customer_phone'] ?? '') ?></small></td>
                        <td class="dispatch-address-cell"><?= e($order['delivery_address'] ?? '—') ?><?php if (! empty($order['notes'])) { ?><small><?= e($order['notes']) ?></small><?php } ?></td>
                        <td><span class="courier-chip <?= e($chip) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span><?php if (! empty($order['is_delayed']) && $selectedStage !== 'completed') { ?><small class="dispatch-delay-label">Delayed · <?= (int) ($order['age_minutes'] ?? 0) ?> min</small><?php } ?></td>
                        <td><?= $riderId ? e($order['rider_name'] ?? 'Assigned') : '<span class="text-muted">Unassigned</span>' ?><?= $riderId && ! empty($order['rider_code']) ? '<small>'.e($order['rider_code']).'</small>' : '' ?></td>
                        <td class="dispatch-amount">₱<?= formatMoney((float) ($order['order_total'] ?? 0)) ?></td>
                        <td><?= $orderDate ? e(formatDateTime($orderDate)) : '—' ?></td>
                        <td class="dispatch-actions-cell">
                            <?php if ($selectedStage !== 'completed') { ?>
                                <div class="dispatch-row-actions">
                                    <form method="POST" class="dispatch-inline-form">
                                        <?= csrfField() ?><input type="hidden" name="action" value="reassign"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <select class="form-control" name="rider_id" required aria-label="Assign or reassign rider">
                                            <option value=""><?= $riderId ? 'Reassign rider…' : 'Assign to rider…' ?></option>
                                            <?php foreach ($riders as $rd) { ?><option value="<?= (int) $rd['id'] ?>" <?= $riderId === (int) $rd['id'] ? 'selected' : '' ?>><?= e($rd['full_name']) ?></option><?php } ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary-outline btn-sm"><?= $riderId ? 'Update' : 'Assign' ?></button>
                                    </form>
                                    <?php if ($selectedStage === 'pending_dispatch' || $selectedStage === 'assigned') { ?>
                                        <form method="POST" class="dispatch-inline-form"><?= csrfField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="new_status" value="out_for_delivery"><button type="submit" class="btn btn-warning btn-sm">Mark in transit</button></form>
                                    <?php } elseif ($selectedStage === 'in_transit') { ?>
                                        <form method="POST" class="dispatch-inline-form"><?= csrfField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="new_status" value="delivered"><button type="submit" class="btn btn-success btn-sm">Mark delivered</button></form>
                                    <?php } ?>
                                    <form method="POST" class="dispatch-inline-form" onsubmit="return confirm('Cancel order #<?= e($order['order_number']) ?>?')"><?= csrfField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="new_status" value="cancelled"><button type="submit" class="btn btn-danger-outline btn-sm">Cancel</button></form>
                                </div>
                            <?php } else { ?><span class="text-muted">Delivered <?= $orderDate ? e(formatDateTime($orderDate)) : '' ?></span><?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php } else { ?><div class="notebook-panel" id="notebookPanel">
    <div class="notebook-left card">
        <div class="card-header">
            <h2>Direct Order Entry</h2>
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
                                <?php foreach ($stores as $s) { ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $catalogStoreId ? 'selected' : '' ?>><?= e($s['store_name']) ?></option>
                                <?php } ?>
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
                    <div class="form-group">
                        <label for="googleMapsLocation">Google Maps Pin</label>
                        <input class="form-control" id="googleMapsLocation" name="google_maps_location" type="text" placeholder="Paste a Google Maps link or latitude, longitude">
                        <small class="text-muted">Use a dropped pin or shared Maps link for precise rider navigation.</small>
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
                    <p id="orderOptionsError" class="order-options-error" role="alert" hidden>Please select a menu item and complete its required options before creating the order.</p>
                </div>

                <template id="tplOrderLine">
                    <div class="order-line-row" data-line-idx="0">
                        <div class="order-line-head">
                            <select class="form-control order-line-menu" style="flex:1;min-width:0;">
                                <option value="">Select an item…</option>
                            </select>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn qty-minus">−</button>
                                <input type="number" class="qty-input" name="" min="1" value="1" readonly>
                                <button type="button" class="qty-btn qty-plus">+</button>
                            </div>
                            <button type="button" class="btn btn-danger-outline btn-xs btn-remove-line" title="Remove line">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="order-line-opts" style="margin-top:0.5rem;"></div>
                        <div class="order-line-totals" style="text-align:right;margin-top:0.35rem;">
                            <small class="text-muted order-line-unit">₱0.00 × 1</small>
                            <span class="order-line-subtotal">= ₱0.00</span>
                        </div>
                        <div class="order-line-hidden" hidden></div>
                    </div>
                </template>

                <div class="notebook-summary card" style="margin-top:1rem;">
                    <div class="summary-row"><span>Items Subtotal</span><span id="subtotalDisplay">₱0.00</span></div>
                    <div class="summary-row"><span>Delivery Fee</span><span id="feeDisplay">₱0.00</span></div>
                    <div class="summary-row summary-grand"><span>GRAND TOTAL</span><span id="grandDisplay">₱0.00</span></div>
                </div>

                <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Create &amp; Queue for Dispatch</button>
                </div>
            </form>
        </div>
    </div>

    <aside class="notebook-right card">
        <div class="card-header">
            <h2>Store Catalog</h2>
            <small class="text-muted">Pick from menu items with variants</small>
        </div>
        <div class="card-body catalog-side">
            <?php if (! $catalogStoreId) { ?>
                <p class="text-muted">Select a pickup store first.</p>
            <?php } elseif (empty($catalog)) { ?>
                <p class="text-muted">No available menu items for this store yet. Create items in the Menu Manager.</p>
            <?php } else { ?>
                <?php foreach ($catalog as $cat => $items) { ?>
                    <div class="catalog-group">
                        <h4 class="catalog-group-title"><?= e($cat) ?></h4>
                        <?php foreach ($items as $it) { ?>
                            <button type="button" class="catalog-item"
                                data-id="<?= (int) $it['id'] ?>"
                                data-name="<?= e($it['item_name']) ?>"
                                data-price="<?= (float) $it['price'] ?>"
                                title="Add to order">
                                <span class="catalog-item-name"><?= e($it['item_name']) ?></span>
                                <span class="catalog-item-price">₱<?= formatMoney((float) $it['price']) ?></span>
                            </button>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </aside>
</div>

<script id="catalogGroupsData" type="application/json"><?= json_encode($catalogGroups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script id="catalogItemsData" type="application/json"><?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php } ?>

<meta http-equiv="refresh" content="30;url=<?= e(APP_URL) ?>/orders.php?tab=<?= e($tab) ?><?= $tab === 'kanban' ? '&amp;stage='.rawurlencode($selectedStage).($selectedStage === 'completed' ? '&amp;completed_date='.rawurlencode($completedDate) : '') : '' ?>">
<?php include __DIR__.'/includes/footer.php'; ?>
