<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = null;
$active = 'home';
$phone = get_setting($pdo, 'phone_primary', '');
$hours = get_setting($pdo, 'hours', 'Mon – Sat: 9:00 AM – 6:00 PM');
$hoursNote = get_setting($pdo, 'hours_note', 'Closed Sundays');
$eta   = get_setting($pdo, 'delivery_eta', '35–60 min');
$zones = delivery_zones($pdo);

$featured = $pdo->query("SELECT m.*, c.name AS category_name
    FROM menu_items m JOIN categories c ON c.id = m.category_id
    WHERE m.is_featured = 1 AND m.is_available = 1
    ORDER BY (m.price IS NULL), (m.image IS NULL), m.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id AND m.is_available = 1) AS n
    FROM categories c ORDER BY c.sort_order")->fetchAll(PDO::FETCH_ASSOC);

$gameState = game_state_for_client($pdo);
$badgeDefs = game_badge_defs();

include __DIR__ . '/includes/header.php';
?>

<div class="layout-magazine">
  <div class="mag-left">

<section class="hero">
  <div class="hero-slider">
    <div class="hs-track" data-hs-track>
      <div class="hs-slide on">
        <img src="<?= e(site_photo($pdo, 'hero_1', 'swallow-soup-plain.jpg')) ?>" alt="Egusi soup with swallow" fetchpriority="high" decoding="async" width="1600" height="900" sizes="100vw">
        <div class="hs-overlay"></div>
        <div class="hs-caption">
          <div class="wrap">
            <div class="eyebrow" style="margin-bottom:12px;opacity:.94;">The Kitchen Table — Daily Dish Abuja</div>
            <h1 class="hero-title">Your everyday delicacy<span class="line2">at your door.</span></h1>
            <p class="hero-sub">Egusi and swallow, smoky party jollof, pepper fish in foil. Cooked to order in Gwarinpa and delivered across Abuja.</p>
            <div class="hero-actions">
              <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-primary">Start your order</a>
              <a href="tel:<?= e(tel($phone)) ?>" class="btn btn-ghost">Call <?= e($phone) ?></a>
            </div>
            <p class="hero-note"><span class="dot-open"></span><?= e($hours) ?><?php if ($hoursNote): ?> · <small style="opacity:.82;"><?= e($hoursNote) ?></small><?php endif; ?></p>
          </div>
        </div>
      </div>
      <div class="hs-slide">
        <img src="<?= e(site_photo($pdo, 'hero_2', 'grilled-foil.jpg')) ?>" alt="Foil-grilled pepper fish" loading="lazy" decoding="async" width="1600" height="900" sizes="100vw">
        <div class="hs-overlay"></div>
        <div class="hs-caption">
          <div class="wrap">
            <div class="eyebrow" style="margin-bottom:12px;opacity:.94;">The Kitchen Table — Daily Dish Abuja</div>
            <h1 class="hero-title">Smoky grills &<span class="line2">peppered goodness.</span></h1>
            <p class="hero-sub">Fresh fish, chicken and beef grilled hot with our signature pepper blend. Sealed in foil, delivered juicy.</p>
            <div class="hero-actions">
              <a href="<?= e(base_url('menu.php#grills')) ?>" class="btn btn-primary">See the grills</a>
            </div>
          </div>
        </div>
      </div>
      <div class="hs-slide">
        <img src="<?= e(site_photo($pdo, 'hero_3', 'jollof-trays.jpg')) ?>" alt="Trays of party jollof rice with chicken" loading="lazy" decoding="async" width="1600" height="900" sizes="100vw">
        <div class="hs-overlay"></div>
        <div class="hs-caption">
          <div class="wrap">
            <div class="eyebrow" style="margin-bottom:12px;opacity:.94;">The Kitchen Table — Daily Dish Abuja</div>
            <h1 class="hero-title">Feeding a crowd?<span class="line2">We do trays.</span></h1>
            <p class="hero-sub">Party trays of jollof, fried rice and buckets of soup, made to order for events and offices.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="https://wa.me/<?= e(get_setting($pdo, 'whatsapp')) ?>?text=<?= rawurlencode('Hi Daily Dish, I want to order party trays for an event.') ?>" target="_blank" rel="noopener">Plan it on WhatsApp</a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <button class="hs-prev" data-hs-prev aria-label="Previous slide">‹</button>
    <button class="hs-next" data-hs-next aria-label="Next slide">›</button>
    <div class="hs-dots" data-hs-dots>
      <button class="on" aria-label="Slide 1"></button>
      <button aria-label="Slide 2"></button>
      <button aria-label="Slide 3"></button>
    </div>
  </div>

  <?php if ($zones): $first = array_key_first($zones); ?>
  <div class="wrap hero-docket-wrap">
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
  </div>
  <?php endif; ?>
