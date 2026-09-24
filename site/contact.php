<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Find us';
$active = 'contact';
$address = get_setting($pdo, 'address');
$phone1 = get_setting($pdo, 'phone_primary');
$phone2 = get_setting($pdo, 'phone_secondary');
$whatsapp = get_setting($pdo, 'whatsapp');
$hours = get_setting($pdo, 'hours', 'Mon – Sat: 9:00 AM – 6:00 PM');
$hoursNote = get_setting($pdo, 'hours_note', 'Closed Sundays');
$email = get_setting($pdo, 'email');
$mapQuery = urlencode('RVS Mall Third Avenue Gwarinpa Abuja');
include __DIR__ . '/includes/header.php';
?>

<div class="page-head"><div class="wrap"><h1>Find us</h1><p>Walk in, call ahead for pickup, or have it delivered.</p></div></div>

<section class="section-tight" style="padding-top:8px;">
  <div class="wrap split">
    <div>
      <ul class="contact-list">
        <li><span>Address</span><span><?= e($address) ?></span></li>
        <li><span>Hours</span><span><?= e($hours) ?><?php if ($hoursNote): ?><br><small style="color:var(--ink-soft);"><?= e($hoursNote) ?></small><?php endif; ?></span></li>
        <li><span>Phone</span><span><a href="tel:<?= e(tel($phone1)) ?>"><?= e($phone1) ?></a><?php if ($phone2): ?><br><a href="tel:<?= e(tel($phone2)) ?>"><?= e($phone2) ?></a><?php endif; ?></span></li>
        <?php if ($whatsapp): ?><li><span>WhatsApp</span><span><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">Message the kitchen</a></span></li><?php endif; ?>
        <?php if ($email): ?><li><span>Email</span><span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span></li><?php endif; ?>
      </ul>
      <div class="hero-actions">
        <a href="<?= e(base_url('menu.php')) ?>" class="btn btn-primary">Order online</a>
        <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>" target="_blank" rel="noopener" class="btn btn-ghost">Get directions</a>
      </div>
    </div>
    <div class="split-photo" style="aspect-ratio:4/3;">
      <iframe src="https://maps.google.com/maps?q=<?= $mapQuery ?>&z=16&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map to Daily Dish Restaurant"></iframe>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
