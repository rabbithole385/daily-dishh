<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = null;
$active = 'home';
$phone = get_setting($pdo, 'phone_primary', '');
$hours = get_setting($pdo, 'hours', '');
$eta   = get_setting($pdo, 'delivery_eta', '35–60 min');
$zones = delivery_zones($pdo);

$featured = $pdo->query("SELECT m.*, c.name AS category_name
    FROM menu_items m JOIN categories c ON c.id = m.category_id
    WHERE m.is_featured = 1 AND m.is_available = 1
    ORDER BY (m.price IS NULL), (m.image IS NULL), m.id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id AND m.is_available = 1) AS n
    FROM categories c ORDER BY c.sort_order")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <h1 class="hero-title">Your everyday delicacy<span class="line2">at your door.</span></h1>
      <p class="hero-sub">Egusi and swallow, smoky party jollof, pepper fish in foil. Cooked to order in Gwarinpa and delivered across Abuja.</p>
      <div class="hero-actions">
        <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-primary">Start your order</a>
        <a href="tel:<?= e(tel($phone)) ?>" class="btn btn-ghost">Call <?= e($phone) ?></a>
      </div>
      <p class="hero-note"><span class="dot-open"></span><?= e($hours) ?></p>
    </div>

    <div class="hero-visual">
    <div class="hero-plates">
      <figure class="p1"><img src="<?= e(site_photo($pdo, 'hero_1', 'swallow-soup-plain.jpg')) ?>" alt="Egusi soup with swallow" fetchpriority="high"></figure>
      <figure class="p2"><img src="<?= e(site_photo($pdo, 'hero_2', 'grilled-foil.jpg')) ?>" alt="Foil-grilled pepper fish"></figure>
      <figure class="p3"><img src="<?= e(site_photo($pdo, 'hero_3', 'jollof-trays.jpg')) ?>" alt="Trays of party jollof rice with chicken"></figure>
    </div>

      <?php if ($zones): $first = array_key_first($zones); ?>
      <div class="docket hero-docket">
        <div class="docket-head"><span class="docket-title">Delivery check</span><span class="docket-meta"><?= e($eta) ?></span></div>
        <label for="zoneCheck">Where should we bring it?</label>
        <select id="zoneCheck">
          <?php foreach ($zones as $z => $fee): ?>
            <option data-fee-text="<?= e(money($fee, $pdo)) ?> delivery"><?= e($z) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="docket-row"><span>Fee</span><strong id="zoneFee"><?= e(money($zones[$first], $pdo)) ?> delivery</strong></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>Most ordered this week</h2>
        <p>Tap a dish to see it up close and add it to your order.</p>
      </div>
      <a class="link-arrow" href="<?= e(base_url('menu.php')) ?>">See the full menu</a>
    </div>
    <div class="dish-grid">
      <?php foreach ($featured as $item): ?>
        <article class="dish"<?= dish_attr($item, $item['category_name']) ?>>
          <div class="dish-photo" data-open-dish>
            <span class="dish-cat"><?= e($item['category_name']) ?></span>
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
  </div>
</section>

<section class="section section-cocoa">
  <div class="wrap steps-wrap">
    <div class="rider-art">
      <img src="<?= e(base_url('assets/img/rider.svg')) ?>" alt="">
      <p>Packed hot in sealed containers, out the door in <?= e($eta) ?>.</p>
    </div>
    <div>
      <div class="section-head"><div><h2>How ordering works</h2><p>No app to download, no account to create.</p></div></div>
      <ol class="steps">
        <li><div><h3>Pick your dishes</h3><p>Add anything with a price straight to your order. For the rest, tap Ask price and we reply on WhatsApp.</p></div></li>
        <li><div><h3>Choose delivery or pickup</h3><p>Select your area to see the delivery fee, then pay cash or transfer.</p></div></li>
        <li><div><h3>Track it to your door</h3><p>We call to confirm, and your order page updates from kitchen to doorstep.</p></div></li>
      </ol>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="wrap">
    <div class="section-head"><div><h2>What are you craving?</h2></div></div>
    <div class="chips">
      <?php foreach ($categories as $cat): if (!$cat['n']) continue; ?>
        <a class="chip" href="<?= e(base_url('menu.php#' . $cat['slug'])) ?>"><?= e($cat['name']) ?> <small><?= (int)$cat['n'] ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="wrap">
    <div class="section-head"><div><h2>Fresh from our kitchen</h2></div></div>
    <div class="gallery">
      <?php foreach (gallery_photos($pdo) as $g): ?>
        <img src="<?= e(img_url($g)) ?>" alt="Dish from the Daily Dish kitchen" loading="lazy">
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($zones): ?>
<section class="section section-sand">
  <div class="wrap areas-grid">
    <div>
      <h2>Where we deliver</h2>
      <p>Flat fees by area, shown before you pay. Not on the list? Call us and we will sort it out.</p>
      <a class="btn btn-dark" href="<?= e(base_url('menu.php')) ?>">Order now</a>
    </div>
    <div class="docket">
      <div class="docket-head"><span class="docket-title">Delivery fees</span><span class="docket-meta"><?= e($eta) ?></span></div>
      <div class="areas-list">
        <?php foreach ($zones as $z => $fee): ?>
          <div class="docket-row"><span><?= e($z) ?></span><span><?= e(money($fee, $pdo)) ?></span></div>
        <?php endforeach; ?>
      </div>
      <p class="docket-note">Pickup at RVS Mall, Gwarinpa is always free.</p>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap">
    <div class="cta">
      <div>
        <h2>Feeding a crowd?</h2>
        <p>Party trays of jollof, fried rice and buckets of soup, made to order for events and offices.</p>
      </div>
      <div class="hero-actions">
        <a class="btn btn-light" href="https://wa.me/<?= e(get_setting($pdo, 'whatsapp')) ?>?text=<?= rawurlencode('Hi Daily Dish, I want to order party trays for an event.') ?>" target="_blank" rel="noopener">Plan it on WhatsApp</a>
        <a class="btn btn-ghost" href="tel:<?= e(tel($phone)) ?>">Call the kitchen</a>
      </div>
    </div>
  </div>
</section>

<?= sheet_markup() ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
