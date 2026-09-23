<?php
$siteName = get_setting($pdo, 'site_name', 'Daily Dish Restaurant');
$rc       = get_setting($pdo, 'rc_number', '');
$address  = get_setting($pdo, 'address', '');
$phone1   = get_setting($pdo, 'phone_primary', '');
$phone2   = get_setting($pdo, 'phone_secondary', '');
$hours    = get_setting($pdo, 'hours', '');
$whatsapp = get_setting($pdo, 'whatsapp', '');
$ig       = get_setting($pdo, 'instagram_url', '');
$fb       = get_setting($pdo, 'facebook_url', '');
$cartCount = cart_count();
?>
</main>

<?php if (empty($hideCartBar)): ?>
<a href="<?= e(base_url('cart.php')) ?>" class="cart-bar" data-cart-bar <?= $cartCount ? '' : 'hidden' ?>>
  <span class="cart-bar-count"><span data-cart-count><?= (int)$cartCount ?></span> in your order</span>
  <span class="cart-bar-total" data-cart-subtotal><?= money($cartCount ? cart_subtotal($pdo) : 0, $pdo) ?></span>
  <span class="cart-bar-go">View order</span>
</a>
<?php endif; ?>

<div class="toast" role="status" aria-live="polite" data-toast hidden></div>

<footer class="footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <span class="brand-word">Daily <b>Dish</b></span>
      <p>Nigerian home-style cooking and continental favourites from our kitchen in Gwarinpa, delivered hot across Abuja.</p>
      <?php if ($rc): ?><p class="footer-rc"><?= e($rc) ?></p><?php endif; ?>
    </div>
    <div>
      <h4>Visit</h4>
      <p><?= e($address) ?></p>
      <p><?= e($hours) ?></p>
    </div>
    <div>
      <h4>Order by phone</h4>
      <p><a href="tel:<?= e(tel($phone1)) ?>"><?= e($phone1) ?></a><?php if ($phone2): ?><br><a href="tel:<?= e(tel($phone2)) ?>"><?= e($phone2) ?></a><?php endif; ?></p>
      <?php if ($whatsapp): ?><p><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp the kitchen</a></p><?php endif; ?>
    </div>
    <div>
      <h4>Quick links</h4>
      <p><a href="<?= e(base_url('menu.php')) ?>">Full menu</a><br>
      <a href="<?= e(base_url('track.php')) ?>">Track an order</a><br>
      <?php if ($ig): ?><a href="<?= e($ig) ?>" target="_blank" rel="noopener">Instagram</a><br><?php endif; ?>
      <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?></p>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <span>© <?= date('Y') ?> <?= e($siteName) ?></span>
    <a href="https://digitalweboracleict.com" target="_blank" rel="noopener">Powered by DWO</a>
  </div>
</footer>

<script src="<?= e(base_url('assets/js/app.js')) ?>?v=3" defer></script>
</body>
</html>
