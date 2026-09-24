<?php
/** Expects $pdo, $order, $orderLines. Renders the order ticket with status timeline. */
$isPickup = $order['fulfillment'] === 'pickup';
$flow = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
$labels = [
    'pending' => ['Order received', 'Waiting for the kitchen to confirm'],
    'confirmed' => ['Confirmed', 'We have called you and locked it in'],
    'preparing' => ['In the kitchen', 'Your food is being cooked fresh'],
    'out_for_delivery' => $isPickup ? ['Ready for pickup', 'Come to RVS Mall, Shop 4'] : ['On the way', 'The rider has left the kitchen'],
    'delivered' => $isPickup ? ['Collected', 'Enjoy your meal'] : ['Delivered', 'Enjoy your meal'],
];
$pos = array_search($order['status'], $flow, true);
$cancelled = $order['status'] === 'cancelled';
?>
<div class="docket">
  <?php if ($cancelled): ?><span class="stamp cancelled">Cancelled</span>
  <?php elseif ($order['status'] === 'delivered'): ?><span class="stamp"><?= $isPickup ? 'Collected' : 'Delivered' ?></span><?php endif; ?>
  <div class="docket-head" style="flex-direction:column;align-items:flex-start;gap:2px;">
    <span class="docket-meta">Order code</span>
    <span class="code-big">#<?= e($order['order_code']) ?></span>
  </div>
  <?php foreach ($orderLines as $l): ?>
    <div class="docket-row"><span><?= (int)$l['qty'] ?> × <?= e($l['name']) ?></span><span><?= money((float)$l['price'] * (int)$l['qty'], $pdo) ?></span></div>
  <?php endforeach; ?>
  <?php if (!$isPickup): ?>
    <div class="docket-row" style="margin-top:8px;"><span>Delivery<?= $order['delivery_zone'] ? ' (' . e($order['delivery_zone']) . ')' : '' ?></span><span><?= money((float)($order['delivery_fee'] ?? 0), $pdo) ?></span></div>
  <?php endif; ?>
  <div class="docket-row total"><span>Total</span><span><?= money(order_total($order), $pdo) ?></span></div>
  <div class="docket-row"><span><?= $isPickup ? 'Pickup' : 'Delivery' ?></span><span><?= e($order['preferred_time'] ?: 'As soon as possible') ?></span></div>
  <div class="docket-row"><span>Payment</span><span><?= e(payment_label($order['payment_method'] ?? 'cash')) ?></span></div>

  <?php if (!$cancelled): ?>
  <?php $orderStatus = $order['status']; include __DIR__ . '/status-timeline.php'; ?>
  <ol class="timeline">
    <?php foreach ($flow as $i => $s): $cls = $i < $pos ? 'done' : ($i === $pos ? ($s === 'delivered' ? 'done' : 'now') : ''); ?>
      <li class="<?= $cls ?>"><span class="dot"></span><strong><?= e($labels[$s][0]) ?></strong><small><?= e($labels[$s][1]) ?></small></li>
    <?php endforeach; ?>
  </ol>
  <?php else: ?>
    <p class="docket-note">This order was cancelled. Call the kitchen if that is a surprise.</p>
  <?php endif; ?>
</div>
