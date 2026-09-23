<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$adminActive = 'menu-items';
$adminTitle = 'Menu Items';
$successMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$itemId]);
        $successMsg = 'Item deleted.';
    } elseif ($action === 'toggle_available') {
        $pdo->prepare("UPDATE menu_items SET is_available = 1 - is_available WHERE id = ?")->execute([$itemId]);
        $successMsg = 'Availability updated.';
    } elseif ($action === 'toggle_featured') {
        $pdo->prepare("UPDATE menu_items SET is_featured = 1 - is_featured WHERE id = ?")->execute([$itemId]);
        $successMsg = 'Featured status updated.';
    }
}

$catFilter = (int)($_GET['category'] ?? 0);
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT m.*, c.name AS category_name FROM menu_items m JOIN categories c ON c.id = m.category_id";
if ($catFilter) {
    $sql .= " WHERE m.category_id = " . (int)$catFilter;
}
$sql .= " ORDER BY c.sort_order, m.sort_order";
$items = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div class="flex-between" style="margin-bottom:18px;">
  <div class="filter-tabs" style="margin-bottom:0;">
    <a href="<?= e(base_url('admin/menu-items.php')) ?>" class="<?= !$catFilter ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= e(base_url('admin/menu-items.php?category=' . (int)$cat['id'])) ?>" class="<?= $catFilter === (int)$cat['id'] ? 'active' : '' ?>"><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <a href="<?= e(base_url('admin/menu-item-form.php')) ?>" class="btn btn-ember">+ Add Item</a>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>

<div class="card">
  <table class="admin-table">
    <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Available</th><th>Featured</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><img class="thumb-sm" src="<?= e(img_url($item['image'])) ?>" alt=""></td>
          <td><strong><?= e($item['name']) ?></strong></td>
          <td class="text-muted"><?= e($item['category_name']) ?></td>
          <td><?= money($item['price'] !== null ? (float)$item['price'] : null, $pdo) ?></td>
          <td>
            <form method="post" action="<?= e(base_url('admin/menu-items.php' . ($catFilter ? '?category=' . $catFilter : ''))) ?>" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_available">
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm"><?= $item['is_available'] ? 'Yes' : 'No' ?></button>
            </form>
          </td>
          <td>
            <form method="post" action="<?= e(base_url('admin/menu-items.php' . ($catFilter ? '?category=' . $catFilter : ''))) ?>" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_featured">
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm"><?= $item['is_featured'] ? '★' : '☆' ?></button>
            </form>
          </td>
          <td style="white-space:nowrap;">
            <a href="<?= e(base_url('admin/menu-item-form.php?id=' . (int)$item['id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
            <form method="post" action="<?= e(base_url('admin/menu-items.php' . ($catFilter ? '?category=' . $catFilter : ''))) ?>" style="display:inline;" onsubmit="return confirm('Delete this item? This cannot be undone.');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
