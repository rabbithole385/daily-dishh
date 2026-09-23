<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$adminActive = 'photos';
$adminTitle = 'Site Photos';
$errors = [];
$successMsg = null;

$slots = [
    'hero_1' => ['Homepage, large photo', 'swallow-soup-plain.jpg'],
    'hero_2' => ['Homepage, top small photo', 'grilled-foil.jpg'],
    'hero_3' => ['Homepage, bottom small photo', 'jollof-trays.jpg'],
    'about'  => ['About page photo', 'swallow-soup-plain.jpg'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired, please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'slot' && isset($slots[$_POST['slot'] ?? ''])) {
            $err = null;
            $name = save_uploaded_image($_FILES['photo'] ?? [], 'site', $err);
            if ($name) { set_setting($pdo, 'photo_' . $_POST['slot'], $name); $successMsg = 'Photo updated.'; }
            elseif ($err) $errors[] = $err;
            else $errors[] = 'Choose a photo first.';
        } elseif ($action === 'reset' && isset($slots[$_POST['slot'] ?? ''])) {
            set_setting($pdo, 'photo_' . $_POST['slot'], '');
            $successMsg = 'Back to the default photo.';
        } elseif ($action === 'gallery_add') {
            $list = gallery_photos($pdo);
            $files = $_FILES['photos'] ?? null;
            $added = 0;
            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $n) {
                    $err = null;
                    $one = ['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                    $saved = save_uploaded_image($one, 'gallery', $err);
                    if ($saved) { array_unshift($list, $saved); $added++; }
                    elseif ($err) $errors[] = $n . ': ' . $err;
                }
            }
            set_setting($pdo, 'gallery_photos', implode(',', array_slice($list, 0, 16)));
            if ($added) $successMsg = $added . ' photo(s) added to the gallery.';
        } elseif ($action === 'gallery_remove') {
            $rm = $_POST['file'] ?? '';
            $list = array_values(array_filter(gallery_photos($pdo), fn($f) => $f !== $rm));
            set_setting($pdo, 'gallery_photos', implode(',', $list));
            $successMsg = 'Removed from the gallery.';
        }
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<p class="text-muted" style="margin-top:0;">Dish photos are set on each menu item. Use this page for the homepage, about page and kitchen gallery. Portrait or square photos under 8MB work best.</p>

<div class="photo-grid">
  <?php foreach ($slots as $key => [$label, $fallback]): ?>
    <div class="card photo-card">
      <img src="<?= e(site_photo($pdo, $key, $fallback)) ?>" alt="">
      <h2><?= e($label) ?></h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="slot"><input type="hidden" name="slot" value="<?= e($key) ?>">
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn btn-ember btn-sm" type="submit">Upload</button>
      </form>
      <?php if (get_setting($pdo, 'photo_' . $key, '') !== ''): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="reset"><input type="hidden" name="slot" value="<?= e($key) ?>">
        <button class="btn btn-outline btn-sm" type="submit">Use default</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="flex-between"><h2>Kitchen gallery</h2></div>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="gallery_add">
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
    <button class="btn btn-ember btn-sm" type="submit">Add photos</button>
  </form>
  <div class="gallery-admin">
    <?php foreach (gallery_photos($pdo) as $g): ?>
      <div>
        <img src="<?= e(img_url($g)) ?>" alt="">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="gallery_remove"><input type="hidden" name="file" value="<?= e($g) ?>">
          <button class="btn btn-outline btn-sm" type="submit">Remove</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
