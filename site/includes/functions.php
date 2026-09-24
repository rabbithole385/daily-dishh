<?php

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    if (!isset($GLOBALS['__dd_settings'])) {
        $GLOBALS['__dd_settings'] = [];
        foreach ($pdo->query("SELECT skey, svalue FROM settings") as $row) {
            $GLOBALS['__dd_settings'][$row['skey']] = $row['svalue'];
        }
    }
    $val = $GLOBALS['__dd_settings'][$key] ?? null;
    return ($val === null || $val === '') && $default !== '' ? $default : (string)($val ?? $default);
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?)
        ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue");
    $stmt->execute([$key, $value]);
    if (isset($GLOBALS['__dd_settings'])) {
        $GLOBALS['__dd_settings'][$key] = $value;
    }
}

function money(?float $amount, PDO $pdo = null, bool $formatted = true): string
{
    if ($amount === null) {
        return 'Ask for price';
    }
    $symbol = '₦';
    if ($pdo !== null) {
        $symbol = get_setting($pdo, 'currency_symbol', '₦');
    }
    $num = $formatted ? number_format($amount, 0) : (string)(int)$amount;
    return $symbol . $num;
}

function img_url(?string $filename): string
{
    if (!$filename) {
        return base_url('assets/img/placeholder-dish.svg');
    }
    return base_url('assets/img/' . $filename);
}

function base_url(string $path = ''): string
{
    // Works whether the site sits at the domain root or in a sub-folder.
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $root = str_replace('\\', '/', dirname($script));
    // If we're inside /admin, step one level up.
    if (in_array(basename($root), ['admin', 'api'], true)) {
        $root = dirname($root);
    }
    $root = rtrim($root, '/');
    return $root . '/' . ltrim($path, '/');
}

function order_code(): string
{
    return 'DD' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
}

// ---------------------------------------------------------------------
// Cart (session based: [menu_item_id => qty])
// ---------------------------------------------------------------------

function cart_get(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $itemId, int $qty = 1): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $qty = max(1, $qty);
    $_SESSION['cart'][$itemId] = ($_SESSION['cart'][$itemId] ?? 0) + $qty;
}

function cart_set_qty(int $itemId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$itemId]);
        return;
    }
    $_SESSION['cart'][$itemId] = $qty;
}

function cart_remove(int $itemId): void
{
    unset($_SESSION['cart'][$itemId]);
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_count(): int
{
    return array_sum(cart_get());
}

function cart_items(PDO $pdo): array
{
    $cart = cart_get();
    if (empty($cart)) {
        return [];
    }
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $qty = $cart[$row['id']];
        $result[] = [
            'item' => $row,
            'qty' => $qty,
            'line_total' => ($row['price'] ?? 0) * $qty,
        ];
    }
    return $result;
}

function cart_subtotal(PDO $pdo): float
{
    $total = 0.0;
    foreach (cart_items($pdo) as $line) {
        $total += $line['line_total'];
    }
    return $total;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    $token = $_POST['csrf'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

// ---------------------------------------------------------------------
// Redesign helpers (delivery zones, site photos, order tracking)
// ---------------------------------------------------------------------

/** Parse the "Area | fee" lines from settings into [name => fee]. */
function delivery_zones(PDO $pdo): array
{
    $raw = get_setting($pdo, 'delivery_zones', "Gwarinpa | 1500\nKubwa | 2500\nJabi | 3000\nWuse | 3500");
    $zones = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if ($parts[0] === '') continue;
        $zones[$parts[0]] = isset($parts[1]) && is_numeric($parts[1]) ? (float)$parts[1] : 0.0;
    }
    return $zones;
}

/** A site photo slot (hero, about, gallery …) with a bundled fallback. */
function site_photo(PDO $pdo, string $slot, string $fallback): string
{
    $file = get_setting($pdo, 'photo_' . $slot, '');
    return img_url($file !== '' ? $file : $fallback);
}

/** Gallery filenames: admin uploads first, then bundled kitchen shots. */
function gallery_photos(PDO $pdo): array
{
    $raw = get_setting($pdo, 'gallery_photos', '');
    $list = array_values(array_filter(array_map('trim', explode(',', $raw))));
    if (!$list) {
        $list = ['jollof-trays.jpg', 'grilled-foil.jpg', 'seafood-native.jpg', 'chicken-stirfry.jpg', 'swallow-soup-plain.jpg', 'rice-tray.jpg', 'pepper-chicken.jpg'];
    }
    return $list;
}

/** Save an uploaded image to assets/img and return its filename. */
function save_uploaded_image(array $file, string $prefix, ?string &$error = null): ?string
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        $error = 'Photos must be JPG, PNG or WEBP.';
        return null;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        $error = 'Photos must be smaller than 8MB.';
        return null;
    }
    if (@getimagesize($file['tmp_name']) === false) {
        $error = 'That file is not a valid image.';
        return null;
    }
    $name = $prefix . '-' . uniqid() . '.' . $ext;
    $dir = BASE_PATH . '/assets/img';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'Could not save the photo — check that assets/img is writable.';
        return null;
    }
    return $name;
}

