<aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
    <div class="sidebar-brand">
        <div class="brand-icon" aria-hidden="true">
            <i data-lucide="bike"></i>
        </div>
        <div>
            <strong>Motobook</strong>
            <small><?= isStoreStaff() ? e(currentUser()['store_name'] ?? 'Store Panel') : 'Management Staff' ?></small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Workspace</div>
        <a class="nav-item <?= isActivePage('dashboard.php') ?>" href="<?= APP_URL ?>/dashboard.php">
            <i data-lucide="layout-dashboard"></i>
            Dashboard
        </a>
        <a class="nav-item <?= isActivePage('orders.php') ?>" href="<?= APP_URL ?>/orders.php">
            <i data-lucide="clipboard-list"></i>
            Live Orders
        </a>
        <?php if (isPlatformStaff()): ?>
            <div class="nav-section-label">Platform Operations</div>
            <a class="nav-item <?= isActivePage('remittance.php') ?>" href="<?= APP_URL ?>/remittance.php">
                <i data-lucide="banknote"></i>
                Cash Remittance
            </a>
            <a class="nav-item <?= isActivePage('riders.php') ?>" href="<?= APP_URL ?>/riders.php">
                <i data-lucide="users-round"></i>
                Rider Support
            </a>
            <a class="nav-item <?= isActivePage('tickets.php') ?>" href="<?= APP_URL ?>/tickets.php">
                <i data-lucide="message-circle-question"></i>
                Customer Tickets
            </a>
            <a class="nav-item <?= isActivePage('stores.php') ?>" href="<?= APP_URL ?>/stores.php">
                <i data-lucide="store"></i>
                Store Support
            </a>
            <a class="nav-item <?= isActivePage('menu.php') ?>" href="<?= APP_URL ?>/menu.php">
                <i data-lucide="utensils-crossed"></i>
                Menu Assist
            </a>
            <a class="nav-item <?= isActivePage('helpdesk.php') ?>" href="<?= APP_URL ?>/helpdesk.php">
                <i data-lucide="headphones"></i>
                Merchant Help Desk
            </a>
            <a class="nav-item <?= isActivePage('banners.php') ?>" href="<?= APP_URL ?>/banners.php">
                <i data-lucide="billboard"></i>
                Banners
            </a>
            <a class="nav-item <?= isActivePage('promos.php') ?>" href="<?= APP_URL ?>/promos.php">
                <i data-lucide="tag-percent"></i>
                Promo Approvals
            </a>
            <a class="nav-item <?= isActivePage('settings.php') ?>" href="<?= APP_URL ?>/settings.php">
                <i data-lucide="sliders-horizontal"></i>
                Rules (Read-Only)
            </a>
        <?php else: ?>
            <div class="nav-section-label">Store</div>
            <a class="nav-item <?= isActivePage('menu.php') ?>" href="<?= APP_URL ?>/menu.php">
                <i data-lucide="warehouse"></i>
                Menu Availability
            </a>
            <a class="nav-item <?= isActivePage('helpdesk.php') ?>" href="<?= APP_URL ?>/helpdesk.php">
                <i data-lucide="headphones"></i>
                Help Desk
            </a>
            <a class="nav-item <?= isActivePage('promos.php') ?>" href="<?= APP_URL ?>/promos.php">
                <i data-lucide="megaphone"></i>
                Submit Promo
            </a>
            <a class="nav-item <?= isActivePage('stores.php') ?>" href="<?= APP_URL ?>/stores.php">
                <i data-lucide="store"></i>
                Store Status
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer"><small>Motobook Management &middot; powered by MySQL</small></div>
</aside>
