<?php
/** Expects $pdo, optionally $active (nav key), $pageTitle, $hideCartBar. */
$siteName = get_setting($pdo, 'site_name', 'Daily Dish Restaurant');
$tagline  = get_setting($pdo, 'tagline', 'Your Everyday Delicacy');
$phone    = get_setting($pdo, 'phone_primary', '');
$hours    = get_setting($pdo, 'hours', '');
$cartCount = cart_count();
$cartSubtotal = $cartCount ? cart_subtotal($pdo) : 0;
$act = $active ?? '';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle ?? $siteName) ?><?= isset($pageTitle) ? ' | ' . e($siteName) : ' | ' . e($tagline) ?></title>
  <meta name="description" content="<?= e($siteName) ?>, Gwarinpa, Abuja. Nigerian soups and swallow, party jollof, grills, pasta and more. Order online for delivery or pickup.">
  <meta name="theme-color" content="#2A1810">
  <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&family=Courier+Prime:wght@400;700&display=swap&family=Bricolage+Grotesque&display=swap&family=Instrument+Sans&display=swap&family=Courier+Prime&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=8">
</head>
<body class="page-<?= e($act ?? 'home') ?>" data-base="<?= e(base_url('')) ?>" data-csrf="<?= e(csrf_token()) ?>">
<a class="skip" href="#main">Skip to content</a>

<header class="topbar">
  <div class="wrap topbar-in">
    <a href="<?= e(base_url('index.php')) ?>" class="brand" aria-label="<?= e($siteName) ?> home">
      <span class="brand-mark"><?php include __DIR__ . '/logo-mark.php'; ?></span>
      <span class="brand-word">Daily <b>Dish</b></span>
    </a>

    <nav class="nav" id="mainNav" aria-label="Main">
      <a href="<?= e(base_url('menu.php')) ?>" class="<?= $act === 'menu' ? 'on' : '' ?>">Menu</a>
      <a href="<?= e(base_url('track.php')) ?>" class="<?= $act === 'track' ? 'on' : '' ?>">Track order</a>
      <a href="<?= e(base_url('about.php')) ?>" class="<?= $act === 'about' ? 'on' : '' ?>">About</a>
      <a href="<?= e(base_url('contact.php')) ?>" class="<?= $act === 'contact' ? 'on' : '' ?>">Find us</a>
      <a href="tel:<?= e(tel($phone)) ?>" class="nav-call">Call <?= e($phone) ?></a>
    </nav>

    <div class="topbar-actions">
      <?= game_topbar_markup($pdo) ?>
      <a href="<?= e(base_url('cart.php')) ?>" class="cart-pill <?= $act === 'cart' ? 'on' : '' ?>" aria-label="Your order">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
        <span class="cart-pill-label">Your order</span>
        <span class="cart-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= (int)$cartCount ?></span>
      </a>
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav">
        <span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main id="main">
