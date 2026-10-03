<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireOpsLogin();

$user = currentUser();
$isStore = isStoreStaff();
$myStoreId = currentUserStoreId() ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' && $isStore) {
        $subject = trim($_POST['subject'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $message = trim($_POST['message'] ?? '');
        $ok = $subject !== '' && $message !== '' && createHelpdeskTicket($subject, $category, $message);
        flash($ok ? 'success' : 'error', $ok ? 'Support ticket submitted.' : 'Ticket creation failed.');
        header('Location: ' . APP_URL . '/helpdesk.php');
        exit;
    }

    if ($action === 'reply' && isPlatformStaff()) {
        $id = (int) $_POST['ticket_id'];
        $reply = trim($_POST['reply'] ?? '');
        $ok = $reply !== '' && replyHelpdeskTicket($id, $reply);
        flash($ok ? 'success' : 'error', $ok ? 'Reply sent and ticket resolved.' : 'Reply failed.');
        header('Location: ' . APP_URL . '/helpdesk.php');
        exit;
    }
}

$tickets = fetchHelpdeskTickets();

$detailId = (int) ($_GET['id'] ?? 0);
$detail = null;
foreach ($tickets as $t) {
    if ((int) $t['id'] === $detailId) {
        $detail = $t;
        break;
    }
}

$pageTitle = $isStore ? 'Store Help Desk' : 'Merchant Help Desk';
include __DIR__ . '/includes/header.php';
?>

<?php if ($isStore): ?>
<div class="card">
    <div class="card-header">
        <h2>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            Submit Support Request
        </h2>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Report order delays, app glitches, menu update assistance, or account questions here. Motobook management staff will respond on this ticket.
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select class="form-control" name="category" required>
                        <option value="order_delay">Order delay issue</option>
                        <option value="app_glitch">App glitch / technical</option>
                        <option value="menu_help">Menu update help</option>
                        <option value="account">Account / login</option>
                        <option value="other">Other inquiry</option>
                    </select>
                </div>
                <div class="form-group"><label>Short subject</label><input class="form-control" name="subject" required placeholder="Cannot update menu item…"></div>
            </div>
            <div class="form-group">
                <label>Detailed message</label>
                <textarea class="form-control" name="message" rows="4" required placeholder="What happened, when did it start, and what have you tried?"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Send help desk request</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><?= $isStore ? 'My Support Tickets' : 'All Merchant Tickets' ?></h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Ticket #</th><th>Time</th><th>Store</th><th>Category</th><th>Subject</th><th>Status</th><th>Staff reply</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><strong><?= e($t['ticket_number']) ?></strong></td>
                            <td><?= formatDateTime($t['created_at']) ?></td>
                            <td><?= e($t['store_name']) ?></td>
                            <td><span class="badge badge-info"><?= e(ucwords(str_replace('_', ' ', (string) $t['category']))) ?></span></td>
                            <td><?= e($t['subject']) ?><br><small style="color:var(--gray-500)"><?= substr(e($t['message']), 0, 80) ?>…</small></td>
                            <td><?= statusBadge((string) $t['status']) ?></td>
                            <td><?= $t['staff_reply'] ? '<span class="text-muted" title="'.e($t['staff_reply']).'">Replied</span>' : '<span class="text-muted">Pending</span>' ?></td>
                            <td>
                                <?php if (isPlatformStaff() && ($t['status'] ?? '') !== 'resolved'): ?>
                                    <a class="btn btn-outline btn-sm" href="?id=<?= (int) $t['id'] ?>#reply">Reply</a>
                                <?php elseif ($isStore): ?>
                                    <a class="btn btn-outline btn-sm" href="?id=<?= (int) $t['id'] ?>#detail">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="8" class="text-muted">No tickets yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($detail): ?>
<div class="card" id="<?= isPlatformStaff() ? 'reply' : 'detail' ?>">
    <div class="card-header"><h2>Ticket: <?= e($detail['ticket_number']) ?></h2></div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem">Store message</h3>
                <p><strong>Store:</strong> <?= e($detail['store_name']) ?></p>
                <p><strong>Category:</strong> <?= e(ucwords(str_replace('_', ' ', (string) $detail['category']))) ?></p>
                <p><strong>Subject:</strong> <?= e($detail['subject']) ?></p>
                <div class="card" style="margin-top:.5rem;border-left:4px solid var(--cyan-500)">
                    <div class="card-body"><?= nl2br(e($detail['message'])) ?></div>
                </div>
            </div>
            <div>
                <h3 style="font-size:.95rem;margin-bottom:.5rem">Staff reply</h3>
                <?php if (!empty($detail['staff_reply'])): ?>
                    <div class="card" style="border-left:4px solid var(--success)"><div class="card-body"><?= nl2br(e($detail['staff_reply'])) ?></div></div>
                <?php else: ?>
                    <p class="text-muted">No staff reply yet.</p>
                <?php endif; ?>
                <?php if (isPlatformStaff() && ($detail['status'] ?? '') !== 'resolved'): ?>
                    <form method="POST" style="margin-top:1rem">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="ticket_id" value="<?= (int) $detail['id'] ?>">
                        <div class="form-group">
                            <label>Your response (ticket will be marked resolved)</label>
                            <textarea class="form-control" name="reply" rows="4" required placeholder="Helpful step-by-step reply to the store manager…"></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Send reply & close ticket</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
