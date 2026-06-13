<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Auth::tryRememberLogin();
if (Session::isLoggedIn() && Session::role() === 'customer') {
    redirect('/customer/dashboard.php');
}

$page    = $_GET['page'] ?? 'login';
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    switch ($_POST['action'] ?? '') {

        case 'login':
            $res = Auth::login($_POST['email'] ?? '', $_POST['password'] ?? '', isset($_POST['remember']));
            if ($res['success']) redirect('/customer/dashboard.php');
            if (!empty($res['unverified_user_id'])) {
                $_SESSION['pending_verify'] = $res['unverified_user_id'];
                Auth::sendOtp((int) $res['unverified_user_id']);
                redirect('/customer/index.php?page=verify');
            }
            $error = $res['error'];
            break;

        case 'register':
            $data = $_POST;
            if (!empty($_FILES['profile_photo']['name'])) {
                $data['profile_photo'] = User::saveUpload($_FILES['profile_photo'], 'profile');
            }
            $res = Auth::register($data);
            if ($res['success']) {
                $_SESSION['pending_verify'] = $res['user_id'];
                redirect('/customer/index.php?page=verify');
            }
            $error = $res['error'];
            $page  = 'register';
            break;

        case 'verify':
            $uid = (int) ($_SESSION['pending_verify'] ?? 0);
            if ($uid === 0) { $error = 'Session expired. Please log in again.'; $page = 'login'; break; }
            $res = Auth::verifyOtp($uid, trim($_POST['otp'] ?? ''));
            if ($res['success']) {
                unset($_SESSION['pending_verify']);
                Session::flash('success', 'Email verified! Your account is active - please sign in.');
                redirect('/customer/index.php');
            }
            $error = $res['error'];
            $page  = 'verify';
            break;

        case 'resend':
            $uid = (int) ($_SESSION['pending_verify'] ?? 0);
            if ($uid > 0) { Auth::sendOtp($uid); $success = 'A new OTP has been sent to your email.'; }
            $page = 'verify';
            break;

        case 'forgot':
            Auth::requestPasswordReset($_POST['email'] ?? '');
            $success = 'If that email exists, a reset link has been sent (check logs/mail.log in dev mode).';
            $page = 'forgot';
            break;

        case 'reset':
            if (($_POST['password'] ?? '') !== ($_POST['password2'] ?? '')) {
                $error = 'Passwords do not match.';
                $page  = 'reset';
                break;
            }
            $res = Auth::resetPassword($_POST['selector'] ?? '', $_POST['token'] ?? '', $_POST['password'] ?? '');
            if ($res['success']) {
                Session::flash('success', 'Password reset successful. Please sign in.');
                redirect('/customer/index.php');
            }
            $error = $res['error'];
            $page  = 'reset';
            break;
    }
}
$flashes = Session::pullFlashes();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>window.FV = { base: '<?= BASE_URL ?>', csrf: '<?= Session::csrfToken() ?>' };</script>
</head>
<body class="auth-body">
<div class="auth-wrap">
  <div class="auth-brand">
    <div class="brand-logo big">FV</div>
    <h1><?= APP_NAME ?></h1>
    <p><?= APP_TAGLINE ?></p>
    <small class="sim-note">Educational banking simulation · no real money</small>
  </div>

  <div class="glass-card auth-card">
    <?php foreach ($flashes as $t => $m): ?><div class="alert <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <?php if ($page === 'login'): ?>
      <h2>Welcome back</h2>
      <form method="post">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="login">
        <label>Email<input type="email" name="email" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <div class="row between">
          <label class="check"><input type="checkbox" name="remember"> Remember me</label>
          <a href="?page=forgot">Forgot password?</a>
        </div>
        <button class="btn primary block" type="submit">Sign in</button>
      </form>
      <p class="auth-switch">New to FinVault? <a href="?page=register">Open an account</a></p>

    <?php elseif ($page === 'register'): ?>
      <h2>Open your account</h2>
      <form method="post" enctype="multipart/form-data">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="register">
        <div class="grid-2">
          <label>Full name<input type="text" name="full_name" required></label>
          <label>Date of birth<input type="date" name="dob" required max="<?= date('Y-m-d', strtotime('-18 years')) ?>"></label>
          <label>Gender
            <select name="gender" required>
              <option value="">Select</option><option value="male">Male</option>
              <option value="female">Female</option><option value="other">Other</option>
            </select>
          </label>
          <label>Mobile<input type="tel" name="mobile" pattern="[6-9][0-9]{9}" required></label>
          <label>Email<input type="email" name="email" required></label>
          <label>Profile photo<input type="file" name="profile_photo" accept="image/*"></label>
          <label>PAN number<input type="text" name="pan_number" maxlength="10" placeholder="ABCDE1234F"></label>
          <label>Aadhaar number<input type="text" name="aadhaar_number" maxlength="12" pattern="[0-9]{12}"></label>
          <label>City<input type="text" name="city"></label>
          <label>State<input type="text" name="state"></label>
        </div>
        <label>Address<textarea name="address" rows="2" required></textarea></label>
        <label>Password<input type="password" name="password" minlength="8" required></label>
        <button class="btn primary block" type="submit">Create account</button>
      </form>
      <p class="auth-switch">Already have an account? <a href="?page=login">Sign in</a></p>

    <?php elseif ($page === 'verify'): ?>
      <h2>Verify your email</h2>
      <p>Enter the 6-digit code we sent to your email. It expires in <?= OTP_EXPIRY_MINUTES ?> minutes.</p>
      <form method="post">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="verify">
        <label>OTP code<input class="otp-input" type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required autofocus></label>
        <button class="btn primary block" type="submit">Verify</button>
      </form>
      <form method="post" class="mt-1">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="resend">
        <button class="btn ghost block" type="submit">Resend OTP</button>
      </form>

    <?php elseif ($page === 'forgot'): ?>
      <h2>Forgot password</h2>
      <form method="post">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="forgot">
        <label>Email<input type="email" name="email" required autofocus></label>
        <button class="btn primary block" type="submit">Send reset link</button>
      </form>
      <p class="auth-switch"><a href="?page=login">Back to sign in</a></p>

    <?php elseif ($page === 'reset'): ?>
      <h2>Set a new password</h2>
      <form method="post">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="selector" value="<?= e($_GET['selector'] ?? '') ?>">
        <input type="hidden" name="token" value="<?= e($_GET['token'] ?? '') ?>">
        <label>New password<input type="password" name="password" minlength="8" required autofocus></label>
        <label>Confirm password<input type="password" name="password2" minlength="8" required></label>
        <button class="btn primary block" type="submit">Reset password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
