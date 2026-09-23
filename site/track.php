<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Track your order';
$active = 'track';
$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$code = ltrim($code, '#');
$phoneIn = trim((string)($_GET['phone'] ?? ''));
$order = null;
$error = null;

if ($code !== '') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
    $stmt->execute([$code]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    $digits = fn($s) => substr(preg_replace('/\D/', '', $s), -10);
    if ($found && $digits($found['phone']) === $digits($phoneIn) && $digits($phoneIn) !== '') {
        $order = $found;
        $ls = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $ls->execute([$order['id']]);
        $orderLines = $ls->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = 'We could not find that order. Check the code and use the same phone number you ordered with.';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="wrap">
    <h1>Track your order</h1>
    <?php if (!$order): ?><p>Enter the order code from your confirmation screen and the phone number you ordered with.</p><?php endif; ?>
  </div>
</div>

<div class="wrap track-wrap">
  <?php if ($order): ?>
    <div data-autorefresh>
      <?php include __DIR__ . '/includes/order-docket.php'; ?>
      <p style="color:var(--ink-soft);font-size:.9rem;margin-top:24px;">This page refreshes itself. Last checked <?= date('g:ia') ?>.</p>
      <a class="btn btn-ghost" href="tel:<?= e(tel(get_setting($pdo, 'phone_primary'))) ?>">Call the kitchen</a>
    </div>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form class="form-card" method="get" action="<?= e(base_url('track.php')) ?>">
      <div class="field"><label for="code">Order code</label><input id="code" name="code" placeholder="DD260923-A1B2" value="<?= e($code) ?>" required autocapitalize="characters"></div>
      <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($phoneIn) ?>" required></div>
      <button class="btn btn-primary btn-block" type="submit">Show my order</button>
    </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
