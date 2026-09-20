<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">Ops</div>
        <div>
            <strong>Motobook</strong>
            <small><?= isStoreStaff() ? e(currentUser()['store_name'] ?? 'Store Panel') : 'Management Staff' ?></small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a class="nav-item <?= isActivePage('dashboard.php') ?>" href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <a class="nav-item <?= isActivePage('orders.php') ?>" href="<?= APP_URL ?>/orders.php">Live Orders</a>
        <?php if (isPlatformStaff()): ?>
            <a class="nav-item <?= isActivePage('remittance.php') ?>" href="<?= APP_URL ?>/remittance.php">Cash Remittance</a>
            <a class="nav-item <?= isActivePage('riders.php') ?>" href="<?= APP_URL ?>/riders.php">Rider Support</a>
            <a class="nav-item <?= isActivePage('tickets.php') ?>" href="<?= APP_URL ?>/tickets.php">Customer Tickets</a>
            <a class="nav-item <?= isActivePage('stores.php') ?>" href="<?= APP_URL ?>/stores.php">Store Support</a>
            <a class="nav-item <?= isActivePage('menu.php') ?>" href="<?= APP_URL ?>/menu.php">Menu Assist</a>
            <a class="nav-item <?= isActivePage('helpdesk.php') ?>" href="<?= APP_URL ?>/helpdesk.php">Merchant Help Desk</a>
            <a class="nav-item <?= isActivePage('banners.php') ?>" href="<?= APP_URL ?>/banners.php">Banners</a>
            <a class="nav-item <?= isActivePage('promos.php') ?>" href="<?= APP_URL ?>/promos.php">Promo Approvals</a>
            <a class="nav-item <?= isActivePage('settings.php') ?>" href="<?= APP_URL ?>/settings.php">Rules (Read-Only)</a>
        <?php else: ?>
            <a class="nav-item <?= isActivePage('menu.php') ?>" href="<?= APP_URL ?>/menu.php">Menu Availability</a>
            <a class="nav-item <?= isActivePage('helpdesk.php') ?>" href="<?= APP_URL ?>/helpdesk.php">Help Desk</a>
            <a class="nav-item <?= isActivePage('promos.php') ?>" href="<?= APP_URL ?>/promos.php">Submit Promo</a>
            <a class="nav-item <?= isActivePage('stores.php') ?>" href="<?= APP_URL ?>/stores.php">Store Status</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer"><small>Motobook Management · powered by MySQL</small></div>
</aside>