function status_label(string $status): string
{
    return [
        'pending' => 'Received',
        'confirmed' => 'Confirmed',
        'preparing' => 'In the kitchen',
        'out_for_delivery' => 'On the way',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ][$status] ?? ucwords(str_replace('_', ' ', $status));
}

function payment_label(?string $method): string
{
    return [
        'cash' => 'Cash on delivery / pickup',
        'transfer_on_delivery' => 'Transfer on delivery / pickup',
        'transfer_now' => 'Bank transfer now',
    ][$method ?? 'cash'] ?? 'Cash on delivery / pickup';
}

function order_total(array $order): float
{
    if (isset($order['total']) && $order['total'] !== null) {
        return (float)$order['total'];
    }
    return (float)$order['subtotal'] + (float)($order['delivery_fee'] ?? 0);
}

function tel(string $phone): string
{
    return preg_replace('/[^0-9+]/', '', $phone);
}

function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Current request path, used as the "back" target for cart forms. */
function current_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? base_url('menu.php');
    return str_starts_with($uri, '/') ? $uri : base_url('menu.php');
}

/** Add-to-order button, or a way to ask for the price when there isn't one. */
function add_control(PDO $pdo, array $item, string $extraClass = ''): string
{
    if ($item['price'] === null || $item['price'] === '') {
        $wa = get_setting($pdo, 'whatsapp', '');
        if ($wa) {
            $msg = rawurlencode('Hi Daily Dish, how much is the ' . $item['name'] . ' today?');
            return '<a class="btn-ask ' . e($extraClass) . '" href="https://wa.me/' . e($wa) . '?text=' . $msg . '" target="_blank" rel="noopener">Ask price</a>';
        }
        return '<a class="btn-ask ' . e($extraClass) . '" href="tel:' . e(tel(get_setting($pdo, 'phone_primary'))) . '">Call for price</a>';
    }
    $qty = cart_get()[(int)$item['id']] ?? 0;
    ob_start(); ?>
<form class="add-form <?= e($extraClass) ?>" method="post" action="<?= e(base_url('api/cart.php')) ?>" data-add>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add">
  <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
  <input type="hidden" name="back" value="<?= e(current_path()) ?>">
  <button type="submit" class="btn-add" aria-label="Add <?= e($item['name']) ?> to your order">
    <span class="btn-add-plus" aria-hidden="true">+</span>
    <span class="btn-add-text">Add</span>
    <span class="btn-add-qty" data-qty <?= $qty ? '' : 'hidden' ?>><?= (int)$qty ?></span>
  </button>
</form>
<?php
    return ob_get_clean();
}

/** Data attribute payload for the tap-to-open dish sheet (priced dishes only). */
function dish_attr(array $item, string $catName): string
{
    if ($item['price'] === null || $item['price'] === '') return '';
    $data = [
        'id' => (int)$item['id'],
        'name' => $item['name'],
        'desc' => (string)$item['description'],
        'price' => (float)$item['price'],
        'img' => img_url($item['image']),
        'cat' => $catName,
    ];
    return ' data-dish="' . e(json_encode($data, JSON_UNESCAPED_UNICODE)) . '"';
}

