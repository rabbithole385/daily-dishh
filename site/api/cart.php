<?php
/**
 * Cart endpoint. Works two ways:
 *  - fetch() from assets/js/app.js  -> JSON response
 *  - plain form post (no JS)        -> redirect back
 */
require_once __DIR__ . '/../includes/config.php';

$back = $_POST['back'] ?? base_url('menu.php');
if (!is_string($back) || !str_starts_with($back, '/')) {
    $back = base_url('menu.php');
}

function respond(PDO $pdo, bool $ok, string $message, string $back, int $itemId = 0): void
{
    if (is_ajax()) {
        header('Content-Type: application/json');
        $cart = cart_get();
        $sub = cart_subtotal($pdo);
        echo json_encode([
            'ok' => $ok,
            'message' => $message,
            'count' => cart_count(),
            'qty' => $itemId ? ($cart[$itemId] ?? 0) : 0,
            'subtotal' => $sub,
            'subtotal_text' => money($sub, $pdo),
        ]);
        exit;
    }
    if ($message) flash_set($ok ? 'success' : 'error', $message);
    header('Location: ' . $back);
    exit;
}

if (($_GET['view'] ?? '') === 'json') {
    header('Content-Type: application/json');
    $subtotal = cart_subtotal($pdo);
    $linesPayload = [];
    foreach (cart_items($pdo) as $line) {
        $linesPayload[] = [
            'name' => $line['item']['name'],
            'qty' => (int)$line['qty'],
            'line_total' => (float)$line['line_total'],
            'line_total_text' => money($line['line_total'], $pdo),
        ];
    }
    echo json_encode([
        'count' => cart_count(),
        'subtotal' => $subtotal,
        'subtotal_text' => money($subtotal, $pdo),
        'lines' => $linesPayload,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    respond($pdo, false, 'Your session expired. Refresh the page and try again.', $back);
}

$action = $_POST['action'] ?? 'add';
$itemId = (int)($_POST['item_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

switch ($action) {
    case 'add':
        if (!$item || !$item['is_available'] || $item['price'] === null) {
            respond($pdo, false, 'That dish can’t be ordered online right now. Call the kitchen to order it.', $back);
        }
        cart_add($itemId, max(1, min(20, (int)($_POST['qty'] ?? 1))));
        respond($pdo, true, $item['name'] . ' added to your order', $back, $itemId);
    case 'inc':
        $cur = cart_get()[$itemId] ?? 0;
        cart_set_qty($itemId, min(20, $cur + 1));
        respond($pdo, true, '', $back, $itemId);
    case 'dec':
        $cur = cart_get()[$itemId] ?? 0;
        cart_set_qty($itemId, $cur - 1);
        respond($pdo, true, $cur - 1 <= 0 ? 'Removed from your order' : '', $back, $itemId);
    case 'remove':
        cart_remove($itemId);
        respond($pdo, true, 'Removed from your order', $back, $itemId);
    case 'clear':
        cart_clear();
        respond($pdo, true, 'Your order was cleared', $back);
}
respond($pdo, false, 'Unknown action.', $back);
