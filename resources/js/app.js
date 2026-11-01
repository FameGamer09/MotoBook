import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('dashboardNotifications', (url) => ({
    url,
    open: false,
    loading: false,
    loaded: false,
    loadError: false,
    lowStockCount: 0,
    notifications: [],

    async load() {
        if (this.loading || this.loaded) return;

        this.loading = true;
        this.loadError = false;

        try {
            const response = await fetch(this.url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) throw new Error('Notifications request failed.');

            const payload = await response.json();
            this.lowStockCount = payload.low_stock_count ?? 0;
            this.notifications = payload.notifications ?? [];
            this.loaded = true;
        } catch {
            this.loadError = true;
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('global-search-dialog');
    const form = dialog?.querySelector('[data-global-search-form]');
    const target = dialog?.querySelector('[data-global-search-target]');

    document.querySelector('[data-open-global-search]')?.addEventListener('click', () => {
        dialog?.showModal();
        dialog?.querySelector('[data-global-search-input]')?.focus();
    });
    dialog?.querySelector('[data-close-global-search]')?.addEventListener('click', () => dialog.close());
    target?.addEventListener('change', () => { form.action = target.value; });
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});
