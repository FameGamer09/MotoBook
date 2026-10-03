<aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
    <div class="sidebar-brand">
        <div class="brand-icon" aria-hidden="true">
            <i data-lucide="bike"></i>
        </div>
        <div>
            <strong>Motobook</strong>
            <small>Super Admin</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Workspace</div>
        <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= isActivePage('dashboard.php') ?>">
            <i data-lucide="layout-dashboard"></i>
            Dashboard
        </a>
        <a href="<?= APP_URL ?>/pos.php" class="nav-item <?= isActivePage('pos.php') ?>">
            <i data-lucide="calculator"></i>
            POS &amp; Remittance
        </a>
        <a href="<?= APP_URL ?>/orders.php" class="nav-item <?= isActivePage('orders.php') ?>">
            <i data-lucide="package"></i>
            Orders
        </a>
        <div class="nav-section-label">Administration</div>
        <a href="<?= APP_URL ?>/riders.php" class="nav-item <?= isActivePage('riders.php') ?>">
            <i data-lucide="bike"></i>
            Riders
        </a>
        <a href="<?= APP_URL ?>/staff.php" class="nav-item <?= isActivePage('staff.php') ?>">
            <i data-lucide="users"></i>
            Management (Staff)
        </a>
        <a href="<?= APP_URL ?>/stores.php" class="nav-item <?= isActivePage('stores.php') ?>">
            <i data-lucide="store"></i>
            Partnership Stores
        </a>
        <a href="<?= APP_URL ?>/settings.php" class="nav-item <?= isActivePage('settings.php') ?>">
            <i data-lucide="settings"></i>
            Global Settings
        </a>
    </nav>
    <div class="sidebar-footer">
        <small>API v1 &middot; <a href="/IM-101/motobook/A-management/login.php" style="color:#67e8f9">Management panel</a></small>
    </div>
</aside>
