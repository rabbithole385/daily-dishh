<?php
require_once __DIR__ . '/includes/config.php';

// Old non-JS add form support (links from older pages)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . base_url('menu.php'));
    exit;
}

$pageTitle = 'Menu';
$active = 'menu';

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$itemsStmt = $pdo->prepare("SELECT * FROM menu_items WHERE category_id = ? AND is_available = 1 ORDER BY (image IS NULL), sort_order");

$menu = [];
foreach ($categories as $cat) {
    $itemsStmt->execute([$cat['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($items) $menu[] = ['cat' => $cat, 'items' => $items];
}

include __DIR__ . '/includes/header.php';
$ok = flash_get('success');
$err = flash_get('error');
?>

<div class="page-head">
  <div class="wrap">
    <h1>Menu</h1>
    <p>Tap any dish to add it. Dishes without a listed price are cooked to order, so tap Ask price and we will reply on WhatsApp.</p>
  </div>
</div>

<div class="menu-bar">
  <div class="wrap menu-bar-in">
    <label class="menu-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input id="menuSearch" type="search" placeholder="Search jollof, egusi, prawns…" aria-label="Search the menu" autocomplete="off">
    </label>
    <nav class="chips" aria-label="Menu sections">
      <?php foreach ($menu as $sec): ?>
        <a class="chip" href="#<?= e($sec['cat']['slug']) ?>"><?= e($sec['cat']['name']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<div class="wrap" style="padding-bottom:80px;">
  <?php if ($ok): ?><div class="alert alert-success" style="margin-top:20px;"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error" style="margin-top:20px;"><?= e($err) ?></div><?php endif; ?>

  <?php foreach ($menu as $sec):
      $cat = $sec['cat'];
      $withPhoto = array_filter($sec['items'], fn($i) => !empty($i['image']));
      $noPhoto = array_filter($sec['items'], fn($i) => empty($i['image']));
  ?>
    <section class="menu-cat" id="<?= e($cat['slug']) ?>" data-menu-cat>
      <div class="menu-cat-head">
        <h2><?= e($cat['name']) ?></h2>
        <span><?= count($sec['items']) ?> dishes</span>
      </div>

      <?php if ($withPhoto): ?>
      <div class="dish-grid" data-group>
        <?php foreach ($withPhoto as $item): ?>
          <article class="dish"<?= dish_attr($item, $cat['name']) ?> data-search="<?= e(strtolower($item['name'] . ' ' . $item['description'] . ' ' . $cat['name'])) ?>">
            <div class="dish-photo" data-open-dish>
              <img src="<?= e(img_url($item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
            </div>
            <div class="dish-body">
              <h3 data-open-dish><?= e($item['name']) ?></h3>
              <p><?= e($item['description']) ?></p>
              <div class="dish-foot">
                <span class="price <?= $item['price'] === null ? 'ask' : '' ?>"><?= $item['price'] === null ? 'Price on request' : money((float)$item['price'], $pdo) ?></span>
                <?= add_control($pdo, $item) ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($noPhoto): ?>
      <div class="menu-rows" data-group>
        <?php foreach ($noPhoto as $item): ?>
          <div class="mrow"<?= dish_attr($item, $cat['name']) ?> data-search="<?= e(strtolower($item['name'] . ' ' . $item['description'] . ' ' . $cat['name'])) ?>">
            <div>
              <h3 data-open-dish><?= e($item['name']) ?></h3>
              <p><?= e($item['description']) ?></p>
            </div>
            <div class="mrow-end">
              <span class="price <?= $item['price'] === null ? 'ask' : '' ?>"><?= $item['price'] === null ? 'Price on request' : money((float)$item['price'], $pdo) ?></span>
              <?= add_control($pdo, $item) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

  <div class="menu-empty" id="menuEmpty" hidden>
    <h2>Nothing matches that</h2>
    <p>Try a shorter word like “rice” or “soup”, or call the kitchen and ask.</p>
  </div>
</div>

<?= sheet_markup() ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
