<?php

declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? 'Operations';

// Determine mode: explicit $pageMode variable, or infer from current script name
if (empty($pageMode)) {
    $script = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
    $dispatchPages = ['orders.php', 'riders.php', 'remittance.php', 'pos.php', 'dashboard.php'];
    $pageMode = in_array($script, $dispatchPages, true) ? 'dispatch' : 'store';
}
$isPlatform = isPlatformStaff();
$isStoreOnly = isStoreStaff();
if ($isStoreOnly && $pageMode !== 'store') {
    flash('error', 'Store accounts are restricted to Store Workspace.');
    redirectOps('/menu.php');
}
$currentScript = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <meta name="color-scheme" content="light">
    <title><?= pageTitle($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= gmdate('Ymd-Hi') ?>">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <linearGradient id="half-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="50%" stop-color="currentColor"/>
                <stop offset="50%" stop-color="transparent" stop-opacity="0"/>
            </linearGradient>
        </defs>
    </svg>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) {
                window.lucide.createIcons({
                    attrs: {
                        'stroke-width': 1.75,
                        class: 'inline-block shrink-0',
                    },
                });
            }
        });
    </script>
</head>
<body class="app-mode-<?= $pageMode === 'dispatch' ? 'dispatch' : 'store' ?>">
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <p class="subtitle"><?= e(currentUserStoreId() ? 'Store Operations · ' . (currentUser()['store_name'] ?? '') : 'Operations Center') ?></p>
            </div>

            <?php if (!isset($hideModeSwitcher) || !$hideModeSwitcher): ?>
            <nav class="mode-switcher" aria-label="Workspace mode">
                <?php if ($isPlatform): ?>
                    <a class="mode-tab<?= $pageMode === 'dispatch' ? ' active' : '' ?>"
                       data-mode="dispatch"
                       data-url="<?= APP_URL ?>/orders.php?tab=kanban"
                       href="<?= APP_URL ?>/orders.php?tab=kanban"
                       aria-current="<?= $pageMode === 'dispatch' ? 'page' : 'false' ?>">
                        <i data-lucide="truck"></i>
                        Dispatch
                    </a>
                <?php endif; ?>
                    <a class="mode-tab<?= $pageMode === 'store' ? ' active' : '' ?>"
                       data-mode="store"
                       data-url="<?= APP_URL ?>/menu.php"
                       href="<?= APP_URL ?>/menu.php"
                       aria-current="<?= $pageMode === 'store' ? 'page' : 'false' ?>">
                        <i data-lucide="store"></i>
                        <?= $isPlatform ? 'Store Manager' : 'Store Workspace' ?>
                    </a>
            </nav>
            <?php endif; ?>

            <div class="topbar-user">
                <div class="user-info">
                    <strong><?= e($user['name'] ?? 'Staff') ?></strong>
                    <small><?= e(userTypeLabel()) ?><?= !empty($user['store_name']) ? ' · ' . e($user['store_name']) : '' ?></small>
                </div>
                <div class="avatar cyan" title="<?= e($user['name'] ?? 'Staff') ?>" aria-hidden="true"><?= strtoupper(substr($user['name'] ?? 'S', 0, 1)) ?></div>
                <a class="btn btn-outline btn-sm" href="<?= ADMIN_URL ?>/logout.php" title="Sign out" aria-label="Sign out">
                    <i data-lucide="log-out"></i>
                    Sign Out
                </a>
            </div>
        </header>
        <main class="page-content">
            <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success" role="status">
                    <i data-lucide="check-circle-2"></i>
                    <?= e($msg) ?>
                </div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-error" role="alert">
                    <i data-lucide="alert-circle"></i>
                    <?= e($msg) ?>
                </div>
            <?php endif; ?>
