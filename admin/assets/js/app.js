document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 992 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    document.querySelectorAll('[data-modal]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });

    document.querySelectorAll('.modal-close, [data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const container = this.closest('.modal-body') || this.closest('.card-body');
            if (!container) return;

            const tabId = this.getAttribute('data-tab');
            container.querySelectorAll('.tab-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            container.querySelectorAll('.tab-panel').forEach(function (p) {
                p.classList.remove('active');
            });

            this.classList.add('active');
            const panel = container.querySelector('#' + tabId);
            if (panel) {
                panel.classList.add('active');
            }
        });
    });

    if (typeof initDashboardCharts === 'function') {
        initDashboardCharts();
    }

    if (typeof refreshAnalytics === 'function') {
        refreshAnalytics();
        setInterval(refreshAnalytics, 30000);
    }
});

function openRiderModal(riderId) {
    fetch(window.APP_URL + '/api/rider_detail.php?id=' + riderId)
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) return;

            const rider = data.rider || {};
            document.getElementById('riderModalTitle').textContent = data.title || rider.full_name || 'Rider Profile';
            document.getElementById('riderOverview').innerHTML = data.overview_html || '';
            document.getElementById('riderReviews').innerHTML = data.reviews_html || '<p class="text-muted">Reviews panel not available for new riders.</p>';
            document.getElementById('riderEarnings').innerHTML = data.earnings_html || '<p class="text-muted">Earnings panel not available for new riders.</p>';
            document.getElementById('riderModal').classList.add('active');

            document.querySelectorAll('#riderModal .tab-btn').forEach(function (b, i) {
                b.classList.toggle('active', i === 0);
            });
            document.querySelectorAll('#riderModal .tab-panel').forEach(function (p, i) {
                p.classList.toggle('active', i === 0);
            });
        });
}

function openStoreModal(storeId) {
    fetch(window.APP_URL + '/api/store_detail.php?id=' + storeId)
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) return;

            document.getElementById('storeModalTitle').textContent = data.store.store_name;
            document.getElementById('storeDetailContent').innerHTML = data.detail_html;
            document.getElementById('storeModal').classList.add('active');
        });
}

function confirmAction(message) {
    return confirm(message);
}
