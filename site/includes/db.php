<?php
/**
 * Daily Dish Restaurant — database layer.
 * Uses SQLite via PDO (zero-config, works on any cPanel host).
 * To switch to MySQL later, replace get_db() with a MySQL PDO DSN —
 * every query below uses plain PDO/PDO-portable SQL.
 */

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dataDir = BASE_PATH . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $isNew = !file_exists(DB_PATH);

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    create_schema($pdo);

    if ($isNew) {
        seed_data($pdo);
    }

    return $pdo;
}

function create_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        sort_order INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        price REAL,
        image TEXT,
        is_available INTEGER DEFAULT 1,
        is_featured INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_code TEXT UNIQUE,
        customer_name TEXT NOT NULL,
        phone TEXT NOT NULL,
        fulfillment TEXT DEFAULT 'delivery',
        address TEXT,
        notes TEXT,
        status TEXT DEFAULT 'pending',
        subtotal REAL DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        menu_item_id INTEGER,
        name TEXT NOT NULL,
        price REAL NOT NULL,
        qty INTEGER NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey TEXT PRIMARY KEY,
        svalue TEXT
    )");

    migrate_schema($pdo);
}

/**
 * Adds columns introduced in the 2026 redesign to databases created
 * by the earlier version — safe to run on every request.
 */
function migrate_schema(PDO $pdo): void
{
    $cols = [];
    foreach ($pdo->query("PRAGMA table_info(orders)") as $c) {
        $cols[$c['name']] = true;
    }
    $add = [
        'delivery_zone'  => "TEXT",
        'delivery_fee'   => "REAL DEFAULT 0",
        'total'          => "REAL",
        'payment_method' => "TEXT DEFAULT 'cash'",
        'preferred_time' => "TEXT",
    ];
    foreach ($add as $name => $type) {
        if (!isset($cols[$name])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $name $type");
        }
    }
}

