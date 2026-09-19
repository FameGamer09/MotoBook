<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDBConnection();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

$avgRating = $pdo->query('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total FROM customer_reviews')->fetch();
$flaggedCount = $pdo->query('SELECT COUNT(*) AS cnt FROM customer_reviews WHERE is_flagged = 1')->fetch()['cnt'];
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="stats-grid" id="analyticsCounters">
    <div class="stat-card">
        <div class="label"><span class="live-dot"></span>Total Revenue (Today)</div>
        <div class="value" id="revenueToday">—</div>
        <div class="sub">Weekly: <span id="revenueWeek">—</span></div>
    </div>
    <div class="stat-card">
        <div class="label">Monthly Revenue</div>
        <div class="value" id="revenueMonth">—</div>
        <div class="sub">Lifetime: <span id="revenueLifetime">—</span></div>
    </div>
    <div class="stat-card">
        <div class="label">Active Riders</div>
        <div class="value" id="activeRiders">—</div>
        <div class="sub"><span id="ridersOnTrip">—</span> on trip · <span id="ridersIdle">—</span> idle</div>
    </div>
    <div class="stat-card">
        <div class="label">Active Staff</div>
        <div class="value" id="activeStaff">—</div>
        <div class="sub">Currently on shift</div>
    </div>
    <div class="stat-card">
        <div class="label">Partnership Stores Open</div>
        <div class="value" id="activeStores">—</div>
        <div class="sub">Operating on network</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Platform Rider Satisfaction</h2>
            <?php if ($flaggedCount > 0): ?>
                <span class="badge badge-danger"><?= $flaggedCount ?> flagged</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="rating-summary">
                <div class="big-rating"><?= number_format((float) ($avgRating['avg_rating'] ?? 0), 1) ?> / 5.0</div>
                <?= renderStars((float) ($avgRating['avg_rating'] ?? 0)) ?>
                <p>Based on <?= (int) ($avgRating['total'] ?? 0) ?> customer reviews platform-wide</p>
            </div>
            <h3 style="font-size:0.9rem;color:var(--cyan-800);margin-bottom:0.75rem;">Recent Feedback Feed</h3>
            <div id="reviewFeed">
                <?php
                $reviews = $pdo->query('
                    SELECT cr.*, r.full_name AS rider_name, ps.store_name
                    FROM customer_reviews cr
                    LEFT JOIN riders r ON r.id = cr.rider_id
                    LEFT JOIN partnership_stores ps ON ps.id = cr.store_id
                    ORDER BY cr.created_at DESC LIMIT 8
                ')->fetchAll();

foreach ($reviews as $review): ?>
                <div class="review-item <?= $review['is_flagged'] ? 'flagged' : '' ?>">
                    <div class="review-meta">
                        <div>
                            <strong><?= e($review['customer_name']) ?></strong>
                            <?= renderStars((float) $review['rating']) ?>
                            <?php if ($review['is_flagged']): ?>
                                <span class="badge badge-danger">Needs Intervention</span>
                            <?php endif; ?>
                        </div>
                        <small><?= formatDateTime($review['created_at']) ?></small>
                    </div>
                    <p><?= e($review['comment']) ?></p>
                    <small class="text-muted">Rider: <?= e($review['rider_name'] ?? 'N/A') ?> · Store: <?= e($review['store_name'] ?? 'N/A') ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Operational Overview</h2></div>
        <div class="card-body">
            <h3 style="font-size:0.85rem;color:var(--gray-500);margin-bottom:0.5rem;">Order Volume (Last 7 Days)</h3>
            <div class="chart-container"><canvas id="orderVolumeChart"></canvas></div>
            <h3 style="font-size:0.85rem;color:var(--gray-500);margin:1.5rem 0 0.5rem;">Peak Delivery Hours</h3>
            <div class="chart-container"><canvas id="peakHoursChart"></canvas></div>
            <h3 style="font-size:0.85rem;color:var(--gray-500);margin:1.5rem 0 0.5rem;">Daily Sales Performance</h3>
            <div class="chart-container"><canvas id="salesChart"></canvas></div>
        </div>
    </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script>
function refreshAnalytics() {
    fetch(window.APP_URL + '/api/analytics.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            document.getElementById('revenueToday').textContent = d.revenue.today;
            document.getElementById('revenueWeek').textContent = d.revenue.week;
            document.getElementById('revenueMonth').textContent = d.revenue.month;
            document.getElementById('revenueLifetime').textContent = d.revenue.lifetime;
            document.getElementById('activeRiders').textContent = d.riders.active;
            document.getElementById('ridersOnTrip').textContent = d.riders.on_trip;
            document.getElementById('ridersIdle').textContent = d.riders.idle;
            document.getElementById('activeStaff').textContent = d.staff.active;
            document.getElementById('activeStores').textContent = d.stores.active;
        });
}

function initDashboardCharts() {
    fetch(window.APP_URL + '/api/charts.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            const cyan = '#0891b2';
            const cyanLight = '#67e8f9';

            new Chart(document.getElementById('orderVolumeChart'), {
                type: 'line',
                data: {
                    labels: d.order_volume.labels,
                    datasets: [{
                        label: 'Orders',
                        data: d.order_volume.data,
                        borderColor: cyan,
                        backgroundColor: 'rgba(8,145,178,0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });

            new Chart(document.getElementById('peakHoursChart'), {
                type: 'bar',
                data: {
                    labels: d.peak_hours.labels,
                    datasets: [{
                        label: 'Deliveries',
                        data: d.peak_hours.data,
                        backgroundColor: cyanLight,
                        borderColor: cyan,
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });

            new Chart(document.getElementById('salesChart'), {
                type: 'bar',
                data: {
                    labels: d.daily_sales.labels,
                    datasets: [{
                        label: 'Sales (₱)',
                        data: d.daily_sales.data,
                        backgroundColor: cyan,
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
