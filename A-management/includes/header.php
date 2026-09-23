<?php

declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? 'Operations';

// Determine mode: explicit $pageMode variable, or infer from current script name
if (empty($pageMode)) {
    $script = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
    $dispatchPages = ['orders.php', 'riders.php', 'remittance.php', 'pos.php'];
    $pageMode = in_array($script, $dispatchPages, true) ? 'dispatch' : 'store';
}
$isPlatform = isPlatformStaff();
$currentScript = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <title><?= pageTitle($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="app-mode-<?= $pageMode === 'dispatch' ? 'dispatch' : 'store' ?>">
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar"><span></span><span></span><span></span></button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <p class="subtitle"><?= e(currentUserStoreId() ? 'Store operations · ' . (currentUser()['store_name'] ?? '') : 'Motobook operations center') ?></p>
            </div>

            <?php if (!isset($hideModeSwitcher) || !$hideModeSwitcher): ?>
            <nav class="mode-switcher" aria-label="Workspace mode">
                <?php if ($isPlatform): ?>
                    <a class="mode-tab<?= $pageMode === 'dispatch' ? ' active' : '' ?>"
                       data-mode="dispatch"
                       data-url="<?= APP_URL ?>/orders.php?tab=kanban"
                       href="<?= APP_URL ?>/orders.php?tab=kanban">
                        <span class="mode-icon">🛵</span>Dispatch &amp; Rider View
                    </a>
                <?php endif; ?>
                    <a class="mode-tab<?= $pageMode === 'store' ? ' active' : '' ?>"
                       data-mode="store"
                       data-url="<?= APP_URL ?>/menu.php"
                       href="<?= APP_URL ?>/menu.php">
                        <span class="mode-icon">🍽️</span><?= $isPlatform ? 'Store Menu Manager' : 'Store Workspace' ?>
                    </a>
            </nav>
            <?php endif; ?>

            <div class="topbar-user">
                <div class="user-info">
                    <strong><?= e($user['name'] ?? 'Staff') ?></strong>
                    <small><?= e(userTypeLabel()) ?><?= !empty($user['store_name']) ? ' · ' . e($user['store_name']) : '' ?></small>
                </div>
                <a class="btn btn-outline btn-sm" href="<?= APP_URL ?>/logout.php">Logout</a>
            </div>
        </header>
        <main class="page-content">
            <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
            <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
