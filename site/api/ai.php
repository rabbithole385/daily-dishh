<?php
/**
 * AI Errands — chat endpoint.
 * Accepts POST { message, csrf } -> JSON { reply, actions[], suggestions[] }
 *
 * Strategy (no Composer, PHP native HTTP only):
 *   1. Load menu items, categories, zones, hours, phones, WhatsApp.
 *   2. If an LLM provider + key is set, POST to OpenAI or Anthropic via
 *      file_get_contents + stream_context_create.
 *   3. Otherwise (or on failure), use a rule-based keyword engine.
 *   4. Always respond with the same JSON shape so the frontend never breaks.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* --------------------------- Conversation state (session scoped) --------------------------- */

function ai_state_get(): array {
    if (empty($_SESSION['ai_state'])) {
        $_SESSION['ai_state'] = [
            'last_items'      => [], // [{id,name,price,cat}] last shown picks, newest first
            'pending_qty'     => 1,
            'last_heat'       => null, // 'spicy'|'mild'|null
            'last_zone'       => null, // e.g. 'Wuse'
            'last_budget'     => 0,
            'slot'            => null, // reserved clarifier slot: 'clarify_zone' | 'clarify_people' etc
            'pending_people'  => 0,
        ];
    }
    return $_SESSION['ai_state'];
}
function ai_state_set(array $s): void { $_SESSION['ai_state'] = $s; }
function ai_state_last_item(?array $state = null): ?array {
    $s = $state ?? ai_state_get();
    return $s['last_items'][0] ?? null;
}
function ai_state_push_item(array $item, ?array &$state = null): void {
    if (empty($item['id'])) return;
    $s = &$state;
    if ($s === null) { $s = ai_state_get(); $byRef = true; }
    $prior = $s['last_items'] ?? [];
    $new   = [];
    foreach ($prior as $p) { if (($p['id'] ?? 0) !== (int)$item['id']) $new[] = $p; }
    array_unshift($new, [
        'id'    => (int)$item['id'],
        'name'  => (string)($item['name'] ?? ''),
        'price' => $item['price'] === null || $item['price'] === '' ? null : (float)$item['price'],
        'cat'   => (string)($item['category_name'] ?? ''),
    ]);
    $s['last_items'] = array_slice($new, 0, 5);
    if ($byRef ?? false) ai_state_set($s);
}

/* --------------------------- Helpers --------------------------- */

