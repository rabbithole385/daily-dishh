<?php
/** Expects $pdo, $adminActive, $adminTitle */
$siteName = get_setting($pdo, 'site_name', 'Daily Dish Restaurant');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($adminTitle ?? 'Dashboard') ?> — Admin — <?= e($siteName) ?></title>
  <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <?php include __DIR__ . '/../../includes/logo-mark.php'; ?>
      <span>Daily Dish<br>Admin</span>
    </div>
    <?php if (!empty($_SESSION['admin_username'])): ?>
      <div class="admin-user">Signed in as <?= e($_SESSION['admin_username']) ?></div>
    <?php endif; ?>
    <nav class="admin-nav">
      <a href="<?= e(base_url('admin/index.php')) ?>" class="<?= ($adminActive ?? '') === 'dashboard' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
        Dashboard
      </a>
      <a href="<?= e(base_url('admin/orders.php')) ?>" class="<?= ($adminActive ?? '') === 'orders' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
        Orders<?php $pc = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(); if ($pc): ?> <span class="nav-badge"><?= $pc ?></span><?php endif; ?>
      </a>
      <a href="<?= e(base_url('admin/menu-items.php')) ?>" class="<?= ($adminActive ?? '') === 'menu-items' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3z"></path><path d="M3 9h18M9 21V9"></path></svg>
        Menu Items
      </a>
      <a href="<?= e(base_url('admin/categories.php')) ?>" class="<?= ($adminActive ?? '') === 'categories' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
        Categories
      </a>
      <a href="<?= e(base_url('admin/photos.php')) ?>" class="<?= ($adminActive ?? '') === 'photos' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
        Site Photos
      </a>
      <a href="<?= e(base_url('admin/settings.php')) ?>" class="<?= ($adminActive ?? '') === 'settings' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        Settings
      </a>
      <a href="<?= e(base_url('index.php')) ?>" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
        View Site
      </a>
    </nav>
    <div class="admin-sidebar-footer">
      <form method="post" action="<?= e(base_url('admin/logout.php')) ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit">Log Out</button>
      </form>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1><?= e($adminTitle ?? 'Dashboard') ?></h1>
    </div>
    <div class="admin-content">
