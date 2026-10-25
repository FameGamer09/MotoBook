<?php

declare(strict_types=1);

require_once __DIR__.'/includes/functions.php';
requirePlatform();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    if (($_POST['action'] ?? '') === 'log_incident') {
        $riderId = (int) $_POST['rider_id'];
        $type = $_POST['incident_type'] ?? 'other';
        $notes = trim($_POST['notes'] ?? '');
        $ok = logRiderIncident($riderId, $type, $notes);
        flash($ok ? 'success' : 'error', $ok ? 'Incident logged successfully.' : 'Failed to log incident.');
        header('Location: '.APP_URL.'/riders.php');
        exit;
    }
}

$riders = fetchActiveRiders();
$incidents = fetchRiderIncidents();
$shifts = fetchTodayShifts();
ensureRiderSupportRequestTable(getOpsDB());
$supportRequests = getOpsDB()->query(
    'SELECT requests.id, requests.category, requests.subject, requests.message, requests.status, requests.staff_reply,
            requests.created_at, riders.full_name AS rider_name, riders.rider_code
     FROM rider_support_requests AS requests
     INNER JOIN riders ON riders.id = requests.rider_id
     ORDER BY CASE requests.status WHEN \'open\' THEN 0 WHEN \'in_progress\' THEN 1 ELSE 2 END, requests.created_at DESC
     LIMIT 100'
)->fetchAll();

$shiftsByRider = [];
foreach ($shifts as $s) {
    $shiftsByRider[(int) $s['rider_id']] = $s;
}

$pageTitle = 'Rider Support & Shift Tracker';
include __DIR__.'/includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Rider Shift Status (Today)</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Rider</th><th>Code</th><th>Duty</th><th>Shift</th><th>Plate</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riders as $r) {
                            $shift = $shiftsByRider[(int) $r['id']] ?? null; ?>
                            <tr>
                                <td><strong><?= e($r['full_name']) ?></strong><br><small><?= e($r['phone']) ?></small></td>
                                <td><?= e($r['rider_code']) ?></td>
                                <td><?= statusBadge((string) $r['duty_status']) ?></td>
                                <td><?= $shift ? statusBadge((string) $shift['shift_status']) : '<span class="badge badge-muted">No shift</span>' ?></td>
                                <td><?= e($r['vehicle_plate'] ?? '—') ?></td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($riders)) { ?>
                            <tr><td colspan="5" class="text-muted">No active riders on duty.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Report Rider Incident</h2></div>
        <div class="card-body">
            <div class="alert alert-info">
                Record late arrivals, broken bags, vehicle trouble, completed safety briefings, or other issues.
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="log_incident">
                <div class="form-group">
                    <label>Rider</label>
                    <select class="form-control" name="rider_id" required>
                        <option value="">Select rider…</option>
                        <?php foreach ($riders as $r) { ?>
                            <option value="<?= (int) $r['id'] ?>"><?= e($r['full_name']) ?> (<?= e($r['rider_code']) ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Incident type</label>
                    <select class="form-control" name="incident_type" required>
                        <option value="late_arrival">Late arrival</option>
                        <option value="broken_bag">Broken bag / equipment</option>
                        <option value="vehicle_issue">Vehicle trouble / flat tire</option>
                        <option value="safety_briefing">Safety briefing completed</option>
                        <option value="other">Other note</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-control" name="notes" rows="3" required placeholder="Describe the incident, follow-up actions, or completion details…"></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Save incident log</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2>Rider Support Inbox</h2>
            <small class="text-muted">Messages sent from the Rider app. Newest open requests appear first.</small>
        </div>
        <span class="badge badge-info"><?= count(array_filter($supportRequests, static fn (array $request): bool => $request['status'] === 'open')) ?> open</span>
    </div>
    <div class="card-body">
        <?php if ($supportRequests) { ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Rider</th><th>Subject</th><th>Category</th><th>Message</th><th>Status</th><th>Received</th></tr></thead>
                    <tbody>
                        <?php foreach ($supportRequests as $request) { ?>
                            <tr>
                                <td><strong><?= e($request['rider_name']) ?></strong><br><small><?= e($request['rider_code']) ?></small></td>
                                <td><?= e($request['subject']) ?></td>
                                <td><?= e($request['category']) ?></td>
                                <td style="min-width:260px;white-space:pre-wrap;"><?= e($request['message']) ?><?php if (! empty($request['staff_reply'])) { ?><div class="text-muted" style="margin-top:.5rem;"><strong>Staff reply:</strong> <?= e($request['staff_reply']) ?></div><?php } ?></td>
                                <td><?= statusBadge((string) $request['status']) ?></td>
                                <td><?= formatDateTime($request['created_at']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted" style="margin:0;">No rider support messages yet.</p>
        <?php } ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Rider Incident Log</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead><tr><th>Time</th><th>Rider</th><th>Type</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php foreach ($incidents as $i) { ?>
                        <tr>
                            <td><?= formatDateTime($i['created_at']) ?></td>
                            <td><strong><?= e($i['rider_name']) ?></strong></td>
                            <td><?= statusBadge((string) str_replace('_', ' ', $i['incident_type'])) ?></td>
                            <td><?= e($i['notes']) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($incidents)) { ?>
                        <tr><td colspan="4" class="text-muted">No incidents logged today.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