function ai_json($reply, array $actions = [], array $suggestions = [], ?array $state = null): void {
    if ($state !== null) ai_state_set($state);
    echo json_encode([
        'reply'       => (string)$reply,
        'actions'     => $actions,
        'suggestions' => $suggestions,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_rate_limit_check(): ?string {
    if (empty($_SESSION['ai_ratelimit'])) {
        $_SESSION['ai_ratelimit'] = [];
    }
    $now  = microtime(true);
    $cut  = $now - 600; // last 10 minutes
    $list = $_SESSION['ai_ratelimit'];
    $list = array_values(array_filter($list, function ($t) use ($cut) { return $t > $cut; }));
    if (count($list) >= 20) {
        $_SESSION['ai_ratelimit'] = $list;
        return 'Whoa, slow down chef! Give me a minute to restock my notebook — I’m a little swamped right now. Try again in a moment?';
    }
    $list[] = $now;
    $_SESSION['ai_ratelimit'] = $list;
    return null;
}

/** Collect a compact context string the LLM or rule engine can answer from. */
function ai_build_context(PDO $pdo): array {
    $siteName   = get_setting($pdo, 'site_name', 'Daily Dish Restaurant');
    $tagline    = get_setting($pdo, 'tagline', 'Your Everyday Delicacy');
    $address    = get_setting($pdo, 'address', '');
    $hours      = get_setting($pdo, 'hours', 'Mon – Sat: 9:00 AM – 6:00 PM');
    $hoursNote  = get_setting($pdo, 'hours_note', 'Closed Sundays');
    $phone1     = get_setting($pdo, 'phone_primary', '');
    $phone2     = get_setting($pdo, 'phone_secondary', '');
    $whatsapp   = get_setting($pdo, 'whatsapp', '');
    $eta        = get_setting($pdo, 'delivery_eta', '35–60 min');

    $zones = delivery_zones($pdo);
    $zonesStr = '';
    foreach ($zones as $name => $fee) {
        $zonesStr .= ($zonesStr ? ', ' : '') . $name . ' (' . money((float)$fee, $pdo, false) . ')';
    }

    $categoriesStmt = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order ASC, id ASC");
    $categories     = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsStmt = $pdo->query("SELECT mi.id, mi.name, mi.price, mi.description, mi.is_featured, mi.is_available, c.name AS category_name
                               FROM menu_items mi LEFT JOIN categories c ON c.id = mi.category_id
                               ORDER BY c.sort_order ASC, mi.sort_order ASC, mi.id ASC");
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $menuLines = [];
    $itemsByCat = [];
    foreach ($items as $it) {
        $cat = $it['category_name'] ?? 'Uncategorized';
        if (!isset($itemsByCat[$cat])) $itemsByCat[$cat] = [];
        $priceStr = $it['price'] === null || $it['price'] === ''
            ? 'price on request'
            : money((float)$it['price'], $pdo, false);
        $avail = empty($it['is_available']) ? ' [unavailable online]' : '';
        $feat  = !empty($it['is_featured']) ? ' ★FEATURED' : '';
        $desc  = !empty($it['description']) ? ' — ' . trim(preg_replace('/\s+/', ' ', (string)$it['description'])) : '';
        $menuLines[] = sprintf('#%d [%s] %s: %s%s%s%s',
            (int)$it['id'], $cat, $it['name'], $priceStr, $feat, $avail, $desc);
        $itemsByCat[$cat][] = $it;
    }

    return [
        'site' => [
            'name'       => $siteName,
            'tagline'    => $tagline,
            'address'    => $address,
            'hours'      => $hours,
            'hours_note' => $hoursNote,
            'phone1'     => $phone1,
            'phone2'     => $phone2,
            'whatsapp'   => $whatsapp,
            'eta'        => $eta,
        ],
        'zones_text'   => $zonesStr,
        'zones'        => $zones,
        'categories'   => $categories,
        'items'        => $items,
        'items_by_cat' => $itemsByCat,
        'menu_flat'    => implode("\n", $menuLines),
    ];
}

function ai_suggestions(array $bias = []): array {
    $pool = [
        'What’s today’s jollof like?',
        'Recommend something spicy 🌶️',
        'Show me Wuse delivery trays',
        'I’m on a budget — under ₦5k',
        'What grills do you have?',
        'Best family party tray',
        'Can I order a custom plate?',
    ];
    if (!empty($bias)) {
        $pool = array_merge($bias, $pool);
    }
    shuffle($pool);
    return array_slice(array_unique($pool), 0, 4);
}

function ai_action_link(string $label, string $url, bool $primary = true): array {
    return ['type' => 'link', 'label' => $label, 'url' => $url, 'primary' => $primary];
}
function ai_action_scroll(string $label, string $selector, bool $primary = true): array {
    return ['type' => 'scroll', 'label' => $label, 'selector' => $selector, 'primary' => $primary];
}
function ai_action_cart(string $label, int $itemId, int $qty = 1, bool $primary = true): array {
    return ['type' => 'add_to_cart', 'label' => $label, 'item_id' => $itemId, 'qty' => $qty, 'primary' => $primary];
}
function ai_action_wa(string $label, string $text, bool $primary = true): array {
    return ['type' => 'whatsapp', 'label' => $label, 'text' => $text, 'primary' => $primary];
}

/* --------------------------- Follow-up / multi-turn resolution --------------------------- */

/** Detects short follow-up answers against session state and returns a response BEFORE
 *  the keyword rule engine runs. Returns null if this is NOT a follow-up. */
function ai_followup_answer(string $msg, array $ctx, array $state): ?array {
    $m       = mb_strtolower($msg);
    $site    = $ctx['site'];
    $lastItem = ai_state_last_item($state);
    $pdo     = $GLOBALS['pdo'];

    // --- Pending clarification slot: zone ---
    if (!empty($state['slot'])) {
        if ($state['slot'] === 'clarify_zone') {
            $zones = $ctx['zones'];
            $hit = null;
            foreach (array_keys($zones) as $zn) {
                if (stripos($m, strtolower($zn)) !== false) { $hit = $zn; break; }
            }
            if ($hit) {
                $state['last_zone'] = $hit;
                $state['slot'] = null;
                $fee = $zones[$hit] ?? 0;
                $reply = "Got it — {$hit} delivery is " . money((float)$fee, $pdo) . ". Shall I put together a {$hit} tray? 👇";
                $feat = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) && !empty($i['is_featured'])));
                $actions = [];
                foreach (array_slice($feat, 0, 2) as $f) {
                    ai_state_push_item($f, $state);
                    if (!empty($f['price'])) $actions[] = ai_action_cart('Add ' . $f['name'], (int)$f['id']);
                }
                $actions[] = ai_action_wa('Order a ' . $hit . ' tray on WhatsApp', "Hi! I’m in {$hit} — I’d like to order a tray please.");
                $state['last_zone'] = $hit;
                return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions(["Jollof party tray", "Spicy grilled chicken + rice"]), 'state' => $state];
            }
            // Zone not recognised: WA handoff
            $state['slot'] = null;
            $waText = "Hi! I need delivery to {$msg}. What are my options?";
            return ['reply' => "I’ll check with dispatch for {$msg}. Quickest way is WhatsApp — tap the button below and they’ll confirm your area in under 2 minutes.",
                    'actions' => [ai_action_wa('Ask dispatch about ' . $msg, $waText)],
                    'suggestions' => ai_suggestions(['Show Gwarinpa menu', 'Show Wuse menu']), 'state' => $state];
        }
        if ($state['slot'] === 'clarify_people') {
            $n = 0;
            if (preg_match('/(\d+)/', $msg, $nm)) $n = (int)$nm[1];
            $wordNums = ['one'=>1,'two'=>2,'three'=>3,'four'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'ten'=>10,'twelve'=>12,'twenty'=>20];
            foreach ($wordNums as $w => $v) if (str_contains($m, $w)) { $n = $v; break; }
            if ($n > 0) {
                $state['pending_people'] = $n;
                $state['slot'] = null;
                // Build a meal plan for N people: 1 big jollof + protein + swallow/options
                $feat = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) && !empty($i['is_featured'])));
                $lines = []; $actions = [];
                $perPerson = 1;
                foreach (array_slice($feat, 0, 3) as $f) {
                    $qty = max(1, (int)ceil($n * $perPerson / 4));
                    $pText = $f['price'] === null || $f['price'] === '' ? 'price on request' : money((float)$f['price'] * $qty, $pdo);
                    $lines[] = "• {$f['name']} × {$qty} portions — {$pText}";
                    ai_state_push_item($f, $state);
                    if (!empty($f['price']) && count($actions) < 2) {
                        $actions[] = ai_action_cart('Add ' . $qty . '× ' . $f['name'], (int)$f['id'], $qty);
                    }
                    $perPerson *= 0.8;
                }
                $reply = "For {$n} people I’d plate it up like this (adjust portions as you like):\n" . implode("\n", $lines);
                $actions[] = ai_action_link('All family packs', base_url('menu.php#featured'), false);
                return ['reply' => $reply, 'actions' => $actions,
                        'suggestions' => ai_suggestions(['Add more protein', 'Add a soup + swallow']), 'state' => $state];
            }
            $state['slot'] = null;
            return ['reply' => "No problem — tell me a rough number (e.g. 6 people) and I’ll build the tray, or message the kitchen and they’ll size it for you.",
                    'actions' => [ai_action_wa('Ask kitchen to size a tray', "Hi! I need a food tray for a group.")],
                    'suggestions' => ai_suggestions(['For 4 people', 'For 10 people']), 'state' => $state];
        }
        if ($state['slot'] === 'clarify_heat') {
            if (preg_match('/spicy|hot|pepper|very|much pepper|high heat/', $m))       $state['last_heat'] = 'spicy';
            elseif (preg_match('/mild|no pepper|less|gentle|medium|not.*spicy/', $m)) $state['last_heat'] = 'mild';
            $state['slot'] = null;
            $heat = $state['last_heat'] ?? 'medium';
            $li = $lastItem;
            if ($li && !empty($li['price'])) {
                $sug = ["Make it {$heat} and add", "Add plantain too"];
                return ['reply' => "Locked in — {$heat} heat on the {$li['name']}. Anything else before I send it to the kitchen?",
                        'actions' => [ai_action_cart('Send ' . $li['name'] . ' (' . $heat . ')', (int)$li['id'])],
                        'suggestions' => ai_suggestions($sug), 'state' => $state];
            }
            return ['reply' => "Got it. When you pick your dish I’ll flag it {$heat} for the chef.",
                    'actions' => [ai_action_link('Browse menu', base_url('menu.php'))],
                    'suggestions' => ai_suggestions(["What's spicy today?"]), 'state' => $state];
        }
    }

    // --- Short: yes / ok / add it / done / send it ---
    if ($lastItem && preg_match('/^(yes|yeah|yep|ok|okay|sure|alright|done|send it|add it|make it that|i( will|’ll|' . "'" . 'll)? take (it|that)|that( one)? sounds? good|i want (it|that)|go ahead)$/i', trim($msg))) {
        if (!empty($lastItem['price'])) {
            return ['reply' => "Boom — {$lastItem['name']} going in. 🧺 Want me to double it or add a side?",
                    'actions' => [
                        ai_action_cart('Add ' . $lastItem['name'], (int)$lastItem['id']),
                        ai_action_link('View my order', base_url('cart.php'), false),
                    ],
                    'suggestions' => ai_suggestions(['2 portions please', 'Add a drink', 'Add plantain']), 'state' => $state];
        }
    }

    // --- Quantity follow-ups: "2 portions", "make it 3", "add 2 of those", "double it" ---
    $qty = 0;
    if (preg_match('/(?:add\s+)?(\d+)\s*(?:portions?|plates?|servings?|qty|pieces?|times?|of\s+those|of\s+that)/i', $msg, $qm)) $qty = (int)$qm[1];
    elseif (preg_match('/make\s+(?:it\s+)?(\d+)/i', $msg, $qm)) $qty = (int)$qm[1];
    elseif (preg_match('/(\d+)\s*(?:$|[!?.])/', $msg, $qm) && strlen(trim($msg)) < 18) $qty = (int)$qm[1];
    elseif (preg_match('/double\s*(?:it|that)?/i', $msg)) $qty = 2;
    elseif (preg_match('/triple/i', $msg)) $qty = 3;
    if ($qty > 0 && $lastItem) {
        $qty = max(1, min(20, $qty));
        $actions = [];
        if (!empty($lastItem['price'])) {
            $total = money((float)$lastItem['price'] * $qty, $pdo);
            $actions[] = ai_action_cart("Add {$qty}× {$lastItem['name']}", (int)$lastItem['id'], $qty);
            $reply = "{$qty} portions of {$lastItem['name']} — that comes to {$total}. Ready?";
        } else {
            $reply = "Got it — {$qty}× {$lastItem['name']}. Price on request, so I’ll flag that with the kitchen.";
            $actions[] = ai_action_wa("Confirm {$qty}× {$lastItem['name']}", "Hi! I want {$qty} portions of {$lastItem['name']} please.");
        }
        return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions(['Add a soup', 'Add a side of plantain']), 'state' => $state];
    }

    // --- Heat / spice follow-up: "spicy", "less spicy", "mild", "make it hot" ---
    if ($lastItem && preg_match('/^(make\s+(it\s+)?)?(very\s+)?(spicy|hot|peppery)|less\s+spicy|mild|medium|no\s+pepper|no\s+spice$/i', trim($msg)) ||
        (preg_match('/(spicy|hot|pepper|less\s+spicy|mild|no\s+pepper|heat)/', $m) && $lastItem && strlen(trim($msg)) < 50)) {
        $heat = 'medium';
        if (preg_match('/less|mild|no pepper|gentle/', $m)) $heat = 'mild';
        elseif (preg_match('/very|extreme|extra/', $m)) $heat = 'extra-spicy';
        elseif (preg_match('/spicy|hot|pepper/', $m)) $heat = 'spicy';
        $state['last_heat'] = $heat;
        $sug = $heat === 'spicy' || $heat === 'extra-spicy' ? ['Pair with jollof rice', 'Add a cold drink'] : ['Pair with egusi soup', 'Add plantain'];
        $actions = [];
        if (!empty($lastItem['price'])) $actions[] = ai_action_cart("Send {$heat} {$lastItem['name']}", (int)$lastItem['id']);
        else $actions[] = ai_action_wa("Order {$heat} {$lastItem['name']}", "Hi! {$heat} {$lastItem['name']} please.");
        return ['reply' => "Noted — {$heat} on the {$lastItem['name']} 🧺. I’ll scribble it on the docket for the chef.",
                'actions' => $actions, 'suggestions' => ai_suggestions($sug), 'state' => $state];
    }

    // --- Pairing / side request: "what side", "pair with", "add plantain", "add drink" ---
    if (preg_match('/(what\s+side|pair\s+with|goes?\s+with|add\s+(a\s+)?(side|plantain|drink|soup|rice|swallow))/', $m) ||
        (preg_match('/^add\s+(a\s+)?(plantain|chips|drink|soup|rice|swallow|salad)\s*$/i', trim($msg)))) {
        $cat = null;
        if (preg_match('/plantain|chips|side|salad/', $m)) $cat = ['Sides'];
        elseif (preg_match('/drink|juice|soft|zobo|kunu/', $m))   $cat = ['Drinks'];
        elseif (preg_match('/soup|swallow/', $m))                    $cat = ['Soups & Swallow'];
        elseif (preg_match('/rice/', $m))                            $cat = ['Rice & Bowls'];
        $candidates = array_values(array_filter($ctx['items'], function ($i) use ($cat) {
            if (empty($i['is_available'])) return false;
            if ($cat === null) return true;
            return in_array(trim($i['category_name'] ?? ''), $cat, true) ||
                   in_array(strtolower(trim($i['category_name'] ?? '')), array_map('strtolower', $cat), true);
        }));
        if (!$candidates && $lastItem) {
            // heuristic: anything in a DIFFERENT category than last item
            $exclude = strtolower($lastItem['cat'] ?? '');
            $candidates = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) && strcasecmp(trim($i['category_name'] ?? ''), $exclude) !== 0));
        }
        if (!$candidates) $candidates = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available'])));
        $lines = []; $actions = [];
        foreach (array_slice($candidates, 0, 3) as $c) {
            $pText = $c['price'] === null || $c['price'] === '' ? 'ask kitchen' : money((float)$c['price'], $pdo);
            $lines[] = "• {$c['name']} ({$c['category_name']}) — {$pText}";
            ai_state_push_item($c, $state);
            if (!empty($c['price']) && count($actions) < 2) $actions[] = ai_action_cart('Add ' . $c['name'], (int)$c['id']);
        }
        $reply = "A perfect match for the table:\n" . implode("\n", $lines);
        if ($lastItem) $reply = "With your {$lastItem['name']}? " . lcfirst($reply);
        return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions(['Add the first one', 'Send everything above']), 'state' => $state];
    }

    // --- Price check: "how much is that", "price", "total" ---
    if ($lastItem && preg_match('/^(how\s+much|price|what.*cost|total|how\s+many\s+naira)/i', trim($msg)) ||
        ($lastItem && (str_contains($m, 'how much') || str_contains($m, 'price of that')))) {
        if (!empty($lastItem['price'])) {
            $reply = "{$lastItem['name']} is " . money((float)$lastItem['price'], $pdo) . ". Two portions would be " . money((float)$lastItem['price'] * 2, $pdo) . ".";
            return ['reply' => $reply,
                    'actions' => [ai_action_cart('Add ' . $lastItem['name'], (int)$lastItem['id']),
                                  ai_action_cart('Add 2× ' . $lastItem['name'], (int)$lastItem['id'], 2, false)],
                    'suggestions' => ai_suggestions(['Add 2 portions', 'Add a side']), 'state' => $state];
        }
    }

    // --- Cart summary: "what's in my cart", "my order", "cart summary" ---
    if (preg_match('/(what.*my\s+cart|my\s+order|cart\s+(summary|items|content)|show\s+me\s+my\s+order|checkout\s+summary)/', $m)) {
        $items = cart_items($GLOBALS['pdo']);
        if (!$items) {
            return ['reply' => "Cart’s empty chef. Pick your first dish and I’ll build it with you.",
                    'actions' => [ai_action_link('Browse menu', base_url('menu.php'))],
                    'suggestions' => ai_suggestions(["What's good today?"]), 'state' => $state];
        }
        $sub = cart_subtotal($GLOBALS['pdo']);
        $lines = [];
        foreach ($items as $ln) {
            $lines[] = "• {$ln['qty']}× {$ln['item']['name']} — " . money((float)$ln['line_total'], $pdo);
        }
        return ['reply' => "Here’s your docket:\n" . implode("\n", $lines) . "\nSubtotal: " . money($sub, $pdo) . ". Ready to head to checkout?",
                'actions' => [ai_action_link('Review & checkout', base_url('cart.php')),
                              ai_action_link('Full checkout', base_url('checkout.php'), false)],
                'suggestions' => ai_suggestions(['Go to checkout', 'Add one more dish']), 'state' => $state];
    }

    // --- Clear cart: "clear my cart", "start over" ---
    if (preg_match('/(clear|empty|reset|start\s+over).*(cart|order)/', $m)) {
        cart_clear();
        return ['reply' => "Cart wiped clean. 🧺 Fresh start — what are we cooking up today?",
                'actions' => [ai_action_link('Menu', base_url('menu.php'))],
                'suggestions' => ai_suggestions(["What's hot today?"]), 'state' => $state];
    }

    // --- Meal plan request: "food for N people", "feeding 4", "for 6 people" ---
    if (preg_match('/(?:for|feeding|food\s+for)\s+(\d+)\s*(?:people|persons|guests|pax)?/i', $msg, $nm) ||
        preg_match('/(\d+)\s*(?:people|persons|guests|pax)/i', $msg, $nm)) {
        $n = (int)$nm[1];
        if ($n >= 2 && $n <= 100) {
            $feat = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) && !empty($i['is_featured'])));
            if (!$feat) $feat = array_slice(array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']))), 0, 3);
            $lines = []; $actions = [];
            foreach (array_slice($feat, 0, 3) as $f) {
                $qty = max(1, (int)ceil($n / 3));
                $pText = $f['price'] === null || $f['price'] === '' ? 'price on request' : money((float)$f['price'] * $qty, $pdo);
                $lines[] = "• {$qty}× {$f['name']} — {$pText}";
                ai_state_push_item($f, $state);
                if (!empty($f['price']) && count($actions) < 2) {
                    $actions[] = ai_action_cart("Add {$qty}× {$f['name']}", (int)$f['id'], $qty);
                }
            }
            return ['reply' => "Feeding {$n}? Sorted. My go-to spread:\n" . implode("\n", $lines) . "\nAnything to swap?",
                    'actions' => $actions + (!empty($site['whatsapp']) ? [ai_action_wa('Talk catering for ' . $n, "Hi! I need food for {$n} people — let’s plan it.", false)] : []),
                    'suggestions' => ai_suggestions(['More protein please', 'Make it spicy', 'Add a soup option']), 'state' => $state];
        }
        // Large catering: WhatsApp handoff
        if ($n > 100) {
            $waText = "Hi Daily Dish! Catering enquiry — {$n} guests.";
            $state['slot'] = null;
            return ['reply' => "Big crowd! For {$n}+ guests the kitchen will build you a custom menu. WhatsApp the catering team directly — they’ll have numbers for you in 10 minutes.",
                    'actions' => [ai_action_wa('Catering enquiry — ' . $n . ' guests', $waText)],
                    'suggestions' => ai_suggestions(), 'state' => $state];
        }
    }

    // --- Budget meal plan: "I have N naira, feed me" / "N naira order" ---
    if (preg_match('/(?:budget|have|with|for|i\s+want|make\s+(?:it\s+)?me\s+an?\s+order\s+of)\s*[:=]?\s*n?\s*(\d{1,3}(?:[.,]\d{3})*)\s*(?:naira|₦)?/ui', $msg, $bm) ||
        preg_match('/(?:under|below|less\s+than)\s+[:=]?\s*n?\s*(\d{1,3}(?:[.,]\d{3})*)\s*(?:naira|₦)?/ui', $msg, $bm)) {
        $budget = (int)preg_replace('/[.,]/', '', $bm[1]);
        if ($budget >= 1000) {
            $state['last_budget'] = $budget;
            $pool = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) && $i['price'] !== null && $i['price'] !== '' && (float)$i['price'] <= $budget));
            // Pack a combo (staple + protein + side) within budget
            $combo = []; $total = 0; $actions = [];
            usort($pool, fn($a,$b) => (float)$b['price'] <=> (float)$a['price']);
            $categoriesSeen = [];
            foreach ($pool as $c) {
                $cat = trim($c['category_name'] ?? 'misc');
                if (in_array($cat, $categoriesSeen, true) && count($combo) >= 1) continue;
                $p = (float)$c['price'];
                if ($total + $p > $budget) continue;
                $combo[] = $c; $total += $p; $categoriesSeen[] = $cat;
                ai_state_push_item($c, $state);
                if (count($actions) < 2) $actions[] = ai_action_cart('Add ' . $c['name'], (int)$c['id']);
                if (count($combo) >= 3) break;
            }
            if (!$combo) {
                return ['reply' => "For " . money($budget, $pdo) . " your best bet is WhatsApp the kitchen — they’ll rustle up a warm combo just for you.",
                        'actions' => [ai_action_wa('Budget order ' . money($budget, $pdo, false), "Hi! I want a warm meal for " . money($budget, $pdo, false) . ".")],
                        'suggestions' => ai_suggestions(), 'state' => $state];
            }
            $lines = [];
            foreach ($combo as $c) $lines[] = "• {$c['name']} — " . money((float)$c['price'], $pdo);
            $lines[] = "— Total combo: " . money($total, $pdo) . " (" . money($budget - $total, $pdo) . " left)";
            $waText = "Hi! Budget order " . money($budget, $pdo, false) . ": " . implode(', ', array_map(fn($c) => $c['name'], $combo));
            return ['reply' => "For " . money($budget, $pdo) . " I built this:\n" . implode("\n", $lines) . "\nSwap anything?",
                    'actions' => $actions + [ai_action_wa('Send this combo', $waText, false)],
                    'suggestions' => ai_suggestions(['Swap protein', 'Add spice', 'Looks good — add it']), 'state' => $state];
        }
    }

    // --- Proactive clarification question triggers ---
    if (preg_match('/(deliver(?:y|ed)?|send)\s+(it|food|order|tray|meal)/', $m) && empty($state['last_zone'])) {
        $state['slot'] = 'clarify_zone';
        return ['reply' => "Where are we taking it? Abuja zones we serve: {$ctx['zones_text']}.",
                'actions' => [], 'suggestions' => ai_suggestions(['Wuse', 'Gwarinpa', 'Jabi', 'Kubwa']), 'state' => $state];
    }
    if (preg_match('/(party|crowd|group|event|birthday|meeting|office)/', $m) && empty($state['pending_people'])) {
        $state['slot'] = 'clarify_people';
        return ['reply' => "How many people are we feeding? I’ll size the tray accordingly.",
                'actions' => [], 'suggestions' => ai_suggestions(['4 people', '10 people', '20 people']), 'state' => $state];
    }
    if ($lastItem && preg_match('/(order|send|add)\s+spicy|(make|do)\s+it\s+spicy/', $m) && empty($state['last_heat'])) {
        $state['slot'] = 'clarify_heat';
        return ['reply' => "Spicy level? Mild (just a tickle), medium, or face-melter 🌶️?",
                'actions' => [], 'suggestions' => ai_suggestions(['Mild', 'Medium', 'Spicy', 'Extra spicy']), 'state' => $state];
    }

    return null;
}

