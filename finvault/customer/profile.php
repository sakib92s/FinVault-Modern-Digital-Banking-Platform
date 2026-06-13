<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('customer');
$userId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    switch ($_POST['action'] ?? '') {
        case 'profile':
            User::updateProfile($userId, $_POST);
            if (!empty($_FILES['profile_photo']['name'])) {
                $path = User::saveUpload($_FILES['profile_photo'], 'profile');
                if ($path) User::setProfilePhoto($userId, $path);
            }
            Session::flash('success', 'Profile updated successfully.');
            redirect('/customer/profile.php');

        case 'password':
            if (($_POST['new'] ?? '') !== ($_POST['confirm'] ?? '')) {
                Session::flash('error', 'New passwords do not match.');
            } else {
                $res = Auth::changePassword($userId, $_POST['current'] ?? '', $_POST['new'] ?? '');
                Session::flash($res['success'] ? 'success' : 'error',
                    $res['success'] ? 'Password changed successfully.' : $res['error']);
            }
            redirect('/customer/profile.php');
    }
}

$pageTitle = 'Profile';
require_once __DIR__ . '/includes/customer_header.php';
?>
<h1 class="page-title">Profile Settings</h1>

<div class="grid-2-cards">
  <div class="card">
    <h3>Update profile</h3>
    <form method="post" enctype="multipart/form-data">
      <?= Session::csrfField() ?>
      <input type="hidden" name="action" value="profile">
      <label>Full name<input type="text" name="full_name" value="<?= e($currentUser['full_name']) ?>" required></label>
      <label>Mobile<input type="tel" name="mobile" pattern="[6-9][0-9]{9}" value="<?= e($currentUser['mobile']) ?>" required></label>
      <label>Address<textarea name="address" rows="2" required><?= e($currentUser['address']) ?></textarea></label>
      <div class="grid-2">
        <label>City<input type="text" name="city" value="<?= e($currentUser['city']) ?>"></label>
        <label>State<input type="text" name="state" value="<?= e($currentUser['state']) ?>"></label>
      </div>
      <label>Profile photo<input type="file" name="profile_photo" accept="image/*"></label>
      <button class="btn primary" type="submit">Save changes</button>
    </form>
  </div>

  <div class="card">
    <h3>Change password</h3>
    <form method="post">
      <?= Session::csrfField() ?>
      <input type="hidden" name="action" value="password">
      <label>Current password<input type="password" name="current" required></label>
      <label>New password<input type="password" name="new" minlength="8" required></label>
      <label>Confirm new password<input type="password" name="confirm" minlength="8" required></label>
      <button class="btn primary" type="submit">Change password</button>
    </form>
    <hr>
    <h3>Recent security activity</h3>
    <ul class="audit-mini">
      <?php foreach (AuditLog::forUser($userId, 6) as $log): ?>
        <li><span class="mono"><?= e($log['action']) ?></span>
            <small class="muted"><?= date('d M H:i', strtotime($log['created_at'])) ?> · IP <?= e($log['ip_address']) ?></small></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
