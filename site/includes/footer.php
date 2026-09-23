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

<dialog class="reward-dialog" id="spinResult">
  <div class="rd-hero"><span class="rd-emoji" data-rd-emoji>🎉</span></div>
  <div class="rd-body">
    <h2 data-rd-title>You won!</h2>
    <p data-rd-body></p>
    <div class="rd-stats">
      <div class="rc-stat"><div class="rc-stat-num"><span data-rd-points>0</span></div><div class="rc-stat-label">Points</div></div>
      <div class="rc-stat"><div class="rc-stat-num"><span data-rd-xp>0</span></div><div class="rc-stat-label">XP</div></div>
      <div class="rc-stat"><div class="rc-stat-num"><span data-rd-streak>0</span><small>🔥</small></div><div class="rc-stat-label">Streak</div></div>
    </div>
    <button class="btn btn-gold btn-block rd-close" data-rd-close>Awesome!</button>
  </div>
</dialog>

<?php $wa = get_setting($pdo, 'whatsapp', ''); ?>
<?php if ($wa): ?>
<a class="wa-fab" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 32 32" width="26" height="26" aria-hidden="true">
    <path fill="#fff" d="M16.003 3C9.373 3 4 8.372 4 15.003c0 2.651.866 5.09 2.35 7.074L4.6 29l7.092-1.674a11.953 11.953 0 0 0 4.312.803c6.63 0 12.003-5.372 12.003-12.003S22.633 3 16.003 3zm6.686 17.258c-.276.776-1.605 1.488-2.247 1.58-.573.083-1.302.11-2.106-.12-.48-.138-1.096-.362-1.884-.71-3.307-1.462-5.462-4.857-5.626-5.077-.164-.22-1.335-1.778-1.335-3.387 0-1.609.848-2.398 1.149-2.727.301-.33.657-.412.876-.412.22 0 .439.002.631.013.199.012.467-.076.731.566.27.66.919 2.283 1.001 2.447.082.165.137.358.028.578-.11.22-.164.358-.33.55-.164.192-.348.426-.496.575-.165.165-.337.345-.144.678.193.332.857 1.408 1.837 2.283 1.263 1.126 2.322 1.473 2.656 1.638.334.165.528.138.724-.082.196-.22.836-.971 1.058-1.303.22-.332.44-.276.743-.165.302.11 1.908.9 2.235 1.065.328.164.547.247.629.386.082.138.082.807-.193 1.58z"/>
  </svg>
</a>
<?php endif; ?>

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

<script src="<?= e(base_url('assets/js/app.js')) ?>?v=4" defer></script>
</body>
</html>