/* --------------------------- Rule engine fallback --------------------------- */
/** Keyword-based answers — always available, no API key needed. */
function ai_rule_answer(string $msg, array $ctx): array {
    $m    = mb_strtolower($msg);
    $site = $ctx['site'];
    $state = ai_state_get();
    $pdo  = $GLOBALS['pdo'];

    // Prices / jollof / spicy / recommendations
    $itemMatches = [];
    foreach ($ctx['items'] as $it) {
        if (empty($it['is_available'])) continue;
        $hay = mb_strtolower($it['name'] . ' ' . ($it['description'] ?? '') . ' ' . ($it['category_name'] ?? ''));
        $score = 0;
        foreach (preg_split('/\s+/u', preg_replace('/[^a-z0-9 ]+/u', ' ', $m)) as $w) {
            if ($w === '' || mb_strlen($w) < 2) continue;
            if (str_contains($hay, $w)) $score += mb_strlen($w);
        }
        if ($score > 0) $itemMatches[] = ['score' => $score, 'item' => $it];
    }
    usort($itemMatches, function ($a, $b) { return $b['score'] - $a['score']; });
    $top = array_slice($itemMatches, 0, 3);

    $wantSpicy   = str_contains($m, 'spicy') || str_contains($m, 'pepper') || str_contains($m, 'hot');
    $wantBudget  = preg_match('/(under|below|less than|cheap|budget|affordable|only)\s*[:=]?\s*n?\s*(\d{1,3}(?:[.,]\d{3})*)/u', $m, $bm);
    $budgetNaira = 0;
    if ($wantBudget && !empty($bm[2])) {
        $budgetNaira = (int)preg_replace('/[.,]/', '', $bm[2]);
    }
    $wantJollof   = str_contains($m, 'jollof');
    $wantFamily   = str_contains($m, 'family') || str_contains($m, 'party') || str_contains($m, 'tray') || str_contains($m, 'platter');
    $wantGrill    = str_contains($m, 'grill') || str_contains($m, 'grilled') || str_contains($m, 'suya') || str_contains($m, 'barbecue') || str_contains($m, 'bbq');
    $wantSoup     = str_contains($m, 'soup') || str_contains($m, 'swallow') || str_contains($m, 'egusi') || str_contains($m, 'okra') || str_contains($m, 'ofe') || str_contains($m, 'stew');
    $wantRice     = str_contains($m, 'rice') || str_contains($m, 'fried rice');
    $wantPasta    = str_contains($m, 'pasta') || str_contains($m, 'spaghetti') || str_contains($m, 'noodle') || str_contains($m, 'macaroni');
    $wantChicken  = str_contains($m, 'chicken');
    $wantBeef     = str_contains($m, 'beef') || str_contains($m, 'meat');
    $wantSeafood  = str_contains($m, 'seafood') || str_contains($m, 'fish') || str_contains($m, 'prawn') || str_contains($m, 'shrimp');
    $wantVeg      = str_contains($m, 'vegetarian') || str_contains($m, 'veg') || str_contains($m, 'plant') || str_contains($m, 'vegan');
    $wantDrink    = str_contains($m, 'drink') || str_contains($m, 'juice') || str_contains($m, 'water') || str_contains($m, 'wine') || str_contains($m, 'soft');
    $wantCustom   = str_contains($m, 'custom') || str_contains($m, 'special') || str_contains($m, 'plate') || str_contains($m, 'personaliz');

    // Intent: greetings
    if (preg_match('/^(hi|hello|hey|good (morning|afternoon|evening)|howdy|hola|yo)\b/i', $msg)) {
        return [
            'reply' => "Chef’s kiss — welcome to the table! 🍽️ I’m Errands, and anything you need from our kitchen is granted. Take a look around our menu, ask me what’s hot today, or tell me where in Abuja you need delivery — I’m your guy.",
            'actions' => [
                ai_action_link('Browse the full menu', base_url('menu.php')),
                ai_action_scroll('See today’s specials', '#featured', false),
            ],
            'suggestions' => ai_suggestions(["What’s good today?", "Show featured dishes"]),
        ];
    }

    // Intent: delivery zones / where do you deliver
    if (str_contains($m, 'deliver') || str_contains($m, 'area') || str_contains($m, 'zone') || str_contains($m, 'wuse') || str_contains($m, 'gwarinpa') || str_contains($m, 'kubwa') || str_contains($m, 'jabi') || str_contains($m, 'location')) {
        $zonesText = $ctx['zones_text'] ?: 'Ask the kitchen for today’s delivery area list.';
        $reply = "We deliver hot across Abuja. Current zones and fees: {$zonesText}. ETA is roughly {$site['eta']}. Tell me your area and I’ll suggest the perfect tray for it.";
        $actions = [ai_action_link('Pick from the menu', base_url('menu.php#delivery'))];
        // If they mentioned a specific zone, highlight it
        if (str_contains($m, 'wuse')) {
            $reply = "Wuse delivery is sorted — fee is " . money($ctx['zones']['Wuse'] ?? 3500, $GLOBALS['pdo']) . ". Wuse loves our party jollof trays and spicy grilled foil packs. Shall I add a signature tray for you? 🌶️";
            $actions = array_merge($actions, [ai_action_wa('Talk Wuse orders on WhatsApp', "Hi! I’m in Wuse and I’d like to order a delivery tray.")]);
        }
        return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions(['Show Wuse delivery trays', 'How long to Gwarinpa?'])];
    }

    // Intent: hours / open / time
    if (str_contains($m, 'hour') || str_contains($m, 'open') || str_contains($m, 'close') || str_contains($m, 'time') || str_contains($m, 'today') && (str_contains($m, 'open') || str_contains($m, 'close'))) {
        $note = $site['hours_note'] ? " A quick note: {$site['hours_note']}." : '';
        return [
            'reply' => "Kitchen hours right now: {$site['hours']}.{$note} If we’re closed, drop your order on WhatsApp and we’ll fire it up first thing.",
            'actions' => $site['whatsapp'] ? [ai_action_wa('Pre-order on WhatsApp', "Hi, I’d like to pre-order for later today.")] : [],
            'suggestions' => ai_suggestions(["Can I pre-order for 7pm?"]),
        ];
    }

    // Intent: address / visit / find us
    if (str_contains($m, 'address') || str_contains($m, 'visit') || str_contains($m, 'find us') || str_contains($m, 'located') || str_contains($m, 'where are you')) {
        $addr = $site['address'] ?: "Our kitchen is in Gwarinpa, Abuja — call or WhatsApp for the exact gate.";
        return [
            'reply' => "Come through! {$addr} Call before you swing by and we’ll have a cold drink waiting.",
            'actions' => array_filter([
                $site['phone1'] ? ai_action_link('Call the kitchen', 'tel:' . preg_replace('/[^0-9+]/', '', $site['phone1']), false) : null,
                ai_action_link('See map & contact page', base_url('contact.php')),
            ]),
            'suggestions' => ai_suggestions(),
        ];
    }

    // Intent: contact / phone / whatsapp / call
    if (str_contains($m, 'phone') || str_contains($m, 'call') || str_contains($m, 'whatsapp') || str_contains($m, 'contact') || str_contains($m, 'reach')) {
        $lines = [];
        if ($site['phone1']) $lines[] = "📞 Primary: {$site['phone1']}";
        if ($site['phone2']) $lines[] = "📞 Backup: {$site['phone2']}";
        if ($site['whatsapp']) $lines[] = "💬 WhatsApp: {$site['whatsapp']} (fastest!)";
        $reply = "Here’s how to reach us — and remember, I can put you straight through: " . implode(" · ", $lines) . ".";
        $actions = array_filter([
            $site['whatsapp'] ? ai_action_wa('Message the kitchen', "Hi Daily Dish!") : null,
            $site['phone1'] ? ai_action_link('Call now', 'tel:' . preg_replace('/[^0-9+]/', '', $site['phone1']), false) : null,
        ]);
        return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions()];
    }

    // Intent: track order
    if (str_contains($m, 'track') || str_contains($m, 'where is my order') || str_contains($m, 'status of my order') || str_contains($m, 'order code')) {
        return [
            'reply' => "Right this way — pop your order code on the tracking page and I’ll show you exactly where your food is: from the kitchen 📋 to confirmed ✅ to cooking 👨‍🍳 to on the bike 🛵 to your door 🍴.",
            'actions' => [ai_action_link('Track my order', base_url('track.php'))],
            'suggestions' => ai_suggestions(["What do the stages mean?"]),
        ];
    }

    // Intent: checkout / pay / payment method
    if (str_contains($m, 'checkout') || str_contains($m, 'pay') || str_contains($m, 'payment') || str_contains($m, 'bank transfer') || str_contains($m, 'transfer')) {
        return [
            'reply' => "We keep it simple at checkout: cash on delivery/pickup, bank transfer on delivery, or instant transfer now (account details shown on the checkout page). Your name, phone, address and zone are remembered between visits — one tap and you’re done.",
            'actions' => [ai_action_link('Go to checkout', base_url('checkout.php'))],
            'suggestions' => ai_suggestions(),
        ];
    }

    // Intent: loyalty / rewards / spin / points / streak
    if (str_contains($m, 'reward') || str_contains($m, 'point') || str_contains($m, 'streak') || str_contains($m, 'spin') || str_contains($m, 'badge') || str_contains($m, 'loyalty') || str_contains($m, 'daily treat')) {
        return [
            'reply' => "Our loyalty program is designed like a true Kitchen Table regular: points, streaks, badges, and a free daily spin. 🎰 Every naira spent = points, three days ordering builds a streak, and when you unlock milestones — free drinks, bonus XP, the works. Give the wheel a spin every day.",
            'actions' => [ai_action_scroll('Spin the daily wheel', '#rewards'), ai_action_link('See how it works', base_url('about.php#loyalty'))],
            'suggestions' => ai_suggestions(["Spin for me right now", "What badges can I earn?"]),
        ];
    }

    // Intent: about / story / who are you
    if (str_contains($m, 'about') || str_contains($m, 'who are you') || str_contains($m, 'your story') || str_contains($m, 'tell me about') && str_contains($m, 'daily dish')) {
        return [
            'reply' => "Daily Dish is Abuja’s home-style kitchen, tucked right here in Gwarinpa. We cook Nigerian soups and swallow, party jollof trays, smoky grills and continental favourites — everything we’d put on our own table, delivered hot across the city. I’m Errands, your errand boy on the inside. Anything you need, it’s granted.",
            'actions' => [ai_action_link('Read the full story', base_url('about.php'))],
            'suggestions' => ai_suggestions(["Show me the chef’s signature"]),
        ];
    }

    // Intent: recommend / suggestion / what should I order / what's good
    if (str_contains($m, 'recommend') || str_contains($m, 'suggest') || preg_match('/what should (i|we)\b/', $m) || str_contains($m, 'what’s good') || str_contains($m, "what's good") || str_contains($m, 'favourite') || str_contains($m, 'best') || str_contains($m, 'signature') || str_contains($m, 'popular')) {
        $reply = "My handpicked picks — these fly out the kitchen:";
        $picks = [];
        // Prefer featured; otherwise top by availability
        $feat = array_filter($ctx['items'], fn($i) => !empty($i['is_featured']) && !empty($i['is_available']));
        if (!$feat) $feat = array_filter($ctx['items'], fn($i) => !empty($i['is_available']));
        foreach (array_slice(array_values($feat), 0, 3) as $p) {
            $price = $p['price'] === null || $p['price'] === '' ? 'price on request' : money((float)$p['price'], $pdo);
            $picks[] = "• {$p['name']} ({$p['category_name']}): {$price}";
            ai_state_push_item($p, $state);
        }
        if (empty($picks)) {
            return [
                'reply' => "The menu’s still being written up — swing by the menu page or WhatsApp the kitchen for today’s board.",
                'actions' => [ai_action_link('See the menu', base_url('menu.php'))],
                'suggestions' => ai_suggestions(),
                'state'   => $state,
            ];
        }
        $actions = [];
        foreach (array_slice($feat, 0, 2) as $p) {
            if ($p['price'] !== null && $p['price'] !== '') {
                $actions[] = ai_action_cart('Add ' . $p['name'], (int)$p['id'], 1, count($actions) === 0);
            }
        }
        $actions[] = ai_action_link('All recommendations', base_url('menu.php#featured'), false);
        return [
            'reply'       => $reply . "\n" . implode("\n", $picks),
            'actions'     => $actions,
            'suggestions' => ai_suggestions(["Something less spicy please", "Pair with a drink?"]),
            'state'       => $state,
        ];
    }

    // Intent: drinks
    if ($wantDrink) {
        $drinks = array_filter($ctx['items'], fn($i) => !empty($i['is_available']) && (
            preg_match('/drink|juice|water|wine|soft|zobo|kunu|smoothie|malt|cola|coke|sprite|fanta/i',
                $i['name'] . ' ' . ($i['description'] ?? '') . ' ' . ($i['category_name'] ?? ''))
        ));
        if ($drinks) {
            $reply = "Thirsty? I’ve got you:";
            $actions = [];
            foreach (array_slice(array_values($drinks), 0, 3) as $d) {
                $price = $d['price'] === null || $d['price'] === '' ? 'price on request' : money((float)$d['price'], $pdo);
                $reply .= "\n• {$d['name']} — {$price}";
                ai_state_push_item($d, $state);
                if ($d['price'] !== null && $d['price'] !== '' && count($actions) < 2) {
                    $actions[] = ai_action_cart('Add ' . $d['name'], (int)$d['id']);
                }
            }
            return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions(), 'state' => $state];
        }
    }

    // Category-based suggestions
    $categoryBiases = [
        [$wantJollof || $wantRice,  'Rice & Bowls', "Rice dishes:", 'menu.php#cat-rice-bowls'],
        [$wantSoup,                 'Soups & Swallow', "Our soups and swallow:", 'menu.php#cat-soups-swallow'],
        [$wantGrill,                'Grills', "Grills and smoky things:", 'menu.php#cat-grills'],
        [$wantPasta,                'Pasta & Noodles', "Pasta & noodles:", 'menu.php#cat-pasta'],
        [$wantChicken,              null, "Chicken picks:", 'menu.php'],
        [$wantBeef,                 null, "Beef and meat-forward:", 'menu.php'],
        [$wantSeafood,              null, "Seafood from the menu:", 'menu.php'],
        [$wantVeg,                  null, "Vegetarian-friendly picks:", 'menu.php'],
        [$wantFamily,               null, "Party trays and family packs:", 'menu.php#featured'],
    ];
    foreach ($categoryBiases as $bias) {
        [$match, $catName, $prefix, $anchor] = $bias;
        if (!$match) continue;
        $candidates = $top;
        if ($catName) {
            $candidates = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) &&
                0 === strcasecmp(trim($i['category_name'] ?? ''), trim($catName))));
        }
        if (!$candidates && $catName) {
            $candidates = array_values(array_filter($ctx['items'], fn($i) => !empty($i['is_available']) &&
                stripos($i['category_name'] ?? '', $catName) !== false));
        }
        if (!$candidates) $candidates = array_filter($ctx['items'], fn($i) => !empty($i['is_available']));
        $lines = [];
        $actions = [];
        foreach (array_slice(array_values($candidates), 0, 3) as $it) {
            $p = $it['price'] === null || $it['price'] === '' ? 'price on request' : money((float)$it['price'], $pdo);
            $lines[] = "• {$it['name']} — {$p}";
            ai_state_push_item($it, $state);
            if (($it['price'] !== null && $it['price'] !== '') && count($actions) < 2) {
                $actions[] = ai_action_cart('Add ' . $it['name'], (int)$it['id'], 1, count($actions) === 0);
            }
        }
        $actions[] = ai_action_link('See full section', base_url($anchor), false);
        if ($wantBudget && $budgetNaira > 0) {
            $lines = [];
            $budgetItems = array_filter($ctx['items'], function ($i) use ($budgetNaira) {
                if (empty($i['is_available']) || $i['price'] === null || $i['price'] === '') return false;
                return (float)$i['price'] <= $budgetNaira;
            });
            foreach (array_slice(array_values($budgetItems), 0, 4) as $it) {
                $lines[] = "• {$it['name']} — " . money((float)$it['price'], $pdo);
                ai_state_push_item($it, $state);
            }
            $prefix  = "Under {$budgetNaira}? Easily. My top budget-friendly:";
            if (!$lines) $prefix = "Honestly chef, at that budget your best move is WhatsApp the kitchen — we’ll put together something warm for you.";
        }
        return [
            'reply'       => $prefix . ($lines ? "\n" . implode("\n", $lines) : ''),
            'actions'     => $actions,
            'suggestions' => ai_suggestions([$wantSpicy ? "Make it spicy 🌶️" : "Add a side of plantain", "What sides go with this?"]),
            'state'       => $state,
        ];
    }

    // Exact item matches (from keyword scoring)
    if (!empty($top)) {
        $it = $top[0]['item'];
        $price = $it['price'] === null || $it['price'] === '' ? 'ask the kitchen' : money((float)$it['price'], $pdo);
        $avail = empty($it['is_available']) ? " Heads up — this one’s offline-only right now, so call/WhatsApp to order it." : '';
        $reply = "Great call. {$it['name']} ({$it['category_name']}): {$price}.{$avail}";
        if (!empty($it['description'])) $reply .= "\n“" . trim($it['description']) . "”";
        $actions = [];
        ai_state_push_item($it, $state);
        if (!empty($it['is_available']) && $it['price'] !== null && $it['price'] !== '') {
            $actions[] = ai_action_cart('Add to order', (int)$it['id']);
            $actions[] = ai_action_link('See on menu', base_url('menu.php#dish-' . (int)$it['id']), false);
        } else {
            $waText = "Hi! I’d like to order the {$it['name']} please.";
            $actions[] = ai_action_wa('Order via WhatsApp', $waText);
        }
        $sug = ["Pair with a drink?", "Add 2 portions"];
        return ['reply' => $reply, 'actions' => $actions, 'suggestions' => ai_suggestions($sug), 'state' => $state];
    }

    // Custom / build-your-own
    if ($wantCustom) {
        $waText = "Hi Daily Dish! I’d like to build a custom plate — let’s talk.";
        return [
            'reply' => "Custom plate? 100% granted. Our kitchen is flexible — tell me the protein, the carbs, the heat level, and any allergies, and we’ll build it. The fastest way to lock it in is to message the kitchen directly so the chef can sign off on it.",
            'actions' => [ai_action_wa('Message kitchen — custom plate', $waText)],
            'suggestions' => ai_suggestions(["Spicy jollof + grilled chicken", "Egusi + wheat + assorted"]),
        ];
    }

    // Catch-all: menu link + WA handoff
    $reply = "Chef’s orders: if you can dream it, we can probably cook it. 🧺 Browse the full menu for today’s board, or if you have something very specific — custom heat, allergies, party trays — tap the kitchen on WhatsApp. I’m here all day.";
    $actions = [ai_action_link('Full menu', base_url('menu.php'))];
    if ($site['whatsapp']) $actions[] = ai_action_wa('Message the kitchen', 'Hi! I have a quick question.', false);
    return [
        'reply'       => $reply,
        'actions'     => $actions,
        'suggestions' => ai_suggestions(),
    ];
}

