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

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-confirm-dialog]');
        if (!form) return;

        if (form.dataset.confirmedSubmit === 'true') {
            delete form.dataset.confirmedSubmit;
            return;
        }

        event.preventDefault();
        showAdminConfirmDialog(form, event.submitter);
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

function showAdminConfirmDialog(form, submitter) {
    const overlay = document.createElement('div');
    overlay.className = 'admin-confirm-overlay';
    overlay.innerHTML = `
        <section class="admin-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="adminConfirmTitle" aria-describedby="adminConfirmMessage" tabindex="-1">
            <div class="admin-confirm-icon" aria-hidden="true"><i data-lucide="badge-check"></i></div>
            <h2 id="adminConfirmTitle"></h2>
            <p id="adminConfirmMessage"></p>
            <div class="admin-confirm-actions">
                <button type="button" class="btn btn-outline" data-confirm-cancel>Cancel</button>
                <button type="button" class="btn btn-success" data-confirm-approve></button>
            </div>
        </section>`;

    overlay.querySelector('#adminConfirmTitle').textContent = form.dataset.confirmTitle || 'Please confirm';
    overlay.querySelector('#adminConfirmMessage').textContent = form.dataset.confirmMessage || 'Continue with this action?';
    overlay.querySelector('[data-confirm-approve]').textContent = form.dataset.confirmLabel || 'Confirm';
    document.body.appendChild(overlay);

    if (window.lucide) {
        window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 }, root: overlay });
    }

    const dialog = overlay.querySelector('.admin-confirm-dialog');
    const cancelButton = overlay.querySelector('[data-confirm-cancel]');
    const approveButton = overlay.querySelector('[data-confirm-approve]');
    const closeDialog = function () {
        document.removeEventListener('keydown', onKeyDown);
        overlay.remove();
        form.querySelector('button[type="submit"]')?.focus();
    };
    const onKeyDown = function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
        }
        if (event.key === 'Tab') {
            event.preventDefault();
            (document.activeElement === cancelButton ? approveButton : cancelButton).focus();
        }
    };

    cancelButton.addEventListener('click', closeDialog);
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) closeDialog();
    });
    approveButton.addEventListener('click', function () {
        closeDialog();
        form.dataset.confirmedSubmit = 'true';
        form.requestSubmit(submitter || undefined);
    });
    document.addEventListener('keydown', onKeyDown);
    dialog.focus();
    cancelButton.focus();
}
