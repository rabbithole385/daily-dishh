<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$adminActive = 'orders';
$adminTitle = 'Orders';

$statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
$filter = $_GET['status'] ?? '';

if ($filter && in_array($filter, $statuses, true)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC");
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
}
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div class="filter-tabs">
  <a href="<?= e(base_url('admin/orders.php')) ?>" class="<?= $filter === '' ? 'active' : '' ?>">All</a>
  <?php foreach ($statuses as $s): ?>
    <a href="<?= e(base_url('admin/orders.php?status=' . $s)) ?>" class="<?= $filter === $s ? 'active' : '' ?>"><?= e(ucwords(str_replace('_', ' ', $s))) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($orders)): ?>
    <p class="text-muted">No orders found for this filter.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Phone</th><th>Type</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td class="mono">#<?= e($order['order_code']) ?></td>
            <td><?= e($order['customer_name']) ?></td>
            <td><?= e($order['phone']) ?></td>
            <td><?= e(ucfirst($order['fulfillment'])) ?></td>
            <td><?= money(order_total($order), $pdo) ?></td>
            <td><span class="badge badge-<?= e($order['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></span></td>
            <td class="text-muted"><?= e(date('d M, g:ia', strtotime($order['created_at']))) ?></td>
            <td><a href="<?= e(base_url('admin/order-detail.php?id=' . (int)$order['id'])) ?>" class="btn btn-outline btn-sm">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
