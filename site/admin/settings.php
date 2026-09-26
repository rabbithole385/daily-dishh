<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';
admin_require_login();

$adminActive = 'settings';
$adminTitle = 'Settings';
$successMsg = null;
$errors = [];

$fields = [
    'site_name' => 'Restaurant name',
    'tagline' => 'Tagline',
    'strapline' => 'Strapline (used under the tagline)',
    'rc_number' => 'RC Number',
    'address' => 'Address',
    'hours' => 'Opening hours',
    'hours_note' => 'Hours note (small line under opening hours)',
    'phone_primary' => 'Primary phone',
    'phone_secondary' => 'Secondary phone',
    'whatsapp' => 'WhatsApp number (with country code, digits only, e.g. 2348021333972)',
    'email' => 'Email address',
    'facebook_url' => 'Facebook URL',
    'instagram_url' => 'Instagram URL',
];
$deliveryFields = [
    'delivery_eta' => 'Delivery time shown to customers (e.g. 35–60 min)',
    'bank_name' => 'Bank name (for "Transfer now" at checkout, leave blank to hide)',
    'bank_account_name' => 'Account name',
    'bank_account_number' => 'Account number',
];
$llmFields = [
    'llm_provider' => 'AI Assistant provider',
    'llm_model' => 'Model name (leave blank for defaults)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired, please try again.';
    } else {
        foreach ($fields + $deliveryFields + $llmFields as $key => $label) {
            set_setting($pdo, $key, trim($_POST[$key] ?? ''));
        }
        if (!empty($_POST['llm_api_key'])) {
            set_setting($pdo, 'llm_api_key', trim($_POST['llm_api_key']));
        }
        set_setting($pdo, 'delivery_zones', trim($_POST['delivery_zones'] ?? ''));
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                $stmt->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $_SESSION['admin_id']]);
                $successMsg = 'Settings and password updated.';
            }
        }
        if (empty($errors) && $successMsg === null) {
            $successMsg = 'Settings updated.';
        }
    }
}

$values = [];
foreach ($fields + $deliveryFields + $llmFields as $key => $label) {
    $values[$key] = get_setting($pdo, $key, '');
}
$values['delivery_zones'] = get_setting($pdo, 'delivery_zones', '');
$values['llm_api_key']    = get_setting($pdo, 'llm_api_key', '');

include __DIR__ . '/includes/admin-header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="post" action="<?= e(base_url('admin/settings.php')) ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="card">
    <h2>Business Info</h2>
    <div class="form-grid">
      <?php foreach ($fields as $key => $label): ?>
        <div class="form-row">
          <label for="<?= e($key) ?>"><?= e($label) ?></label>
          <?php if ($key === 'address'): ?>
            <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="2"><?= e($values[$key]) ?></textarea>
          <?php else: ?>
            <input id="<?= e($key) ?>" name="<?= e($key) ?>" type="text" value="<?= e($values[$key]) ?>">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2>Delivery &amp; Payment</h2>
    <div class="form-grid">
      <div class="form-row">
        <label for="delivery_zones">Delivery areas and fees: one per line, written as <code>Area | fee</code></label>
        <textarea id="delivery_zones" name="delivery_zones" rows="8" placeholder="Gwarinpa | 1500"><?= e($values['delivery_zones']) ?></textarea>
      </div>
      <?php foreach ($deliveryFields as $key => $label): ?>
        <div class="form-row">
          <label for="<?= e($key) ?>"><?= e($label) ?></label>
          <input id="<?= e($key) ?>" name="<?= e($key) ?>" type="text" value="<?= e($values[$key]) ?>">
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2>AI Assistant — Errands (Errand Boy)</h2>
    <p style="margin-top:-10px;margin-bottom:18px;color:var(--ink-soft);font-size:.9rem;">The chat widget on the public site uses these settings. <b>Free option:</b> pick <b>OpenRouter</b>, grab a free key from openrouter.ai, leave the model on the default Llama free tier, and Errands becomes a real LLM-powered errand boy. If no API key is set, Errands will run on our in-house keyword engine (smart, no cost).</p>
    <div class="form-grid">
      <div class="form-row">
        <label for="llm_provider">Provider</label>
        <select id="llm_provider" name="llm_provider">
          <option value="rules"    <?= $values['llm_provider'] === 'rules'    ? 'selected' : '' ?>>Rule engine only (no API key needed, free)</option>
          <option value="openrouter"<?= $values['llm_provider'] === 'openrouter'? 'selected' : '' ?>>OpenRouter (recommended — free-tier Llama / Mistral available)</option>
          <option value="openai"   <?= $values['llm_provider'] === 'openai'   ? 'selected' : '' ?>>OpenAI</option>
          <option value="anthropic"<?= $values['llm_provider'] === 'anthropic'? 'selected' : '' ?>>Anthropic</option>
        </select>
      </div>
      <div class="form-row">
        <label for="llm_api_key">API key<?= $values['llm_api_key'] ? ' (leave blank to keep current)' : '' ?></label>
        <input id="llm_api_key" name="llm_api_key" type="password" autocomplete="off" placeholder="<?= $values['llm_api_key'] ? '•••••••• current key saved' : 'e.g. sk-or-v1-… (OpenRouter) / sk-… (OpenAI)' ?>">
      </div>
      <div class="form-row">
        <label for="llm_model">Model (blank = defaults: <code>meta-llama/llama-3.1-8b-instruct:free</code> / <code>gpt-4o-mini</code> / <code>claude-3-5-haiku-latest</code>)</label>
        <input id="llm_model" name="llm_model" type="text" value="<?= e($values['llm_model']) ?>" placeholder="e.g. meta-llama/llama-3.1-8b-instruct:free">
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Change Admin Password</h2>
    <div class="form-grid" style="max-width:400px;">
      <div class="form-row">
        <label for="new_password">New password (leave blank to keep current)</label>
        <input id="new_password" name="new_password" type="password" minlength="8">
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-ember">Save Settings</button>
</form>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
