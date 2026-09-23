<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$id = (int)($_GET['id'] ?? 0);
$item = [
    'id' => 0, 'category_id' => '', 'name' => '', 'description' => '',
    'price' => '', 'image' => '', 'is_available' => 1, 'is_featured' => 0,
];
$isEdit = false;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $item = $found;
        $isEdit = true;
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired, please try again.';
    } else {
        $item['category_id'] = (int)($_POST['category_id'] ?? 0);
        $item['name'] = trim($_POST['name'] ?? '');
        $item['description'] = trim($_POST['description'] ?? '');
        $priceRaw = trim($_POST['price'] ?? '');
        $item['price'] = $priceRaw === '' ? null : (float)$priceRaw;
        $item['is_available'] = isset($_POST['is_available']) ? 1 : 0;
        $item['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;

        if ($item['name'] === '') $errors[] = 'Please give the item a name.';
        if (!$item['category_id']) $errors[] = 'Please choose a category.';

        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!isset($allowed[$ext])) {
                $errors[] = 'Image must be a JPG, PNG or WEBP file.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image must be smaller than 5MB.';
            } else {
                $newName = 'item-' . uniqid() . '.' . $ext;
                $destDir = BASE_PATH . '/assets/img';
                if (!is_dir($destDir)) mkdir($destDir, 0775, true);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destDir . '/' . $newName)) {
                    $item['image'] = $newName;
                } else {
                    $errors[] = 'Could not save the uploaded image — please try again.';
                }
            }
        }

        if (empty($errors)) {
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE menu_items SET category_id=?, name=?, description=?, price=?, image=?, is_available=?, is_featured=? WHERE id=?");
                $stmt->execute([$item['category_id'], $item['name'], $item['description'], $item['price'], $item['image'], $item['is_available'], $item['is_featured'], $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO menu_items (category_id, name, description, price, image, is_available, is_featured, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 999)");
                $stmt->execute([$item['category_id'], $item['name'], $item['description'], $item['price'], $item['image'], $item['is_available'], $item['is_featured']]);
            }
            header('Location: ' . base_url('admin/menu-items.php'));
            exit;
        }
    }
}

$adminActive = 'menu-items';
$adminTitle = $isEdit ? 'Edit Menu Item' : 'Add Menu Item';

include __DIR__ . '/includes/admin-header.php';
?>

<a href="<?= e(base_url('admin/menu-items.php')) ?>" class="btn btn-outline btn-sm" style="margin-bottom:20px;">← Back to Menu Items</a>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= e(base_url('admin/menu-item-form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="form-row">
      <label for="name">Item name</label>
      <input id="name" name="name" type="text" value="<?= e($item['name']) ?>" required>
    </div>

    <div class="form-row">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <option value="">Choose a category…</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= (int)$item['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3"><?= e($item['description']) ?></textarea>
    </div>

    <div class="form-row-2">
      <div class="form-row">
        <label for="price">Price (₦) — leave blank for "Ask for price"</label>
        <input id="price" name="price" type="number" step="1" min="0" value="<?= $item['price'] !== null && $item['price'] !== '' ? e((string)$item['price']) : '' ?>">
      </div>
      <div class="form-row">
        <label for="image">Photo</label>
        <input id="image" name="image" type="file" accept="image/*">
        <?php if (!empty($item['image'])): ?>
          <img class="thumb-sm" src="<?= e(img_url($item['image'])) ?>" alt="" style="margin-top:8px;">
        <?php endif; ?>
      </div>
    </div>

    <div class="checkbox-row">
      <input type="checkbox" id="is_available" name="is_available" <?= $item['is_available'] ? 'checked' : '' ?>>
      <label for="is_available" style="font-weight:600;">Available on the menu</label>
    </div>
    <div class="checkbox-row">
      <input type="checkbox" id="is_featured" name="is_featured" <?= $item['is_featured'] ? 'checked' : '' ?>>
      <label for="is_featured" style="font-weight:600;">Feature on homepage</label>
    </div>

    <button type="submit" class="btn btn-ember"><?= $isEdit ? 'Save Changes' : 'Add Item' ?></button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
