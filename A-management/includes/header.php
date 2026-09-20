<?php

declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? 'Operations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= pageTitle($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar"><span></span><span></span><span></span></button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <p class="subtitle"><?= e(currentUserStoreId() ? 'Store operations · ' . (currentUser()['store_name'] ?? '') : 'Motobook operations center') ?></p>
            </div>
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