</section>

<?php if ($featured):
  $editorCollage = array_slice($featured, 0, 3);
  $editorLarge = $editorCollage[0] ?? null;
  $editorSmall1 = $editorCollage[1] ?? null;
  $editorSmall2 = $editorCollage[2] ?? null;
  $carouselFeatured = array_slice($featured, 3);
?>
<section class="section-tight reveal">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow">Editor's Pick</span>
        <h2>Chef's selections this week</h2>
        <p>Fresh picks from our kitchen — tap the quick-add button to add any dish straight to your order.</p>
      </div>
      <a class="link-arrow" href="<?= e(base_url('menu.php')) ?>">See the full menu</a>
    </div>
    <div class="editor-collage">
      <?php if ($editorLarge): ?>
        <div class="ec-large">
          <?= dish_markup($editorLarge, ['show_cat' => true]) ?>
          <?= dish_markup($editorLarge, ['layout' => 'feed']) ?>
        </div>
      <?php endif; ?>
      <div class="ec-small">
        <?php if ($editorSmall1): ?>
          <div class="ec-small-item">
            <?= dish_markup($editorSmall1, ['show_cat' => true]) ?>
            <?= dish_markup($editorSmall1, ['layout' => 'feed']) ?>
          </div>
        <?php endif; ?>
        <?php if ($editorSmall2): ?>
          <div class="ec-small-item">
            <?= dish_markup($editorSmall2, ['show_cat' => true]) ?>
            <?= dish_markup($editorSmall2, ['layout' => 'feed']) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="feed-grid editor-feed mobile-only">
      <?php foreach ($editorCollage as $item): ?>
        <?= dish_markup($item, ['layout' => 'feed']) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($carouselFeatured): ?>
<section class="section-tight reveal late">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>More favourites this week</h2>
        <p>Swipe through the rest of our top picks.</p>
      </div>
    </div>
    <div class="carousel">
      <button class="carousel-prev" data-carousel-prev aria-label="Previous">‹</button>
      <div class="carousel-viewport">
        <div class="carousel-track" data-carousel-track>
          <?php foreach ($carouselFeatured as $item): ?>
            <div class="carousel-item">
              <?= dish_markup($item, ['show_cat' => true]) ?>
              <?= dish_markup($item, ['layout' => 'feed']) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="carousel-next" data-carousel-next aria-label="Next">›</button>
    </div>
    <div class="feed-grid carousel-feed mobile-only">
      <?php foreach ($carouselFeatured as $item): ?>
        <?= dish_markup($item, ['layout' => 'feed']) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php else: ?>
<section class="section-tight reveal">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>Most ordered this week</h2>
        <p>Tap a dish to see it up close and add it to your order.</p>
      </div>
      <a class="link-arrow" href="<?= e(base_url('menu.php')) ?>">See the full menu</a>
    </div>
    <div class="dish-grid">
      <p class="hint">Check back soon — the chef is plating up the week's favourites.</p>
    </div>
  </div>
</section>
<?php endif; ?>

  </div>
  <div class="mag-right">
    <?= render_sticky_rail() ?>
  </div>
</div>

