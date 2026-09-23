<?php
require_once __DIR__ . '/includes/config.php';

$lines = cart_items($pdo);
if (!$lines) {
    header('Location: ' . base_url('cart.php'));
    exit;
}

$zones = delivery_zones($pdo);
$bank = [
    'name' => get_setting($pdo, 'bank_name'),
    'acct_name' => get_setting($pdo, 'bank_account_name'),
    'acct_no' => get_setting($pdo, 'bank_account_number'),
];
$hasBank = $bank['name'] && $bank['acct_no'];

$errors = [];
$v = ['name' => '', 'phone' => '', 'fulfillment' => 'delivery', 'delivery_zone' => '', 'address' => '',
      'when' => 'asap', 'time' => '', 'payment_method' => 'cash', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please submit again.';
    }
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
    $v['fulfillment'] = $v['fulfillment'] === 'pickup' ? 'pickup' : 'delivery';
    if (!in_array($v['payment_method'], ['cash', 'transfer_on_delivery', 'transfer_now'], true)) $v['payment_method'] = 'cash';
    if ($v['payment_method'] === 'transfer_now' && !$hasBank) $v['payment_method'] = 'transfer_on_delivery';

    if ($v['name'] === '') $errors[] = 'Add your name so the rider knows who to ask for.';
    if (!preg_match('/^\+?[0-9 ]{10,15}$/', $v['phone'])) $errors[] = 'Add a phone number we can call, like 0803 123 4567.';
    $fee = 0.0;
    if ($v['fulfillment'] === 'delivery') {
        if ($zones && !isset($zones[$v['delivery_zone']])) $errors[] = 'Choose your delivery area.';
        else $fee = $zones[$v['delivery_zone']] ?? 0.0;
        if ($v['address'] === '') $errors[] = 'Add your delivery address with a landmark.';
    }
    $preferred = 'As soon as possible';
    if ($v['when'] === 'later') {
        if (!preg_match('/^\d{2}:\d{2}$/', $v['time'])) $errors[] = 'Pick a time for your scheduled order.';
        else $preferred = 'Today at ' . date('g:ia', strtotime($v['time']));
    }

    if (!$errors) {
        $subtotal = cart_subtotal($pdo);
        $code = order_code();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO orders (order_code, customer_name, phone, fulfillment, address, notes, status, subtotal,
            delivery_zone, delivery_fee, total, payment_method, preferred_time) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $v['name'], $v['phone'], $v['fulfillment'],
            $v['fulfillment'] === 'delivery' ? $v['address'] : null, $v['notes'], $subtotal,
            $v['fulfillment'] === 'delivery' ? $v['delivery_zone'] : null, $fee, $subtotal + $fee,
            $v['payment_method'], $preferred]);
        $orderId = (int)$pdo->lastInsertId();
        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, name, price, qty) VALUES (?, ?, ?, ?, ?)");
        foreach ($lines as $line) {
            $itemStmt->execute([$orderId, $line['item']['id'], $line['item']['name'], $line['item']['price'], $line['qty']]);
        }
        $pdo->commit();

        $uniqueCatsStmt = $pdo->prepare("SELECT COUNT(DISTINCT m.category_id) FROM order_items oi JOIN menu_items m ON m.id = oi.menu_item_id WHERE oi.order_id = ?");
        $uniqueCatsStmt->execute([$orderId]);
        $uniqueCats = (int)$uniqueCatsStmt->fetchColumn();

        $orderForTotal = ['id' => $orderId, 'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $subtotal + $fee];
        $total = (float)order_total($orderForTotal);
        $reward = game_reward_on_order($pdo, $total, $uniqueCats);
        $_SESSION['order_reward_' . $orderId] = $reward;

        cart_clear();
        $_SESSION['my_orders'][] = $code;
        header('Location: ' . base_url('order-confirmation.php') . '?code=' . urlencode($code));
        exit;
    }
}

$pageTitle = 'Checkout';
$active = 'cart';
$hideCartBar = true;
$subtotal = cart_subtotal($pdo);
$eta = get_setting($pdo, 'delivery_eta', '35–60 min');

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="wrap">
    <div class="crumbs"><a href="<?= e(base_url('cart.php')) ?>">Your order</a> / Checkout</div>
    <h1>Checkout</h1>
  </div>
</div>

