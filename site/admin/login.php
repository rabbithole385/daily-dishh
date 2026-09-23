<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . base_url('admin/index.php'));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Session expired, please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (admin_attempt_login($pdo, $username, $password)) {
            header('Location: ' . base_url('admin/index.php'));
            exit;
        }
        $error = 'Incorrect username or password.';
    }
}
$siteName = get_setting($pdo, 'site_name', 'Daily Dish Restaurant');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — <?= e($siteName) ?></title>
  <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <div class="admin-brand">
        <?php include __DIR__ . '/../includes/logo-mark.php'; ?>
        <span>Daily Dish Admin</span>
      </div>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(base_url('admin/login.php')) ?>" class="form-grid">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-row">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required autofocus>
        </div>
        <div class="form-row">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>
        <button type="submit" class="btn btn-ember" style="width:100%;">Log In</button>
      </form>
    </div>
  </div>
</body>
</html>
