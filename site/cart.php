<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Your order';
$active = 'cart';
$hideCartBar = true;
$lines = cart_items($pdo);
$subtotal = cart_subtotal($pdo);
$back = base_url('cart.php');

include __DIR__ . '/includes/header.php';
$ok = flash_get('success');
$err = flash_get('error');
?>

<div class="page-head">
  <div class="wrap">
    <div class="crumbs"><a href="<?= e(base_url('menu.php')) ?>">Menu</a> / Your order</div>
    <h1>Your order</h1>
  </div>
</div>

<div class="wrap">
  <?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

  <?php if (!$lines): ?>
    <div class="empty">
      <img src="<?= e(base_url('assets/img/plate.svg')) ?>" alt="">
      <h2>Your plate is empty</h2>
      <p>Pick a few dishes from the menu and they will show up here.</p>
      <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-primary">Browse the menu</a>
    </div>
  <?php else: ?>
    <div class="order-grid">
      <div>
        <div class="order-lines">
          <?php foreach ($lines as $line): $item = $line['item']; $id = (int)$item['id']; ?>
            <div class="oline">
              <img src="<?= e(img_url($item['image'])) ?>" alt="">
              <div>
                <h3><?= e($item['name']) ?></h3>
                <span class="unit"><?= money((float)$item['price'], $pdo) ?> each</span>
              </div>
              <div class="oline-end">
                <div class="stepper">
                  <form method="post" action="<?= e(base_url('api/cart.php')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                    <input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="action" value="dec">
                    <button type="submit" aria-label="One less <?= e($item['name']) ?>">−</button>
                  </form>
                  <output><?= (int)$line['qty'] ?></output>
                  <form method="post" action="<?= e(base_url('api/cart.php')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                    <input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="action" value="inc">
                    <button type="submit" aria-label="One more <?= e($item['name']) ?>">+</button>
                  </form>
                </div>
                <span class="oline-total"><?= money($line['line_total'], $pdo) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="order-after">
          <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-ghost btn-sm">Add more dishes</a>
          <form method="post" action="<?= e(base_url('api/cart.php')) ?>" onsubmit="return confirm('Clear everything from your order?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="link-btn">Clear order</button>
          </form>
        </div>

        <a class="btn btn-primary btn-block express-checkout" href="<?= e(base_url('checkout.php')) ?>">Express checkout →</a>

        <?php
          $cartItemIds = array_map(fn($l) => (int)$l['item']['id'], $lines);
          $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
          $alsoStmt = $pdo->prepare("SELECT * FROM menu_items WHERE is_available = 1 AND price IS NOT NULL AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT 3");
          $alsoStmt->execute($cartItemIds);
          $alsoItems = $alsoStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if ($alsoItems): ?>
        <div class="also-section">
          <div class="also-head">
            <span class="eyebrow">You might also like</span>
            <h3>Guests also ordered</h3>
          </div>
          <div class="also-row">
            <?php foreach ($alsoItems as $alsoItem):
              $alsoPrice = (float)$alsoItem['price'];
              $alsoId = (int)$alsoItem['id'];
            ?>
              <div class="also-card">
                <div class="also-img">
                  <img src="<?= e(img_url($alsoItem['image'])) ?>" alt="<?= e($alsoItem['name']) ?>" loading="lazy">
                  <button class="quick-add" data-quick-add data-item-id="<?= $alsoId ?>">Add <?= money($alsoPrice, $pdo, false) ?></button>
                </div>
                <div class="also-body">
                  <h4><?= e($alsoItem['name']) ?></h4>
                  <span class="price"><?= money($alsoPrice, $pdo) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <aside class="sticky-col">
        <div class="docket">
          <div class="docket-head"><span class="docket-title">Order ticket</span><span class="docket-meta"><?= cart_count() ?> items</span></div>
          <?php foreach ($lines as $line): ?>
            <div class="docket-row"><span><?= (int)$line['qty'] ?> × <?= e($line['item']['name']) ?></span><span><?= money($line['line_total'], $pdo) ?></span></div>
          <?php endforeach; ?>
          <div class="docket-row total"><span>Subtotal</span><span><?= money($subtotal, $pdo) ?></span></div>
          <p class="docket-note">Delivery fee is added at checkout once you pick your area.</p>
        </div>
        <a href="<?= e(base_url('checkout.php')) ?>" class="btn btn-primary btn-block" style="margin-top:20px;">Continue to checkout</a>
      </aside>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
