<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requirePlatform();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null) && ($_POST['action'] ?? '') === 'resolve') {
    if (!isPlatformStaff()) {
        flash('error', 'Daily ticket resolution is handled by Motobook management staff.');
        header('Location: ' . APP_URL . '/tickets.php');
        exit;
    }

    $ticketId = (int) $_POST['ticket_id'];
    $action = $_POST['resolution'] ?? 'resolve';
    $notes = trim($_POST['notes'] ?? '');
    $refundAmount = ($action === 'refund') ? (float) ($_POST['refund_amount'] ?? 0) : 0;
    $maxRefund = (float) settingValue('max_refund_amount', '500');

    $pdo = getOpsDB();
    $ticketStmt = $pdo->prepare('SELECT * FROM complaint_tickets WHERE id = ?');
    $ticketStmt->execute([$ticketId]);
    $ticket = $ticketStmt->fetch();

    if (!$ticket) {
        flash('error', 'Ticket not found.');
        header('Location: ' . APP_URL . '/tickets.php');
        exit;
    }

    $message = 'Ticket updated.';

    if ($action === 'refund') {
        if ($refundAmount <= 0 || $refundAmount > $maxRefund) {
            flash('error', 'Refund exceeds Super Admin operational limit of ' . formatMoney($maxRefund) . '.');
            header('Location: ' . APP_URL . '/tickets.php?id=' . $ticketId . '#detail');
            exit;
        }
        $pdo->prepare('INSERT INTO refunds (ticket_id, order_id, amount, refund_type, status, processed_by_staff_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$ticketId, $ticket['order_id'], $refundAmount, 'refund', 'approved', (int) (currentUser()['actor_id'] ?? currentUserId() ?? 0), $notes]);
        if ($ticket['order_id']) {
            $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?")->execute([$ticket['order_id']]);
        }
        $notes = 'Refund ' . formatMoney($refundAmount) . '. ' . $notes;
        $message = 'Refund approved within Super Admin limits.';
    } elseif ($action === 'replacement') {
        $pdo->prepare('INSERT INTO refunds (ticket_id, order_id, amount, refund_type, status, processed_by_staff_id, notes) VALUES (?, ?, 0, ?, ?, ?, ?)')
            ->execute([$ticketId, $ticket['order_id'], 'replacement', 'approved', (int) (currentUser()['actor_id'] ?? currentUserId() ?? 0), $notes]);
        $notes = 'Replacement order issued. ' . $notes;
        $message = 'Replacement order issued.';
    }

    $ok = resolveTicket($ticketId, $action, $notes, $refundAmount);
    flash($ok ? 'success' : 'error', $ok ? $message : 'Resolution failed.');
    header('Location: ' . APP_URL . '/tickets.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$tickets = fetchTickets($statusFilter !== '' ? $statusFilter : null);

$detailId = (int) ($_GET['id'] ?? 0);
$ticket = null;
$evidence = [];
$chats = [];
$gps = [];
if ($detailId) {
    $ticket = fetchTicketById($detailId);
    if ($ticket) {
        $pdo = getOpsDB();
        $ev = $pdo->prepare('SELECT * FROM ticket_evidence WHERE ticket_id = ?');
        $ev->execute([$detailId]);
        $evidence = $ev->fetchAll();
        if (!empty($ticket['order_id'])) {
            $c = $pdo->prepare('SELECT * FROM order_chats WHERE order_id = ? ORDER BY sent_at');
            $c->execute([$ticket['order_id']]);
            $chats = $c->fetchAll();
            $g = $pdo->prepare('SELECT * FROM gps_tracks WHERE order_id = ? ORDER BY recorded_at');
            $g->execute([$ticket['order_id']]);
            $gps = $g->fetchAll();
        }
    }
}

$maxRefund = (float) settingValue('max_refund_amount', '500');

$pageTitle = 'Customer Complaint Tickets';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>🎫 Complaint ticket queue</h2>
        <div>
            <a class="btn btn-outline btn-sm <?= !$statusFilter ? 'btn-primary' : '' ?>" href="<?= APP_URL ?>/tickets.php">All</a>
            <a class="btn btn-outline btn-sm <?= $statusFilter === 'open' ? 'btn-primary' : '' ?>" href="?status=open">Open</a>
            <a class="btn btn-outline btn-sm <?= $statusFilter === 'in_review' ? 'btn-primary' : '' ?>" href="?status=in_review">In Review</a>
            <a class="btn btn-outline btn-sm <?= $statusFilter === 'resolved' ? 'btn-primary' : '' ?>" href="?status=resolved">Resolved</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Ticket</th><th>Time</th><th>Customer</th><th>Category</th><th>Order</th><th>Store</th><th>Rider</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><strong><?= e($t['ticket_number']) ?></strong></td>
                            <td><?= formatDateTime($t['created_at']) ?></td>
                            <td><?= e($t['customer_name']) ?><br><small><?= e($t['customer_phone'] ?? '') ?></small></td>
                            <td><span class="badge badge-info"><?= e(ucwords(str_replace('_', ' ', (string) $t['category']))) ?></span></td>
                            <td><?= e($t['order_number'] ?? '—') ?></td>
                            <td><?= e($t['store_name'] ?? '—') ?></td>
                            <td><?= e($t['rider_name'] ?? '—') ?></td>
                            <td><?= statusBadge((string) $t['status']) ?></td>
                            <td><a class="btn btn-outline btn-sm" href="?id=<?= (int) $t['id'] ?>#detail">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="9" class="text-muted">No tickets in this queue.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($ticket): $t = $ticket; ?>
<div class="card" id="detail">
    <div class="card-header"><h2>🔍 Ticket evidence review · <?= e($t['ticket_number']) ?></h2></div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem">Customer report</h3>
                <p><strong>Customer:</strong> <?= e($t['customer_name']) ?> · <?= e($t['customer_phone'] ?? '') ?></p>
                <p><strong>Category:</strong> <?= e(ucwords(str_replace('_', ' ', (string) $t['category']))) ?></p>
                <p><strong>Order:</strong> <?= e($t['order_number'] ?? '—') ?> · <?= formatMoney((float) ($t['order_total'] ?? 0)) ?></p>
                <p><strong>Store:</strong> <?= e($t['store_name'] ?? '—') ?> · <strong>Rider:</strong> <?= e($t['rider_name'] ?? '—') ?></p>
                <p><strong>Status:</strong> <?= statusBadge((string) $t['status']) ?></p>
                <div class="card" style="margin-top:.75rem;border-left:4px solid var(--warning)">
                    <div class="card-body"><strong>Customer description:</strong><br><?= nl2br(e($t['description'])) ?></div>
                </div>
                <?php if (!empty($t['photo_path'])): ?>
                    <p style="margin-top:.75rem"><strong>Attached photo:</strong><br>
                        <img src="<?= ADMIN_URL ?>/<?= e($t['photo_path']) ?>" alt="Ticket photo" style="max-width:100%;max-height:260px;border-radius:8px;border:1px solid var(--cyan-200)">
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem">Evidence</h3>
                <?php if (empty($evidence)): ?>
                    <p class="text-muted">No uploaded evidence.</p>
                <?php else: ?>
                    <?php foreach ($evidence as $ev): ?>
                        <div class="chat">
                            <strong><?= e(ucwords((string) $ev['evidence_type'])) ?></strong>
                            <div><?= e($ev['content'] ?? $ev['file_path'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3 style="font-size:.95rem;margin:1rem 0 .5rem">💬 Order chat</h3>
                <?php if (empty($chats)): ?>
                    <p class="text-muted">No chat history.</p>
                <?php else: ?>
                    <?php foreach (array_slice($chats, -5) as $chat): ?>
                        <div class="chat"><strong><?= e($chat['sender_name']) ?></strong> <small><?= e($chat['sender_role']) ?></small><div><?= e($chat['message']) ?></div></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3 style="font-size:.95rem;margin:1rem 0 .5rem">📍 Last GPS point</h3>
                <?php if (empty($gps)): ?>
                    <p class="text-muted">No GPS track.</p>
                <?php else: ?>
                    <?php $last = end($gps); ?>
                    <p>Lat <?= e((string) $last['latitude']) ?>, Lng <?= e((string) $last['longitude']) ?><br><small><?= formatDateTime($last['recorded_at']) ?></small></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($t['status'] ?? '') !== 'resolved' && ($t['status'] ?? '') !== 'rejected'): ?>
            <div class="card" style="margin-top:1.25rem;border:2px solid var(--cyan-200)">
                <div class="card-header"><h2>⚖️ Resolve ticket (within Super Admin limits)</h2></div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Max operational refund cap: <strong><?= formatMoney($maxRefund) ?></strong>. Exceeding this amount requires Super Admin approval.
                    </div>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Resolution action</label>
                                <select class="form-control" id="resolution" name="resolution" required onchange="document.getElementById('refundField').style.display=this.value==='refund'?'block':'none'">
                                    <option value="resolve">Resolve with notes (no refund)</option>
                                    <option value="refund">✅ Approve refund (up to cap)</option>
                                    <option value="replacement">🔄 Issue replacement order</option>
                                    <option value="reject">❌ Reject complaint</option>
                                </select>
                            </div>
                            <div class="form-group" id="refundField" style="display:none">
                                <label>Refund amount (₱)</label>
                                <input class="form-control" type="number" step="0.01" name="refund_amount" min="0" max="<?= $maxRefund ?>" placeholder="0.00 - <?= $maxRefund ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Resolution notes / customer-facing response</label>
                            <textarea class="form-control" name="notes" rows="3" required placeholder="Explanation, follow-up actions, or replacement order details…"></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Submit resolution</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success" style="margin-top:1rem"><strong>Resolution:</strong> <?= e($t['resolution_notes'] ?? '') ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
