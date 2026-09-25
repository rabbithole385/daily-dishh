<?php
$orderStatus = $orderStatus ?? 'pending';
$stages = [
  ['key' => 'pending',   'label' => 'Order sent',      'ic' => '📋'],
  ['key' => 'confirmed', 'label' => 'Kitchen confirmed','ic' => '✅'],
  ['key' => 'cooking',   'label' => 'Cooking',         'ic' => '👨‍🍳'],
  ['key' => 'out',       'label' => 'Out for delivery','ic' => '🛵'],
  ['key' => 'delivered', 'label' => 'Delivered',       'ic' => '🍴'],
];
$statusMap = [
  'pending' => 'pending',
  'confirmed' => 'confirmed',
  'preparing' => 'cooking',
  'out_for_delivery' => 'out',
  'delivered' => 'delivered',
];
$mappedStatus = $statusMap[$orderStatus] ?? $orderStatus;
$activeIdx = 0;
foreach ($stages as $i => $s) { if ($s['key'] === $mappedStatus) { $activeIdx = $i; break; } }
$isDelivered = $mappedStatus === 'delivered';
if ($isDelivered) $activeIdx = count($stages) - 1;
?>
<ul class="status-timeline">
  <?php foreach ($stages as $i => $s):
    $cls = '';
    if ($isDelivered || $i < $activeIdx) $cls = 'done';
    elseif ($i === $activeIdx) $cls = 'active';
  ?>
    <li class="tl-stage <?= $cls ?>">
      <div class="tl-dot"><?= $s['ic'] ?></div>
      <div class="tl-label"><?= e($s['label']) ?></div>
    </li>
  <?php endforeach; ?>
</ul>