function seed_data(PDO $pdo): void
{
    // --- default admin -------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $stmt->execute(['admin', password_hash('DailyDish2026!', PASSWORD_DEFAULT)]);

    // --- settings --------------------------------------------------------
    $settings = [
        'site_name'      => 'Daily Dish Restaurant',
        'tagline'        => 'Your Everyday Delicacy',
        'strapline'      => 'Good food. Great taste. Every day.',
        'rc_number'      => 'RC: 9431744',
        'address'        => 'Shop 4, RVS Mall, Third Avenue, Gwarinpa, Abuja, FCT',
        'phone_primary'  => '08021333972',
        'phone_secondary'=> '09061703148',
        'whatsapp'       => '2348021333972',
        'email'          => '',
        'hours'          => 'Mon – Sun: 8:00 AM – 9:00 PM',
        'facebook_url'   => '',
        'instagram_url'  => '',
        'currency_symbol'=> '₦',
        'delivery_zones' => "Gwarinpa | 1500\nKubwa | 2500\nDutse | 2500\nLife Camp | 2500\nJabi | 3000\nWuse | 3500\nMaitama | 3500\nCentral Area | 3500",
        'delivery_eta'   => '35–60 min',
        'bank_name'      => '',
        'bank_account_name' => '',
        'bank_account_number' => '',
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // --- categories --------------------------------------------------------
    $categories = [
        ['starters-soup', 'Starters & Soup', 1],
        ['breakfast', 'Breakfast', 2],
        ['national-dish', 'National Dish & Swallow', 3],
        ['chicken', 'Chicken', 4],
        ['beef-lamb', 'Beef & Lamb Chops', 5],
        ['seafood', 'Seafood', 6],
        ['pasta', 'Pasta', 7],
        ['house-rice', 'House Special Rice', 8],
        ['salads', 'Salads', 9],
        ['sandwich-burger-pizza', 'Sandwich, Burger & Pizza', 10],
        ['chefs-specials', "Chef's Specials", 0],
    ];
    $catStmt = $pdo->prepare("INSERT INTO categories (slug, name, sort_order) VALUES (?, ?, ?)");
    $catIds = [];
    foreach ($categories as [$slug, $name, $order]) {
        $catStmt->execute([$slug, $name, $order]);
        $catIds[$slug] = $pdo->lastInsertId();
    }

    // --- menu items --------------------------------------------------------
    // price = null means "Ask for price" / call to order (POA)
    $items = [
        // Chef's specials (real kitchen photos, not on the printed card)
        ['chefs-specials', 'Loaded Cheese Fries', 'Crispy fries piled with grilled chicken, sausage, sweet peppers and melted cheese, finished with our house drizzle sauce.', 6500, 'nachos.jpg', 1, 0],
        ['chefs-specials', 'Foil-Grilled Pepper Fish', 'Whole fish marinated in fresh pepper sauce and slow-grilled in foil until smoky and tender.', 9500, 'grilled-foil.jpg', 1, 1],

        // Starters & Soup
        ['starters-soup', 'Butterfly Prawns', 'De-veined prawns coated with seasoned breadcrumbs and fried golden brown.', null, null, 1, 0],
        ['starters-soup', 'Peppered Gizzard', 'Chicken or turkey gizzard tossed in a spicy tomato sauce.', null, null, 1, 0],
        ['starters-soup', 'Spring Rolls', 'Crisp pastry filled with your choice of shredded chicken or vegetable (6 pieces).', null, null, 1, 0],
        ['starters-soup', 'Mushroom Soup', 'Mushroom cooked in a roux base stock and thickened with cream.', null, null, 1, 0],
        ['starters-soup', 'Sweet Corn Soup', 'Corn kernels in vegetable stock with strands of poached egg and parsley.', null, null, 1, 0],
        ['starters-soup', 'Tomato Soup', 'Chopped tomato in a vegetable stock with celery, leeks and sweet green peppers.', null, null, 1, 0],
        ['starters-soup', 'Oxtail Pepper Soup', 'Nigerian oxtail pepper soup made with local herbs and spices.', null, null, 1, 1],
        ['starters-soup', "Chicken Pepper Soup", 'Succulent chicken pepper soup, made the Margaret way.', null, null, 1, 0],
        ['starters-soup', 'Chicken Noodle Soup', 'Rich chicken broth with noodles and vegetables.', null, null, 1, 0],

        // Breakfast
        ['breakfast', 'Full English / Continental Breakfast', 'Sausages, eggs, baked beans, grilled tomatoes and mushrooms, with tea, coffee or hot chocolate and a glass of fresh juice. Served with your choice of milk bread, wheat bread, pancakes, waffles or bread rolls — toasted or plain.', null, null, 1, 0],
        ['breakfast', 'National Breakfast', 'Boiled yam or plantain, fried plantain, fried beans, sweet potato chips or yam chips — served with corned beef, stew or egg sauce.', null, null, 1, 0],

        // National Dish & Swallow
        ['national-dish', 'Egusi Soup & Swallow', 'Melon-seed soup, rich and hearty, with your choice of protein — chicken, beef, croaker fish, goat meat or turkey.', 15000, 'swallow-soup-plain.jpg', 1, 1],
        ['national-dish', 'Vegetable / Okro Soup & Swallow', 'Fresh vegetable or draw okro soup with your choice of protein — chicken, beef, croaker fish, goat meat or turkey.', 15000, 'poster-swallow.jpg', 1, 0],
        ['national-dish', 'Afang Soup & Swallow', 'Traditional afang soup loaded with assorted meat and seafood, with your choice of protein.', 15000, 'seafood-native.jpg', 1, 1],
        ['national-dish', 'Bucket of Soup', 'Family or party-size soup, made to order. Call to discuss quantity and protein.', null, null, 1, 0],

        // Chicken
        ['chicken', 'Coconut Chicken Curry', 'Chicken in curry paste, coconut milk and coriander with fresh vegetables — best served with rice.', null, null, 1, 0],
        ['chicken', 'Grilled Black Pepper Chicken', 'Grilled boneless chicken breast in a black pepper and garlic sauce, served with sautéed potatoes or rice.', null, null, 1, 0],
        ['chicken', 'Chicken Casserole', 'Chicken pieces and vegetables baked in a rich sauce, served with sautéed potatoes or rice.', null, 'chicken-stirfry.jpg', 1, 1],
        ['chicken', 'Chicken in Oyster Sauce', 'Chicken cooked in a savoury homemade oyster sauce, served with rice.', null, null, 1, 0],
        ['chicken', 'Shredded Chicken with Green Pepper', 'Chicken breast cut julienne, cooked with Chinese five-spice and green pepper — best with plain steamed rice.', null, null, 1, 0],
        ['chicken', "Crispy Fried Margaret's Chicken", 'Fried chicken in a spicy, crunchy coating, served with sautéed potatoes.', null, 'pepper-chicken.jpg', 1, 1],

        // Beef & Lamb Chops
        ['beef-lamb', 'Grilled Lamb Chops', 'Char-grilled lamb chops, simply seasoned and served hot off the grill.', null, null, 1, 0],
        ['beef-lamb', 'T-Bone Steak', 'A hearty grilled T-bone, cooked to your preference.', null, null, 1, 0],
        ['beef-lamb', 'Café Grilled Steak', 'Succulent sirloin steak with grilled pineapple in a rich creamy sauce.', null, null, 1, 0],
        ['beef-lamb', 'Beef Stroganoff', 'Sautéed strips of beef and mushroom in sour cream, served over rice.', null, null, 1, 0],
        ['beef-lamb', 'Mixed Grill', 'Prawns, fish, chicken, sausage, lamb and beef with seasonal vegetables.', null, null, 1, 0],

        // Seafood
        ['seafood', 'Hot Pan Prawns', 'Stir-fried prawns with green pepper in a sizzling soy-chilli sauce — best with noodles or rice.', null, null, 1, 0],
        ['seafood', 'Sweet and Sour Prawns', 'Jumbo prawns in a rich tomato, honey and vinegar sauce — best served with rice.', null, null, 1, 0],
        ['seafood', 'Grilled Prawns and Cream Sauce', 'Pasta with tiger prawns, served with a cream and chive sauce and a sprinkle of herbs.', null, null, 1, 0],
        ['seafood', 'Grilled Fish with Lemon Sauce', 'Lemon and soy marinated fish, slow-grilled and best served with chips.', null, null, 1, 0],
        ['seafood', 'Fish Curry', 'Fish cooked in spicy coconut milk with pepper — best served with rice.', null, null, 1, 0],
        ['seafood', 'Grilled Salmon', 'Pink salmon, char-grilled with lemon.', null, null, 1, 0],

        // Pasta
        ['pasta', 'Spaghetti Bolognese', 'Traditional Italian spaghetti with mince meat, tomato, herbs and garlic sauce.', null, null, 1, 0],
        ['pasta', 'Spaghetti & Tomato Meatball Sauce', 'Spaghetti cooked al dente, served with spicy meatballs in a piquant tomato sauce.', null, null, 1, 0],
        ['pasta', 'Spaghetti Napolitana', 'Linguine cooked with chicken, prawn, tomatoes, onion, rosemary and sweet bell peppers.', null, null, 1, 0],
        ['pasta', 'Pasta Alfredo', 'Penne pasta cooked in a creamy chicken sauce.', null, null, 1, 0],
        ['pasta', 'Lasagna', 'Classic Italian pasta bake — alternating layers of pasta, cheese and minced beef.', null, null, 1, 0],

        // House Special Rice
        ['house-rice', 'Smokey Party Jollof Rice', 'Our signature smoky party jollof with chicken, turkey, fish or beef and a side salad.', 15000, 'jollof-trays.jpg', 1, 1],
        ['house-rice', 'Native Coconut Rice', 'Coconut rice with chicken, turkey, fish or beef and a side salad.', 15000, null, 1, 0],
        ['house-rice', 'Oriental Rice', 'Stir-fried rice with shrimp and fresh vegetables, served alongside sautéed braised fish.', 15000, 'rice-tray.jpg', 1, 1],
        ['house-rice', 'GK Pot', 'Stir-fried basmati rice with chicken, beef, prawns and fresh vegetables.', 15000, null, 1, 0],
        ['house-rice', 'Chinese Fried Rice', 'Stir-fried rice with spring onion, egg, carrot and green pepper, served with shredded beef and green peppers.', 15000, 'friedrice-beef.jpg', 1, 0],
        ['house-rice', 'Shrimp Fried Rice', 'Stir-fried rice with shrimp, carrot, green beans and sweet corn, served with chicken.', 15000, null, 1, 0],
        ['house-rice', 'Atrium Fried Rice', 'House fried rice with your choice of protein, side and salad.', 15000, 'chicken-friedrice.jpg', 1, 0],

        // Salads
        ['salads', 'Chicken Salad', 'Grilled chicken strips on a bed of lettuce, tomato, cucumber, olives and croutons.', null, null, 1, 0],
        ['salads', 'Prawns Salad', 'Crunchy lettuce, tomato, bell pepper, cucumber and grilled prawns.', null, null, 1, 0],
        ['salads', 'Green Salad', 'Salad greens with sweet corn, hard-boiled egg and olives.', null, null, 1, 0],
        ['salads', 'Caesar Salad', 'A classic Caesar — crisp lettuce, parmesan, croutons and Caesar dressing.', null, null, 1, 0],
        ['salads', 'Fruit Salad', 'A refreshing mix of fresh seasonal fruit.', null, null, 1, 0],
        ['salads', 'Vegetable & Shrimp Salad', 'Fresh vegetables tossed with shrimp.', null, null, 1, 0],
        ['salads', 'Coleslaw', 'Classic creamy shredded cabbage and carrot slaw.', null, null, 1, 0],

        // Sandwich / Burger / Pizza
        ['sandwich-burger-pizza', 'Club Sandwich', 'Triple-stacked classic club sandwich.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Chicken Sandwich', 'Grilled chicken sandwich with fresh fillings.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Tuna Sandwich', 'Creamy tuna mix on your choice of bread.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Chicken Burger', 'Juicy chicken patty burger with fresh toppings.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Beef Burger', 'Grilled beef patty burger with fresh toppings.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Pizza GK', 'Tomato sauce, mozzarella cheese, oregano and rosemary.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Seafood Pizza', 'Tomato sauce, shrimp, fish, calamari, garlic sauce and rosemary.', null, null, 1, 0],
        ['sandwich-burger-pizza', 'Chicken and Cheese Pizza', 'Tomato sauce, mozzarella cheese, chicken, mushroom and oregano.', null, null, 1, 0],
    ];

    $itemStmt = $pdo->prepare("INSERT INTO menu_items (category_id, name, description, price, image, is_available, is_featured, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $order = 0;
    foreach ($items as [$slug, $name, $desc, $price, $image, $avail, $featured]) {
        $order++;
        $itemStmt->execute([$catIds[$slug], $name, $desc, $price, $image, $avail, $featured, $order]);
    }
}