<div class="wrap">
  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('checkout.php')) ?>" id="checkoutForm" data-subtotal="<?= e((string)$subtotal) ?>" class="order-grid" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div>
      <div class="form-card">
        <h2><span class="n">1</span>How do you want it?</h2>
        <div class="choice">
          <label><input type="radio" name="fulfillment" value="delivery" <?= $v['fulfillment'] === 'delivery' ? 'checked' : '' ?>><span class="opt"><strong>Delivery</strong><small><?= e($eta) ?></small></span></label>
          <label><input type="radio" name="fulfillment" value="pickup" <?= $v['fulfillment'] === 'pickup' ? 'checked' : '' ?>><span class="opt"><strong>Pickup</strong><small>RVS Mall, Gwarinpa. Free</small></span></label>
        </div>
        <div data-if-delivery>
          <?php if ($zones): ?>
          <div class="field">
            <label for="delivery_zone">Delivery area</label>
            <select id="delivery_zone" name="delivery_zone">
              <option value="">Choose your area</option>
              <?php foreach ($zones as $z => $fee): ?>
                <option value="<?= e($z) ?>" data-fee="<?= e((string)$fee) ?>" <?= $v['delivery_zone'] === $z ? 'selected' : '' ?>><?= e($z) ?> (<?= e(money($fee, $pdo)) ?>)</option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Area not listed? Call <?= e(get_setting($pdo, 'phone_primary')) ?> and we will quote you.</span>
          </div>
          <?php endif; ?>
          <div class="field">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="2" placeholder="House number, street, estate, nearest landmark"><?= e($v['address']) ?></textarea>
          </div>
        </div>
        <span class="lbl" style="font-weight:600;font-size:.93rem;display:block;margin-bottom:8px;">When?</span>
        <div class="choice">
          <label><input type="radio" name="when" value="asap" <?= $v['when'] !== 'later' ? 'checked' : '' ?>><span class="opt"><strong>As soon as possible</strong></span></label>
          <label><input type="radio" name="when" value="later" <?= $v['when'] === 'later' ? 'checked' : '' ?>><span class="opt"><strong>Later today</strong></span></label>
        </div>
        <div class="field" id="schedTime" hidden>
          <label for="time">Time</label>
          <input id="time" name="time" type="time" min="08:00" max="21:00" value="<?= e($v['time']) ?>">
        </div>
      </div>

      <div class="form-card">
        <h2><span class="n">2</span>Your details</h2>
        <div class="field-2">
          <div class="field"><label for="name">Name</label><input id="name" name="name" autocomplete="name" value="<?= e($v['name']) ?>" required></div>
          <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0803 123 4567" value="<?= e($v['phone']) ?>" required></div>
        </div>
        <div class="field">
          <label for="notes">Notes for the kitchen</label>
          <textarea id="notes" name="notes" rows="2" placeholder="Protein choice, spice level, allergies, gate code"><?= e($v['notes']) ?></textarea>
        </div>
      </div>

      <div class="form-card">
        <h2><span class="n">3</span>Payment</h2>
        <div class="choice">
          <label><input type="radio" name="payment_method" value="cash" <?= $v['payment_method'] === 'cash' ? 'checked' : '' ?>><span class="opt"><strong>Cash</strong><small>Pay on arrival</small></span></label>
          <label><input type="radio" name="payment_method" value="transfer_on_delivery" <?= $v['payment_method'] === 'transfer_on_delivery' ? 'checked' : '' ?>><span class="opt"><strong>Transfer on arrival</strong><small>Pay the rider by transfer</small></span></label>
          <?php if ($hasBank): ?>
          <label><input type="radio" name="payment_method" value="transfer_now" <?= $v['payment_method'] === 'transfer_now' ? 'checked' : '' ?>><span class="opt"><strong>Transfer now</strong><small>Fastest confirmation</small></span></label>
          <?php endif; ?>
        </div>
        <?php if ($hasBank): ?>
        <div class="bank-box" id="bankBox" hidden>
          Send the total to <strong><?= e($bank['acct_no']) ?></strong>, <?= e($bank['name']) ?><?= $bank['acct_name'] ? ', ' . e($bank['acct_name']) : '' ?>. Use your order code as the narration; you get it on the next screen.
        </div>
        <?php endif; ?>
      </div>
    </div>

    <aside class="sticky-col">
      <div class="docket">
        <div class="docket-head"><span class="docket-title">Order ticket</span><span class="docket-meta"><?= cart_count() ?> items</span></div>
        <?php foreach ($lines as $line): ?>
          <div class="docket-row"><span><?= (int)$line['qty'] ?> × <?= e($line['item']['name']) ?></span><span><?= money($line['line_total'], $pdo) ?></span></div>
        <?php endforeach; ?>
        <div class="docket-row" style="margin-top:8px;"><span>Subtotal</span><span><?= money($subtotal, $pdo) ?></span></div>
        <div class="docket-row"><span>Delivery</span><span id="sumFee">Choose area</span></div>
        <div class="docket-row total"><span>Total</span><span id="sumTotal"><?= money($subtotal, $pdo) ?></span></div>
        <p class="docket-note">We call you to confirm before cooking starts.</p>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px;min-height:56px;font-size:1.05rem;">Place order</button>
    </aside>
  </form>
  <div style="height:60px"></div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
