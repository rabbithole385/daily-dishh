<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$adminActive = 'categories';
$adminTitle = 'Categories';
$errors = [];
$successMsg = null;

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);
        if ($name === '') {
            $errors[] = 'Please give the category a name.';
        } else {
            $slug = slugify($name);
            $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
            $check->execute([$slug]);
            if ((int)$check->fetchColumn() > 0) {
                $slug .= '-' . substr(uniqid(), -4);
            }
            $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)")->execute([$name, $slug, $order]);
            $successMsg = 'Category added.';
        }
    } elseif ($action === 'update') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);
        if ($name !== '') {
            $pdo->prepare("UPDATE categories SET name = ?, sort_order = ? WHERE id = ?")->execute([$name, $order, $catId]);
            $successMsg = 'Category updated.';
        }
    } elseif ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $count = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE category_id = ?");
        $count->execute([$catId]);
        if ((int)$count->fetchColumn() > 0) {
            $errors[] = 'This category still has menu items in it — move or delete those first.';
        } else {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$catId]);
            $successMsg = 'Category deleted.';
        }
    }
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id) AS item_count FROM categories c ORDER BY c.sort_order")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card">
  <h2>Add Category</h2>
  <form method="post" action="<?= e(base_url('admin/categories.php')) ?>" class="form-grid" style="max-width:480px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">
    <div class="form-row-2">
      <div class="form-row">
        <label for="name">Category name</label>
        <input id="name" name="name" type="text" required>
      </div>
      <div class="form-row">
        <label for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" value="0">
      </div>
    </div>
    <button type="submit" class="btn btn-ember">Add Category</button>
  </form>
</div>

<div class="card">
  <h2>All Categories</h2>
  <table class="admin-table">
    <thead><tr><th>Order</th><th>Name</th><th>Items</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $cat): $fid = 'cat-form-' . (int)$cat['id']; ?>
        <tr>
          <td style="width:90px;"><input form="<?= $fid ?>" name="sort_order" type="number" value="<?= (int)$cat['sort_order'] ?>" style="width:70px;padding:6px 8px;border:1px solid var(--line);border-radius:4px;"></td>
          <td><input form="<?= $fid ?>" name="name" type="text" value="<?= e($cat['name']) ?>" style="padding:6px 8px;border:1px solid var(--line);border-radius:4px;width:220px;"></td>
          <td class="text-muted"><?= (int)$cat['item_count'] ?> items</td>
          <td style="white-space:nowrap;">
            <button form="<?= $fid ?>" type="submit" class="btn btn-outline btn-sm">Save</button>
            <form method="post" action="<?= e(base_url('admin/categories.php')) ?>" style="display:inline;" onsubmit="return confirm('Delete this category?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php foreach ($categories as $cat): $fid = 'cat-form-' . (int)$cat['id']; ?>
    <form id="<?= $fid ?>" method="post" action="<?= e(base_url('admin/categories.php')) ?>" style="display:none;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