<section id="rewards" class="section section-sand reveal late">
  <div class="wrap">
    <div class="section-head center">
      <div>
        <h2>🍷 Chef’s Table Rewards</h2>
        <p>Our way of saying thank you. Every order brings you closer to the next tier.</p>
      </div>
    </div>
    <div class="rewards-wrap">
      <div class="rewards-card">
        <div class="rc-head">
          <div class="rc-avatar">
            <span class="rc-avatar-emoji" data-game-level-emoji><?= e($gameState['level']['emoji']) ?></span>
          </div>
          <div class="rc-identity">
            <div class="rc-level" data-game-level-color style="--lc:<?= e($gameState['level']['color']) ?>">
              <span class="rc-level-name" data-game-level-name><?= e($gameState['level']['name']) ?></span>
            </div>
            <div class="rc-progress">
              <div class="rc-bar"><div class="rc-bar-fill" data-game-progress style="width:<?= e((string)$gameState['level']['progress']) ?>%"></div></div>
              <div class="rc-progress-meta">
                <span><span data-roll="xp" data-roll-to="<?= (int)$gameState['xp'] ?>">0</span> tasting notes</span>
                <?php if (!empty($gameState['level']['next_min'])): ?>
                <span>Next tier: <span data-game-next-min><?= (int)$gameState['level']['next_min'] ?></span></span>
                <?php else: ?>
                <span>You’ve reached the chef’s table 👨‍🍳</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="rc-stats">
          <div class="rc-stat">
            <div class="rc-stat-num"><span data-roll="points" data-roll-to="<?= (int)$gameState['points'] ?>">0</span></div>
            <div class="rc-stat-label">Reward points</div>
          </div>
          <div class="rc-stat">
            <div class="rc-stat-num"><span data-roll="orders" data-roll-to="<?= (int)$gameState['orders'] ?>">0</span></div>
            <div class="rc-stat-label">Meals enjoyed</div>
          </div>
          <div class="rc-stat">
            <div class="rc-stat-num"><?= (int)$gameState['streak'] ?><small>🌿</small></div>
            <div class="rc-stat-label">Day ritual</div>
          </div>
        </div>
        <div class="rc-actions">
          <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-dark">See today’s menu</a>
        </div>
      </div>

      <div class="spin-card">
        <div class="spin-head">
          <span class="spin-emoji">🎁</span>
          <div>
            <h3>Daily Kitchen Treat</h3>
            <p>A little something from the chef — claim once every day.</p>
          </div>
        </div>
        <div class="spin-stage">
          <div class="spin-wheel" data-spin-wheel></div>
        </div>
        <?php $canSpin = !empty($gameState['can_spin_today']); ?>
        <button class="btn btn-gold btn-block" data-spin-go data-can-spin="<?= $canSpin ? '1' : '0' ?>"
          <?= $canSpin ? '' : 'disabled' ?>>
          <?= $canSpin ? '🍽️ Claim today’s treat' : '⏰ Back tomorrow for another' ?>
        </button>
      </div>
    </div>
  </div>
</section>

<section class="section reveal late2">
  <div class="wrap">
    <div class="section-head center">
      <div>
        <h2>🌿 Culinary Milestones</h2>
        <p>Every visit and every dish leaves its mark on your journey.</p>
      </div>
    </div>
    <div class="badges-grid">
      <?php
        $unlocked = $gameState['badges_unlocked'] ?? [];
        $newBadges = $gameState['badges_new'] ?? [];
      ?>
      <?php foreach ($badgeDefs as $bid => $bdef):
        $isUnlocked = in_array($bid, $unlocked, true);
        $isNew = in_array($bid, $newBadges, true);
      ?>
        <div class="badge <?= $isUnlocked ? '' : 'locked' ?> <?= $isNew ? 'new' : '' ?>">
          <div class="badge-emoji"><?= e($bdef['emoji']) ?></div>
          <div class="badge-name"><?= e($bdef['name']) ?></div>
          <div class="badge-desc"><?= e($bdef['desc']) ?></div>
          <?php if (!$isUnlocked): ?><div class="badge-lock">🔒</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-cocoa reveal late">
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

<section class="section-tight reveal">
  <div class="wrap">
    <div class="section-head"><div><h2>What are you craving?</h2></div></div>
    <div class="chips">
      <?php foreach ($categories as $cat): if (!$cat['n']) continue; ?>
        <a class="chip" href="<?= e(base_url('menu.php#' . $cat['slug'])) ?>"><?= e($cat['name']) ?> <small><?= (int)$cat['n'] ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight reveal late">
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
<section class="section section-sand reveal">
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

<section class="section reveal late">
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
