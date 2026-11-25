const themeRoots = document.querySelectorAll('[data-rider-theme-root]');
const themeStorageKey = 'rider-dashboard-theme';

themeRoots.forEach((root) => {
    const themeToggles = root.querySelectorAll('[data-rider-theme-toggle]');
    const savedTheme = window.localStorage.getItem(themeStorageKey);

    if (savedTheme === 'light' || savedTheme === 'dark') {
        root.dataset.riderTheme = savedTheme;
    }

    const updateToggle = () => {
        const isDark = root.dataset.riderTheme === 'dark';

        themeToggles.forEach((toggle) => {
            toggle.setAttribute('aria-pressed', String(isDark));
            toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            const icon = toggle.querySelector('span');

            if (icon) {
                icon.textContent = isDark ? '☼' : '☾';
            }
        });

        const themeColor = document.querySelector('meta[name="theme-color"]');

        themeColor?.setAttribute('content', isDark ? '#0d1318' : '#f4f7f8');

        root.querySelectorAll('[data-rider-theme-label]').forEach((label) => {
            label.textContent = isDark ? 'Dark' : 'Light';
        });
    };

    updateToggle();
    themeToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            root.dataset.riderTheme = root.dataset.riderTheme === 'dark' ? 'light' : 'dark';
            window.localStorage.setItem(themeStorageKey, root.dataset.riderTheme);
            updateToggle();
        });
    });

    root.querySelectorAll('[data-rider-notification-toggle]').forEach((button) => {
        const updateNotificationButton = () => {
            if (!('Notification' in window)) {
                button.textContent = 'Notifications unavailable';
                button.setAttribute('aria-pressed', 'false');
                button.disabled = true;

                return;
            }

            const permission = window.Notification.permission;
            button.textContent = permission === 'granted'
                ? 'Alerts enabled'
                : permission === 'denied'
                    ? 'Blocked in browser'
                    : 'Enable alerts';
            button.setAttribute('aria-pressed', String(permission === 'granted'));
            button.disabled = permission !== 'default';
        };

        updateNotificationButton();
        button.addEventListener('click', () => {
            if (!('Notification' in window) || window.Notification.permission !== 'default') {
                updateNotificationButton();

                return;
            }

            window.Notification.requestPermission()
                .then(updateNotificationButton)
                .catch(() => {
                    button.textContent = 'Unable to enable alerts';
                    button.setAttribute('aria-pressed', 'false');
                });
        });
    });
});
