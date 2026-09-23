<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$adminActive = 'dashboard';
$adminTitle = 'Dashboard';

$today = date('Y-m-d');
$todayCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at) = date('now')")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$weekRevenue = $pdo->query("SELECT COALESCE(SUM(COALESCE(total, subtotal)),0) FROM orders WHERE created_at >= datetime('now','-7 days') AND status != 'cancelled'")->fetchColumn();
$totalItems = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();

$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div class="stat-grid">
  <div class="stat-card accent">
    <div class="label">Orders Today</div>
    <div class="value"><?= (int)$todayCount ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Pending Orders</div>
    <div class="value"><?= (int)$pendingCount ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Revenue (7 days)</div>
    <div class="value"><?= money((float)$weekRevenue, $pdo) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Menu Items</div>
    <div class="value"><?= (int)$totalItems ?></div>
  </div>
</div>

<div class="card">
  <div class="flex-between">
    <h2>Recent Orders</h2>
    <a href="<?= e(base_url('admin/orders.php')) ?>" class="btn btn-outline btn-sm">View All</a>
  </div>
  <?php if (empty($recentOrders)): ?>
    <p class="text-muted">No orders yet — once customers check out, they'll show up here.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $order): ?>
          <tr>
            <td class="mono">#<?= e($order['order_code']) ?></td>
            <td><?= e($order['customer_name']) ?></td>
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
