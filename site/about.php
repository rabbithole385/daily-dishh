<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'About';
$active = 'about';
$rc = get_setting($pdo, 'rc_number');
include __DIR__ . '/includes/header.php';
?>

<div class="page-head"><div class="wrap"><h1>Cooked like home, every day</h1></div></div>

<section class="section-tight">
  <div class="wrap split">
    <div class="split-photo"><img src="<?= e(site_photo($pdo, 'about', 'swallow-soup-plain.jpg')) ?>" alt="Egusi soup and swallow from Daily Dish"></div>
    <div>
      <h2>A neighbourhood kitchen in Gwarinpa</h2>
      <p class="lede">Daily Dish sits in Shop 4, RVS Mall on Third Avenue. We cook Nigerian soups and swallow, party rice, grills, seafood, pasta and pizza, all to order.</p>
      <p>Good food should not be an occasion. Whether it is one plate of egusi for lunch or fifty trays of jollof for an office party, every order is cooked fresh, packed to travel and sent out on time.</p>
      <?php if ($rc): ?><p style="color:var(--ink-soft);font-size:.9rem;"><?= e($rc) ?></p><?php endif; ?>
      <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-primary">See the menu</a>
    </div>
  </div>
</section>

<section class="section section-cocoa">
  <div class="wrap">
    <div class="values">
      <div><h3>Cooked when you order</h3><p>Nothing sits under a heat lamp. Your order goes into the pot once we confirm it.</p></div>
      <div><h3>Market-fresh</h3><p>Vegetables, peppers and proteins bought fresh, not frozen for weeks.</p></div>
      <div><h3>Packed to travel</h3><p>Sealed containers so your soup arrives hot and your rice arrives fluffy.</p></div>
      <div><h3>Party sizes</h3><p>Trays and buckets for events, offices and family gatherings.</p></div>
    </div>
  </div>
</section>


<?php include __DIR__ . '/includes/footer.php'; ?>
