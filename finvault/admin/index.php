<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
if (Session::isLoggedIn() && Session::role() === 'admin') {
    redirect('/admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $res = Auth::login($_POST['email'] ?? '', $_POST['password'] ?? '', false, 'admin');
    if ($res['success']) redirect('/admin/dashboard.php');
    $error = $res['error'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign in · <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="auth-body admin">
<div class="auth-wrap">
  <div class="auth-brand">
    <div class="brand-logo big">FV</div>
    <h1><?= APP_NAME ?> Admin</h1>
    <p>Enterprise Control Center</p>
  </div>
  <div class="glass-card auth-card">
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <h2>Administrator sign in</h2>
    <form method="post">
      <?= Session::csrfField() ?>
      <label>Email<input type="email" name="email" required autofocus></label>
      <label>Password<input type="password" name="password" required></label>
      <button class="btn primary block" type="submit">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
