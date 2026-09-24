<?php
$railLines = cart_items($pdo);
$railCount = cart_count();
$railSubtotal = cart_subtotal($pdo);
?>
<aside class="sticky-order-rail" data-sticky-rail>
  <div class="docket">
    <?php if (!$railLines): ?>
      <div class="rail-empty" style="text-align:center;padding:22px 8px;">
        <div style="font-size:2.2rem;margin-bottom:8px;">🍽️</div>
        <div class="eyebrow">Order docket</div>
        <p style="margin:6px 0 14px;color:var(--ink-soft);font-size:.95rem;">Your table is empty — pick a dish to start your order.</p>
        <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-ghost btn-block btn-sm">Browse menu</a>
      </div>
    <?php else: ?>
      <div class="docket-head">
        <span class="docket-title">Your order</span>
        <span class="docket-meta" data-rail-count><?= $railCount ?> item<?= $railCount !== 1 ? 's' : '' ?></span>
      </div>
      <div data-rail-lines>
        <?php foreach ($railLines as $l): ?>
          <div class="docket-row">
            <span><?= (int)$l['qty'] ?> × <?= e($l['item']['name']) ?></span>
            <span><?= money($l['line_total'], $pdo) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="docket-row total">
        <span>Subtotal</span>
        <span data-rail-subtotal><?= money($railSubtotal, $pdo) ?></span>
      </div>
      <p class="docket-note" style="font-size:.8rem;margin-top:10px;">Delivery fees added at checkout.</p>
      <a href="<?= e(base_url('checkout.php')) ?>" class="btn btn-primary btn-block" style="margin-top:14px;min-height:48px;">Continue to checkout →</a>
    <?php endif; ?>
  </div>
</aside>