function sheet_markup(): string
{
    ob_start(); ?>
<dialog class="sheet" id="dishSheet" aria-labelledby="sheetName">
  <div class="sheet-photo">
    <img data-sheet-img src="" alt="">
    <button type="button" class="sheet-close" data-sheet-close aria-label="Close">×</button>
  </div>
  <div class="sheet-body">
    <span class="sheet-cat" data-sheet-cat></span>
    <h2 id="sheetName" data-sheet-name></h2>
    <p data-sheet-desc></p>
    <div class="sheet-qty-block">
      <div class="sq-top">
        <div class="stepper">
          <button type="button" data-sheet-dec aria-label="Fewer">−</button>
          <output data-sheet-qty>1</output>
          <button type="button" data-sheet-inc aria-label="More">+</button>
        </div>
        <div class="sq-total"><small>Total</small><strong data-sheet-total></strong></div>
      </div>
      <label class="qty-slider">
        <span>Portions</span>
        <input type="range" data-sheet-slider min="1" max="20" value="1" step="1" aria-label="Quantity slider">
        <span class="qty-ticks"><i>1</i><i>5</i><i>10</i><i>15</i><i>20</i></span>
      </label>
    </div>
    <div class="sheet-foot">
      <button type="button" class="btn btn-primary btn-block sheet-add" data-sheet-add><span>Add to order</span></button>
    </div>
    <p class="sheet-note">Spice level, allergies or protein choice? Add it in the notes at checkout.</p>
  </div>
</dialog>
<?php
    return ob_get_clean();
}

function dish_markup(array $item, array $opts = []): string
{
    global $pdo;
    $catName = $opts['cat'] ?? ($item['category_name'] ?? '');
    $layout = $opts['layout'] ?? '';
    $itemId = (int)$item['id'];
    $price = $item['price'] !== null && $item['price'] !== '' ? (float)$item['price'] : null;
    $isFeatured = !empty($item['is_featured']) || !empty($item['featured']);
    $hasPrice = $price !== null;
    $searchAttr = isset($opts['search']) ? ' data-search="' . e(strtolower($opts['search'])) . '"' : '';

    if ($layout === 'feed') {
        ob_start(); ?>
<article class="feed-tile"<?= dish_attr($item, $catName) ?><?= $searchAttr ?>>
  <div class="ft-img">
    <?php if ($isFeatured): ?><span class="dish-hot flame">🔥 HOT</span><?php endif; ?>
    <img src="<?= e(img_url($item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
    <?php if ($hasPrice): ?>
      <button class="quick-add feed-add" data-quick-add data-item-id="<?= $itemId ?>">Add <?= money($price, $pdo, false) ?></button>
    <?php endif; ?>
  </div>
  <div class="ft-strip">
    <h3><?= e($item['name']) ?></h3>
    <span class="price <?= $hasPrice ? '' : 'ask' ?>"><?= $hasPrice ? money($price, $pdo) : 'Price on request' ?></span>
  </div>
</article>
<?php
        return ob_get_clean();
    }

    ob_start(); ?>
<article class="dish"<?= dish_attr($item, $catName) ?><?= $searchAttr ?>>
  <div class="dish-photo" data-open-dish>
    <?php if (!empty($opts['show_cat']) && $catName): ?><span class="dish-cat"><?= e($catName) ?></span><?php endif; ?>
    <?php if ($isFeatured): ?><span class="dish-hot flame">🔥 HOT</span><?php endif; ?>
    <img src="<?= e(img_url($item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
    <?php if ($hasPrice): ?>
      <button class="quick-add" data-quick-add data-item-id="<?= $itemId ?>">Add <?= money($price, $pdo, false) ?></button>
    <?php endif; ?>
  </div>
  <div class="dish-body">
    <h3 data-open-dish><?= e($item['name']) ?></h3>
    <p><?= e($item['description'] ?? '') ?></p>
    <div class="dish-foot">
      <span class="price <?= $hasPrice ? '' : 'ask' ?>"><?= $hasPrice ? money($price, $pdo) : 'Price on request' ?></span>
      <?= add_control($pdo, $item) ?>
    </div>
  </div>
</article>
<?php
    return ob_get_clean();
}

