<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ' . base_url('admin/orders.php'));
    exit;
}

$statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
$successMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, $statuses, true)) {
        $upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = datetime('now') WHERE id = ?");
        $upd->execute([$newStatus, $id]);
        $order['status'] = $newStatus;
        $successMsg = 'Order status updated.';
    }
}

$lineStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

$adminActive = 'orders';
$adminTitle = 'Order #' . $order['order_code'];

include __DIR__ . '/includes/admin-header.php';
?>

<a href="<?= e(base_url('admin/orders.php')) ?>" class="btn btn-outline btn-sm" style="margin-bottom:20px;">← Back to Orders</a>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>

<div class="card">
  <div class="flex-between">
    <h2>Customer Details</h2>
    <?php $waCust = preg_replace('/^0/', '234', preg_replace('/\D/', '', $order['phone'])); ?>
    <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= e($waCust) ?>?text=<?= rawurlencode('Hi ' . $order['customer_name'] . ', this is Daily Dish about your order #' . $order['order_code'] . '. Status: ' . status_label($order['status']) . '.') ?>">WhatsApp customer</a>
    <span class="badge badge-<?= e($order['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></span>
  </div>
  <table class="admin-table">
    <tbody>
      <tr><td><strong>Name</strong></td><td><?= e($order['customer_name']) ?></td></tr>
      <tr><td><strong>Phone</strong></td><td><a href="tel:<?= e(preg_replace('/\s+/', '', $order['phone'])) ?>"><?= e($order['phone']) ?></a></td></tr>
      <tr><td><strong>Fulfillment</strong></td><td><?= e(ucfirst($order['fulfillment'])) ?><?= !empty($order['delivery_zone']) ? ' to ' . e($order['delivery_zone']) : '' ?></td></tr>
      <tr><td><strong>When</strong></td><td><?= e($order['preferred_time'] ?: 'As soon as possible') ?></td></tr>
      <tr><td><strong>Payment</strong></td><td><?= e(payment_label($order['payment_method'] ?? 'cash')) ?></td></tr>
      <?php if ($order['address']): ?><tr><td><strong>Address</strong></td><td><?= nl2br(e($order['address'])) ?></td></tr><?php endif; ?>
      <?php if ($order['notes']): ?><tr><td><strong>Notes</strong></td><td><?= nl2br(e($order['notes'])) ?></td></tr><?php endif; ?>
      <tr><td><strong>Placed</strong></td><td><?= e(date('d M Y, g:ia', strtotime($order['created_at']))) ?></td></tr>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>Items</h2>
  <table class="admin-table">
    <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Line Total</th></tr></thead>
    <tbody>
      <?php foreach ($lines as $line): ?>
        <tr>
          <td><?= e($line['name']) ?></td>
          <td><?= money((float)$line['price'], $pdo) ?></td>
          <td><?= (int)$line['qty'] ?></td>
          <td><?= money((float)$line['price'] * (int)$line['qty'], $pdo) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="3" style="text-align:right;">Subtotal</td><td><?= money((float)$order['subtotal'], $pdo) ?></td></tr>
      <tr><td colspan="3" style="text-align:right;">Delivery</td><td><?= money((float)($order['delivery_fee'] ?? 0), $pdo) ?></td></tr>
      <tr><td colspan="3" style="text-align:right;"><strong>Total</strong></td><td><strong><?= money(order_total($order), $pdo) ?></strong></td></tr>
    </tfoot>
  </table>
</div>

<div class="card">
  <h2>Update Status</h2>
  <form method="post" action="<?= e(base_url('admin/order-detail.php?id=' . $id)) ?>" class="form-grid" style="max-width:320px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-row">
      <select name="status">
        <?php foreach ($statuses as $s): ?>
          <option value="<?= e($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-ember">Update Status</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