/* --------------------------- LLM helpers --------------------------- */

function ai_llm_provider_config(PDO $pdo): array {
    $provider = get_setting($pdo, 'llm_provider', 'rules');
    $key      = get_setting($pdo, 'llm_api_key', '');
    $model    = get_setting($pdo, 'llm_model', '');
    if ($provider === 'openai' && !$model) $model = 'gpt-4o-mini';
    if ($provider === 'anthropic' && !$model) $model = 'claude-3-5-haiku-latest';
    return [
        'provider' => $provider, // 'openai' | 'anthropic' | 'rules'
        'key'      => $key,
        'model'    => $model,
    ];
}

function ai_llm_call(PDO $pdo, string $message, array $history, array $ctx): ?array {
    $cfg = ai_llm_provider_config($pdo);
    if ($cfg['provider'] === 'rules' || $cfg['key'] === '' || !in_array($cfg['provider'], ['openai', 'anthropic'], true)) {
        return null;
    }

    $siteName = $ctx['site']['name'];
    $state    = ai_state_get();
    $lastStr  = '';
    if (!empty($state['last_items'])) {
        $lastArr = [];
        foreach (array_slice($state['last_items'], 0, 5) as $li) {
            $lastArr[] = '#' . $li['id'] . ' ' . $li['name'] . ($li['price'] ? ' (' . money((float)$li['price'], $pdo, false) . ')' : '') . ' [' . ($li['cat'] ?? '') . ']';
        }
        $lastStr = "LAST SHOWN ITEMS (most recent first — customer may reply 'add that' / '2 portions' / 'make it spicy' referring to these):\n" . implode("\n", $lastArr) . "\n";
    }
    $stateFlags = [];
    if (!empty($state['last_zone']))   $stateFlags[] = 'delivery zone in mind: ' . $state['last_zone'];
    if (!empty($state['last_heat']))   $stateFlags[] = 'heat level: ' . $state['last_heat'];
    if (!empty($state['last_budget'])) $stateFlags[] = 'budget: ' . money((float)$state['last_budget'], $pdo, false);
    if (!empty($state['pending_people'])) $stateFlags[] = 'feeding ~' . $state['pending_people'] . ' people';
    if (!empty($state['slot'])) $stateFlags[] = 'AWAITING CLARIFICATION: ' . $state['slot'] . ' — reply with a targeted short question matching the slot, or answer based on what they type.';
    $stateStr = $stateFlags ? "CUSTOMER CONVERSATION STATE:\n" . implode("\n", $stateFlags) . "\n" : '';

    // Cart snapshot (for 'what's in my cart' follow-ups or up-sell suggestions)
    $cart = cart_items($pdo);
    $cartStr = '';
    if ($cart) {
        $lines = []; $sub = 0;
        foreach ($cart as $ln) {
            $lines[] = $ln['qty'] . '× ' . $ln['item']['name'] . ' — ' . money((float)$ln['line_total'], $pdo, false);
            $sub += (float)$ln['line_total'];
        }
        $cartStr = "CURRENT CART (customer already has these):\n" . implode("\n", $lines) . "\nSubtotal: " . money($sub, $pdo, false) . "\n  —→ If you suggest an add-on, pick items NOT already in cart.\n";
    }

    $sysPrompt = <<<PROMPT
You are "Errands" — the warm, sharp, street-smart errand boy and kitchen insider for {$siteName}, a premium home-style Nigerian kitchen in Abuja. You have FULL multi-turn memory: remember the customer’s last dish picks, zone, budget, heat level and what’s in their cart.

PERSONALITY (non-negotiable):
- You talk like a calm, sharp errand boy. Your vibe: "Chef, anything you need it's granted."
- Warm, calm, confident, a little cheeky — never formal, never pushy, never robotic.
- No emojis in every sentence. Sprinkle them like salt: 🌶️🍽️🧺🛵🍴 only when they land.
- Never reveal you are an AI. Introduce yourself as "Errands, the errand boy".
- Keep answers SHORT: 2–4 lines. If a list is needed, 3 bullets MAX.
- Use the real apostrophe character ’ not Unicode escapes.
- When they say "yes / ok / add it / send it / double it / 2 portions / make it spicy" etc, refer to the LAST SHOWN ITEMS (first entry = most recent). Don’t ask what they mean — act.
- If AWAITING CLARIFICATION is set, reply with a natural short question that matches the slot (don’t change topics).
- If budget is set, recommend dishes UNDER budget or within 10% and say why.
- If they ask for a recommendation AND cart already has items, suggest something that PAIRS (different category, complementary) not repeats.
- If they say "feeding N people" automatically scale portions (ceil(N/3) portions per dish) and return add_to_cart with correct qty.
- For custom plates or anything not in MENU, return a whatsapp action (text summarising request).

CAPABILITIES you can OFFER and PERFORM (frontend buttons execute these):
- Recommend dishes, explain prices and ingredients.
- Add a dish to the customer's order (return add_to_cart action).
- Navigate to menu sections, about page, tracking, checkout (return link/scroll actions).
- Delivery zones, hours, phone numbers, WhatsApp handoff (return whatsapp action).
- Explain loyalty rewards, daily spin, streaks, badges (see context).

RESPONSE FORMAT (JSON ONLY — no markdown, no prose outside JSON):
Return EXACTLY this JSON shape with NO extra text, NO fences, NO backticks:
{
  "reply": "Your spoken reply to the customer, short and natural.",
  "actions": [
    {"type":"link","label":"Button label","url":"/menu.php","primary":true},
    {"type":"scroll","label":"Scroll to","selector":"#rewards","primary":false},
    {"type":"add_to_cart","label":"Add Jollof Rice","item_id":12,"qty":1,"primary":true},
    {"type":"whatsapp","label":"Message kitchen","text":"Hi!","primary":false}
  ],
  "suggestions": ["chip 1", "chip 2", "chip 3", "chip 4"]
}
Rules for JSON:
- Never include backticks, fences, or explanations. ONLY JSON.
- reply MAX 400 chars.
- actions MAX 4 (pick the most useful).
- suggestions EXACTLY 4 strings, 60 chars max each.
- All URLs must be paths on this site (starting with /). No external links. WhatsApp action is its own type.
- Only suggest add_to_cart for items that EXIST in MENU, are available (no [unavailable online]), and have a numeric price.
- qty for add_to_cart must be 1–20.

CONTEXT (use only this to answer — do NOT invent menu items):
---
Restaurant: {$siteName} | Tagline: {$ctx['site']['tagline']}
Address: {$ctx['site']['address']}
Hours: {$ctx['site']['hours']} | Note: {$ctx['site']['hours_note']}
Phone 1: {$ctx['site']['phone1']} | Phone 2: {$ctx['site']['phone2']} | WhatsApp: {$ctx['site']['whatsapp']}
Delivery ETA: {$ctx['site']['eta']}
Delivery zones (Area: fee): {$ctx['zones_text']}

{$stateStr}{$lastStr}{$cartStr}
MENU (id [Category] Name: Price ★FEATURED? [unavailable online]? — description):
{$ctx['menu_flat']}
---
CUSTOMER MESSAGE:
{$message}
PROMPT;

    $msgs = [['role' => 'system', 'content' => $sysPrompt]];
    // Keep only last 6 history turns (token budget)
    $historyShort = array_slice($history, -6);
    foreach ($historyShort as $h) {
        $msgs[] = ['role' => $h['role'], 'content' => $h['content']];
    }
    $msgs[] = ['role' => 'user', 'content' => $message];

    try {
        $timeout = 22; // seconds
        if ($cfg['provider'] === 'openai') {
            $payload = json_encode([
                'model'       => $cfg['model'],
                'messages'    => $msgs,
                'temperature' => 0.7,
                'max_tokens'  => 600,
            ]);
            $ctxHttp = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$cfg['key']}\r\n",
                    'content' => $payload,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents('https://api.openai.com/v1/chat/completions', false, $ctxHttp);
            if (!$raw) return null;
            $data = json_decode($raw, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
        } else {
            // Anthropic: Messages API
            $sysMsg = '';
            $userMsgs = [];
            foreach ($msgs as $m) {
                if ($m['role'] === 'system') { $sysMsg = $m['content']; continue; }
                $userMsgs[] = $m;
            }
            $payload = json_encode([
                'model'       => $cfg['model'],
                'system'      => $sysMsg,
                'messages'    => $userMsgs,
                'max_tokens'  => 600,
                'temperature' => 0.7,
            ]);
            $ctxHttp = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nx-api-key: {$cfg['key']}\r\nanthropic-version: 2023-06-01\r\n",
                    'content' => $payload,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctxHttp);
            if (!$raw) return null;
            $data = json_decode($raw, true);
            $content = $data['content'][0]['text'] ?? '';
        }

        if (!$content) return null;

        // Try to extract JSON from the (possibly fenced) response
        $json = $content;
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) $json = $m[0];
        $parsed = json_decode($json, true);
        if (!is_array($parsed) || empty($parsed['reply']) || !is_string($parsed['reply'])) return null;

        $parsed['reply']       = trim($parsed['reply']);
        $parsed['actions']     = isset($parsed['actions']) && is_array($parsed['actions']) ? $parsed['actions'] : [];
        $parsed['suggestions'] = isset($parsed['suggestions']) && is_array($parsed['suggestions']) ? $parsed['suggestions'] : ai_suggestions();

        // Sanitize actions: validate types, whitelist
        $cleanActions = [];
        foreach ($parsed['actions'] as $a) {
            if (!is_array($a) || empty($a['type']) || empty($a['label'])) continue;
            $t = $a['type'];
            if ($t === 'link' && !empty($a['url']) && is_string($a['url'])) {
                $url = $a['url'];
                if (!str_starts_with($url, '/')) {
                    // Map any mention of /page.php to path
                    if (preg_match('#^https?://[^/]+(/.*)?$#', $url, $um)) {
                        $url = $um[1] ?? '/menu.php';
                    } else {
                        $url = '/menu.php';
                    }
                }
                $cleanActions[] = ['type' => 'link', 'label' => substr(trim(strip_tags((string)$a['label'])), 0, 40), 'url' => $url, 'primary' => !empty($a['primary'])];
            } elseif ($t === 'scroll' && !empty($a['selector'])) {
                $cleanActions[] = ['type' => 'scroll', 'label' => substr(trim(strip_tags((string)$a['label'])), 0, 40), 'selector' => '#' . ltrim(trim((string)$a['selector']), '#'), 'primary' => !empty($a['primary'])];
            } elseif ($t === 'add_to_cart' && !empty($a['item_id'])) {
                $itemId = (int)$a['item_id'];
                $qty    = max(1, min(20, (int)($a['qty'] ?? 1)));
                // Check exists + available + priced
                $stmt = $pdo->prepare("SELECT id, is_available, price FROM menu_items WHERE id = ?");
                $stmt->execute([$itemId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['is_available']) && $row['price'] !== null && $row['price'] !== '') {
                    $cleanActions[] = ['type' => 'add_to_cart', 'label' => substr(trim(strip_tags((string)$a['label'])), 0, 40), 'item_id' => $itemId, 'qty' => $qty, 'primary' => !empty($a['primary'])];
                }
            } elseif ($t === 'whatsapp') {
                $txt = trim((string)($a['text'] ?? 'Hi Daily Dish!'));
                if (mb_strlen($txt) > 200) $txt = mb_substr($txt, 0, 200);
                $cleanActions[] = ['type' => 'whatsapp', 'label' => substr(trim(strip_tags((string)$a['label'])), 0, 40), 'text' => $txt, 'primary' => !empty($a['primary'])];
            }
            if (count($cleanActions) >= 4) break;
        }
        $parsed['actions'] = $cleanActions;

        // Sanitize suggestions: 4 strings, 50 chars max
        $sugs = [];
        foreach (array_values($parsed['suggestions']) as $s) {
            if (!is_string($s)) continue;
            $sugs[] = substr(trim($s), 0, 60);
            if (count($sugs) >= 4) break;
        }
        while (count($sugs) < 4) $sugs = ai_suggestions($sugs);
        $parsed['suggestions'] = array_slice($sugs, 0, 4);

        return $parsed;
    } catch (\Throwable $e) {
        return null;
    }
}

