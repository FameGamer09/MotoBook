<?php

declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activePage */
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard.php';
$adminName = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= pageTitle($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <p class="subtitle">Motobook Platform Control Center</p>
            </div>
            <div class="topbar-user">
                <div class="user-info">
                    <strong><?= e($adminName) ?></strong>
                    <small>Super Administrator</small>
                </div>
                <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </header>
        <main class="page-content">
