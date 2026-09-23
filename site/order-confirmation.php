<?php
require_once __DIR__ . '/includes/config.php';

$code = (string)($_GET['code'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$code]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Only the browser that placed the order sees its details here; everyone else uses Track order.
if (!$order || !in_array($code, $_SESSION['my_orders'] ?? [], true)) {
    header('Location: ' . base_url('track.php'));
    exit;
}
$ls = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$ls->execute([$order['id']]);
$orderLines = $ls->fetchAll(PDO::FETCH_ASSOC);

$reward = $_SESSION['order_reward_' . (int)$order['id']] ?? null;
if ($reward !== null) {
    unset($_SESSION['order_reward_' . (int)$order['id']]);
}

$pageTitle = 'Order placed';
$hideCartBar = true;
$phone = get_setting($pdo, 'phone_primary');
$wa = get_setting($pdo, 'whatsapp');

$msg = "Hi Daily Dish, I just placed order #{$order['order_code']}\n";
foreach ($orderLines as $l) $msg .= "- {$l['qty']} x {$l['name']}\n";
$msg .= 'Total: ' . money(order_total($order), $pdo) . "\n";
$msg .= $order['fulfillment'] === 'pickup' ? 'Pickup' : 'Delivery to: ' . $order['delivery_zone'] . ', ' . $order['address'];

include __DIR__ . '/includes/header.php';
?>

<div class="wrap confirm">
  <?php if ($reward): ?><div hidden data-order-reward="<?= e(json_encode($reward)) ?>"></div><?php endif; ?>
  <h1>Order sent to the kitchen</h1>
  <p>Thanks, <?= e($order['customer_name']) ?>. We will call <?= e($order['phone']) ?> in a few minutes to confirm before we start cooking.</p>

  <?php include __DIR__ . '/includes/order-docket.php'; ?>

  <?php if (($order['payment_method'] ?? '') === 'transfer_now' && get_setting($pdo, 'bank_account_number')): ?>
    <div class="bank-box" style="margin-bottom:24px;">
      Transfer <strong><?= money(order_total($order), $pdo) ?></strong> to <strong><?= e(get_setting($pdo, 'bank_account_number')) ?></strong>, <?= e(get_setting($pdo, 'bank_name')) ?>, <?= e(get_setting($pdo, 'bank_account_name')) ?>. Narration: <strong><?= e($order['order_code']) ?></strong>
    </div>
  <?php endif; ?>

  <div class="confirm-actions">
    <?php if ($wa): ?><a class="btn btn-whatsapp" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode($msg) ?>" target="_blank" rel="noopener">Send order on WhatsApp for faster confirmation</a><?php endif; ?>
    <a class="btn btn-dark" href="<?= e(base_url('track.php?code=' . urlencode($order['order_code']) . '&phone=' . urlencode($order['phone']))) ?>">Track this order</a>
    <a class="btn btn-ghost" href="tel:<?= e(tel($phone)) ?>">Call the kitchen</a>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
