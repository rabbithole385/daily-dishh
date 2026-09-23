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

function money(?float $amount, PDO $pdo = null): string
{
    if ($amount === null) {
        return 'Ask for price';
    }
    $symbol = '₦';
    if ($pdo !== null) {
        $symbol = get_setting($pdo, 'currency_symbol', '₦');
    }
    return $symbol . number_format($amount, 0);
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
    <div class="sheet-foot">
      <div class="stepper">
        <button type="button" data-sheet-dec aria-label="Fewer">−</button>
        <output data-sheet-qty>1</output>
        <button type="button" data-sheet-inc aria-label="More">+</button>
      </div>
      <button type="button" class="btn btn-primary sheet-add" data-sheet-add><span>Add to order</span><span data-sheet-total></span></button>
    </div>
    <p class="sheet-note">Spice level, allergies or protein choice? Add it in the notes at checkout.</p>
  </div>
</dialog>
<?php
    return ob_get_clean();
}