function menu_row(array $item, array $opts = []): string
{
    global $pdo;
    $catName = $opts['cat'] ?? '';
    $searchAttr = isset($opts['search']) ? ' data-search="' . e(strtolower($opts['search'])) . '"' : '';
    ob_start(); ?>
<div class="mrow"<?= dish_attr($item, $catName) ?><?= $searchAttr ?>>
  <div>
    <h3 data-open-dish><?= e($item['name']) ?></h3>
    <p><?= e($item['description'] ?? '') ?></p>
  </div>
  <div class="mrow-end">
    <span class="price <?= ($item['price'] === null || $item['price'] === '') ? 'ask' : '' ?>"><?= ($item['price'] === null || $item['price'] === '') ? 'Price on request' : money((float)$item['price'], $pdo) ?></span>
    <?= add_control($pdo, $item) ?>
  </div>
</div>
<?php
    return ob_get_clean();
}

function render_sticky_rail(): string
{
    ob_start();
    include __DIR__ . '/sticky-order-rail.php';
    return ob_get_clean();
}

// ---------------------------------------------------------------------
// Gamification: loyalty points, badges, streaks, daily spin
// All data stored per-visitor in session + persisted to gamification
// table by session id so returning customers keep progress.
// ---------------------------------------------------------------------

function game_session_id(): string
{
    if (empty($_SESSION['game_sid'])) {
        $_SESSION['game_sid'] = 'u' . substr(bin2hex(random_bytes(8)), 0, 12);
    }
    return $_SESSION['game_sid'];
}

function game_get_raw(PDO $pdo): array
{
    $sid = game_session_id();
    $stmt = $pdo->prepare("SELECT gvalue FROM gamification WHERE gkey = ?");
    $stmt->execute(["state:$sid"]);
    $row = $stmt->fetchColumn();
    $data = $row ? json_decode($row, true) : null;
    if (!is_array($data)) $data = [];
    return $data + [
        'points' => 0,
        'total_spent' => 0,
        'orders' => 0,
        'streak_days' => 0,
        'last_order_date' => null,
        'badges' => [],
        'last_spin_date' => null,
        'lucky_reward' => null,
        'xp' => 0,
    ];
}

