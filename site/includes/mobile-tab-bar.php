<?php
$tabs = [
  ['href' => base_url('index.php'), 'label' => 'Home', 'ic' => '🏠', 'key' => 'home'],
  ['href' => base_url('menu.php'),  'label' => 'Menu', 'ic' => '🍛', 'key' => 'menu'],
  ['href' => base_url('cart.php'),  'label' => 'Cart', 'ic' => '🛒', 'key' => 'cart'],
  ['href' => base_url('track.php'), 'label' => 'Track', 'ic' => '📍', 'key' => 'track'],
];
$activeKey = $active ?? 'home';
$cc = cart_count();
?>
<nav class="mobile-tab-bar" role="tablist" aria-label="Mobile">
  <?php foreach ($tabs as $t):
    $isActive = $activeKey === $t['key'] || ($activeKey === '' && $t['key'] === 'home');
    $badge = '';
    if ($t['key'] === 'cart' && $cc > 0) {
      $badge = ' <span class="tab-badge" style="position:absolute;top:2px;transform:translate(14px,-4px);background:var(--jollof);color:#fff;font-size:.65rem;font-weight:800;padding:1px 6px;border-radius:999px;">' . $cc . '</span>';
    }
  ?>
    <a class="tab <?= $isActive ? 'active' : '' ?>" href="<?= e($t['href']) ?>" role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>" style="position:relative;">
      <span class="ic"><?= $t['ic'] ?></span>
      <span><?= e($t['label']) ?></span>
      <?= $badge ?>
    </a>
  <?php endforeach; ?>
</nav>