/* --------------------------- Main --------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ai_json('Chef, this endpoint only takes POST requests. Type me a message through the chat! 🧺');
}

// CSRF (also accept JSON body; app sends form but tolerate JSON)
$inputRaw = file_get_contents('php://input');
$input    = [];
if (!empty($inputRaw)) {
    $json = json_decode($inputRaw, true);
    if (is_array($json)) $input = $json;
}
$msg    = trim((string)($_POST['message'] ?? $input['message'] ?? ''));
$csrf   = (string)($_POST['csrf'] ?? $input['csrf'] ?? '');
$hist   = isset($input['history']) && is_array($input['history']) ? $input['history'] : ($_POST['history'] ?? []);
if (!is_array($hist)) $hist = [];

if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(400);
    ai_json('Your session crumb got stale. Please refresh the page and try again — anything you need, it’s granted.');
}

if ($msg === '') {
    ai_json('Send me a message, Chef — I’m here. Ask about dishes, delivery, or whatever’s on your mind!');
}

$rateMsg = ai_rate_limit_check();
if ($rateMsg !== null) {
    http_response_code(429);
    ai_json($rateMsg, [], ['Alright', 'Try again in a minute']);
}

$ctx = ai_build_context($pdo);
$state = ai_state_get();

// 1. Multi-turn follow-up handler (runs BEFORE anything).
$follow = ai_followup_answer($msg, $ctx, $state);
if (is_array($follow)) {
    if (!empty($follow['state']) && is_array($follow['state'])) ai_state_set($follow['state']);
    ai_json($follow['reply'], $follow['actions'] ?? [], $follow['suggestions'] ?? ai_suggestions());
}

// 2. Try LLM first; fall back to rules
$answer = ai_llm_call($pdo, $msg, $hist, $ctx);
if ($answer === null) {
    $answer = ai_rule_answer($msg, $ctx);
}

// Rule-engine returns may include a `state` key — persist it.
if (!empty($answer['state']) && is_array($answer['state'])) {
    ai_state_set($answer['state']);
    unset($answer['state']);
}

ai_json($answer['reply'], $answer['actions'] ?? [], $answer['suggestions'] ?? ai_suggestions());