function game_save_raw(PDO $pdo, array $data): void
{
    $sid = game_session_id();
    $stmt = $pdo->prepare("INSERT INTO gamification (gkey, gvalue) VALUES (?, ?)
        ON CONFLICT(gkey) DO UPDATE SET gvalue = excluded.gvalue");
    $stmt->execute(["state:$sid", json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function game_level(int $xp): array
{
    $levels = [
        ['name' => 'Table for One', 'min' => 0,    'emoji' => '🥢', 'color' => '#8C6A4F'],
        ['name' => 'Regular',       'min' => 200,  'emoji' => '🍲', 'color' => '#2E6B3F'],
        ['name' => 'Connoisseur',   'min' => 600,  'emoji' => '🌿', 'color' => '#B87333'],
        ['name' => 'Gourmand',      'min' => 1500, 'emoji' => '🍷', 'color' => '#C9A227'],
        ['name' => 'Chef’s Table', 'min' => 3500, 'emoji' => '🔥', 'color' => '#A83A12'],
    ];
    $cur = $levels[0]; $next = $levels[1] ?? null;
    for ($i = 0; $i < count($levels); $i++) {
        if ($xp >= $levels[$i]['min']) { $cur = $levels[$i]; $next = $levels[$i + 1] ?? null; }
    }
    $minXp = $cur['min'];
    $maxXp = $next ? $next['min'] : $cur['min'] + 1000;
    $pct = $maxXp === $minXp ? 100 : min(100, max(0, floor(($xp - $minXp) / ($maxXp - $minXp) * 100)));
    return [
        'name' => $cur['name'], 'emoji' => $cur['emoji'], 'color' => $cur['color'],
        'next_name' => $next ? $next['name'] : null, 'next_min' => $next ? $next['min'] : null,
        'min_xp' => $minXp, 'max_xp' => $maxXp, 'progress' => $pct,
    ];
}

function game_badge_defs(): array
{
    return [
        'first_order'  => ['name' => 'First Bite',      'emoji' => '🍽️', 'desc' => 'Your first order from our kitchen'],
        'five_orders'  => ['name' => 'Five-Course',     'emoji' => '🥘', 'desc' => '5 orders — you’re part of the family'],
        'ten_orders'   => ['name' => 'House Favorite',  'emoji' => '⭐', 'desc' => '10 orders — you know every dish'],
        'big_spender'  => ['name' => 'Grand Feast',     'emoji' => '🥩', 'desc' => '₦50,000 lifetime — chef’s compliments'],
        'streak_3'     => ['name' => 'Three Days Hot',  'emoji' => '🌶️', 'desc' => 'Ordered 3 days straight — keep it up'],
        'streak_7'     => ['name' => 'Weekly Ritual',   'emoji' => '🌿', 'desc' => '7 days in a row — a true culinary ritual'],
        'explorer'     => ['name' => 'Flavor Journey',  'emoji' => '🧺', 'desc' => 'Tasted 6 different categories'],
        'spinner'      => ['name' => 'Daily Treat',     'emoji' => '🍬', 'desc' => 'Claimed your Daily Kitchen Reward'],
        'vip'          => ['name' => 'Chef’s Table',  'emoji' => '🍷', 'desc' => 'Reached Gourmand tier — our highest honor'],
    ];
}

function game_maybe_unlock(array &$data, string $badge): bool
{
    if (in_array($badge, $data['badges'], true)) return false;
    $data['badges'][] = $badge;
    return true;
}

function game_reward_choices(): array
{
    return [
        ['id' => 'p50',   'label' => '50 Points',  'weight' => 28, 'run' => function (&$d) { $d['points'] += 50;  $d['xp'] += 50;  return '+50 reward points — on the house.'; }],
        ['id' => 'p150',  'label' => '150 Points', 'weight' => 18, 'run' => function (&$d) { $d['points'] += 150; $d['xp'] += 150; return '+150 reward points — chef’s compliments.'; }],
        ['id' => 'p300',  'label' => '300 Points', 'weight' => 10, 'run' => function (&$d) { $d['points'] += 300; $d['xp'] += 300; return '+300 reward points — a feast of a reward!'; }],
        ['id' => 'badge', 'label' => 'Milestone',  'weight' => 14, 'run' => function (&$d) { game_maybe_unlock($d, 'spinner'); return 'Milestone unlocked: Daily Treat 🍬'; }],
        ['id' => 'free_drink', 'label' => 'Free Drink', 'weight' => 8, 'run' => function (&$d) { $d['lucky_reward'] = 'free_drink'; $d['xp'] += 40; return 'A FREE drink on your next order — ask for it!'; }],
        ['id' => 'try_tomorrow', 'label' => 'Visit Soon', 'weight' => 14, 'run' => function (&$d) { $d['xp'] += 10; return 'Come back tomorrow — +10 tasting notes for stopping in.'; }],
        ['id' => 'bonus', 'label' => 'Chef’s Surprise', 'weight' => 8, 'run' => function (&$d) { $d['xp'] += 500; return 'Chef’s surprise: +500 tasting notes!'; }],
    ];
}

function game_pick_reward(): array
{
    $choices = game_reward_choices();
    $total = 0; foreach ($choices as $c) $total += $c['weight'];
    $r = mt_rand(1, $total);
    $acc = 0;
    foreach ($choices as $c) { $acc += $c['weight']; if ($r <= $acc) return $c; }
    return $choices[0];
}

function game_state_for_client(PDO $pdo): array
{
    $data = game_get_raw($pdo);
    $lvl = game_level($data['xp']);
    $badges = [];
    $defs = game_badge_defs();
    foreach ($data['badges'] as $b) {
        if (isset($defs[$b])) $badges[] = ['id' => $b] + $defs[$b];
    }
    $today = date('Y-m-d');
    $unlockedIds = array_values($data['badges'] ?? []);
    return [
        'sid' => game_session_id(),
        'points' => (int)$data['points'],
        'xp' => (int)$data['xp'],
        'orders' => (int)$data['orders'],
        'streak' => (int)$data['streak_days'],
        'badges' => $badges,
        'badges_unlocked' => $unlockedIds,
        'badges_new' => [],
        'lucky_reward' => $data['lucky_reward'],
        'can_spin' => ($data['last_spin_date'] ?? '') !== $today,
        'can_spin_today' => ($data['last_spin_date'] ?? '') !== $today,
        'level' => $lvl,
    ];
}

function game_daily_spin(PDO $pdo): array
{
    $data = game_get_raw($pdo);
    $today = date('Y-m-d');
    if (($data['last_spin_date'] ?? '') === $today) {
        return ['ok' => false, 'message' => 'You already spun today — come back tomorrow!', 'state' => game_state_for_client($pdo)];
    }
    $reward = game_pick_reward();
    $msg = call_user_func($reward['run'], $data);
    $data['last_spin_date'] = $today;
    game_save_raw($pdo, $data);
    return [
        'ok' => true, 'reward_id' => $reward['id'], 'reward_label' => $reward['label'],
        'message' => $msg, 'state' => game_state_for_client($pdo),
    ];
}

function game_points_per_naira(): int { return 1; }

function game_reward_on_order(PDO $pdo, float $total, int $uniqueCats = 1): array
{
    $data = game_get_raw($pdo);
    $today = date('Y-m-d');
    $newBadges = [];
    $oldLevel = game_level($data['xp']);

    $pointsGained = (int)round(max(0, $total) * game_points_per_naira() / 50);
    if ($pointsGained <= 0) $pointsGained = 10;
    $data['points'] += $pointsGained;
    $data['xp']     += $pointsGained + 80;
    $data['total_spent'] += $total;
    $data['orders'] += 1;

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if (($data['last_order_date'] ?? null) === $today) {
        // same day: keep streak
    } elseif (($data['last_order_date'] ?? null) === $yesterday) {
        $data['streak_days'] = ($data['streak_days'] ?? 0) + 1;
    } else {
        $data['streak_days'] = 1;
    }
    $data['last_order_date'] = $today;

    if ($data['orders'] >= 1)  if (game_maybe_unlock($data, 'first_order'))  $newBadges[] = 'first_order';
    if ($data['orders'] >= 5)  if (game_maybe_unlock($data, 'five_orders'))  $newBadges[] = 'five_orders';
    if ($data['orders'] >= 10) if (game_maybe_unlock($data, 'ten_orders'))   $newBadges[] = 'ten_orders';
    if ($data['streak_days'] >= 3) if (game_maybe_unlock($data, 'streak_3')) $newBadges[] = 'streak_3';
    if ($data['streak_days'] >= 7) if (game_maybe_unlock($data, 'streak_7')) $newBadges[] = 'streak_7';
    if ($data['total_spent'] >= 50000) if (game_maybe_unlock($data, 'big_spender')) $newBadges[] = 'big_spender';
    if ($uniqueCats >= 6) if (game_maybe_unlock($data, 'explorer')) $newBadges[] = 'explorer';
    $lvl = game_level($data['xp']);
    if (($lvl['next_min'] === null || $lvl['min_xp'] >= 1500) && $lvl['min_xp'] >= 1500) {
        if (game_maybe_unlock($data, 'vip')) $newBadges[] = 'vip';
    }

    game_save_raw($pdo, $data);
    $leveledUp = $lvl['name'] !== $oldLevel['name'];

    $defs = game_badge_defs();
    $badgePayload = [];
    foreach ($newBadges as $nb) if (isset($defs[$nb])) $badgePayload[] = ['id' => $nb] + $defs[$nb];

    return [
        'points_gained' => $pointsGained,
        'new_badges' => $badgePayload,
        'leveled_up' => $leveledUp,
        'level_name' => $lvl['name'],
        'state' => game_state_for_client($pdo),
    ];
}

function game_topbar_markup(PDO $pdo): string
{
    $g = game_state_for_client($pdo);
    $lvl = $g['level'];
    $json = json_encode($g, JSON_UNESCAPED_UNICODE);
    ob_start();
?><a class="game-pill" href="<?= e(base_url('#rewards')) ?>" title="Loyalty rewards" data-game-pill data-state="<?= e($json) ?>" aria-label="Rewards">
  <span class="gp-level" style="--lc:<?= e($lvl['color']) ?>"><?= e($lvl['emoji']) ?></span>
  <span class="gp-meta">
    <span class="gp-lvl-name"><?= e($lvl['name']) ?></span>
    <span class="gp-stats"><b data-game-points><?= (int)$g['points'] ?></b> pts · 🔥 <b data-game-streak><?= (int)$g['streak'] ?></b></span>
  </span>
</a><?php
    return ob_get_clean();
}
