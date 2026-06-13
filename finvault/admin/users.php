<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('admin');
$adminId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $targetId = (int) ($_POST['user_id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'suspend':  User::setStatus($targetId, 'suspended', $adminId); Session::flash('success', 'User suspended.'); break;
        case 'activate': User::setStatus($targetId, 'active', $adminId);    Session::flash('success', 'User activated.'); break;
        case 'delete':   User::delete($targetId, $adminId);                 Session::flash('success', 'User deleted.'); break;
        case 'reset_pw':
            $temp = User::adminResetPassword($targetId, $adminId);
            Session::flash('success', 'Temporary password: ' . $temp . ' (also emailed to the user).');
            break;
        case 'edit':
            User::updateProfile($targetId, $_POST);
            Session::flash('success', 'User profile updated.');
            break;
    }
    redirect('/admin/users.php?q=' . urlencode($_POST['q'] ?? ''));
}

$pageTitle = 'Users';
require_once __DIR__ . '/includes/admin_header.php';

$q    = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$data = User::paginate($q, $page);
$view = isset($_GET['view']) ? User::find((int) $_GET['view']) : null;
?>
<h1 class="page-title">User Management</h1>

<?php if ($view && $view['role'] === 'customer'):
    $acc = Account::forUser((int) $view['id']); ?>
<div class="card">
  <div class="card-head"><h3>User #<?= (int) $view['id'] ?> · <?= e($view['full_name']) ?></h3>
    <a class="btn ghost sm" href="users.php">Back to list</a></div>
  <div class="grid-2">
    <dl class="detail-list">
      <dt>Email</dt><dd><?= e($view['email']) ?></dd>
      <dt>Mobile</dt><dd><?= e($view['mobile']) ?></dd>
      <dt>Status</dt><dd><span class="tag <?= e($view['status']) ?>"><?= e(ucfirst($view['status'])) ?></span></dd>
      <dt>Account</dt><dd class="mono"><?= e($acc['account_number'] ?? '-') ?></dd>
      <dt>Balance</dt><dd><?= $acc ? money((float) $acc['balance']) : '-' ?></dd>
      <dt>Joined</dt><dd><?= date('d M Y', strtotime($view['created_at'])) ?></dd>
      <dt>Last login</dt><dd><?= $view['last_login'] ? date('d M Y H:i', strtotime($view['last_login'])) : '-' ?></dd>
    </dl>
    <form method="post">
      <?= Session::csrfField() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" value="<?= (int) $view['id'] ?>">
      <label>Full name<input type="text" name="full_name" value="<?= e($view['full_name']) ?>" required></label>
      <label>Mobile<input type="text" name="mobile" value="<?= e($view['mobile']) ?>" required></label>
      <label>Address<textarea name="address" rows="2"><?= e($view['address']) ?></textarea></label>
      <div class="grid-2">
        <label>City<input type="text" name="city" value="<?= e($view['city']) ?>"></label>
        <label>State<input type="text" name="state" value="<?= e($view['state']) ?>"></label>
      </div>
      <button class="btn primary" type="submit">Save changes</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <form method="get" class="filter-bar">
    <input type="text" name="q" placeholder="Search name, email or mobile..." value="<?= e($q) ?>">
    <button class="btn primary" type="submit">Search</button>
  </form>

  <table class="table">
    <thead><tr><th>User</th><th>Contact</th><th>Status</th><th>Joined</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php if (!$data['rows']): ?><tr><td colspan="5" class="muted">No users found.</td></tr><?php endif; ?>
    <?php foreach ($data['rows'] as $u): ?>
      <tr>
        <td><strong><?= e($u['full_name']) ?></strong><br><small class="muted">#<?= (int) $u['id'] ?></small></td>
        <td><?= e($u['email']) ?><br><small class="muted"><?= e($u['mobile']) ?></small></td>
        <td><span class="tag <?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td class="right">
          <a class="btn ghost sm" href="?view=<?= (int) $u['id'] ?>">View / Edit</a>
          <form method="post" class="inline"><?= Session::csrfField() ?>
            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
            <?php if ($u['status'] === 'suspended'): ?>
              <button class="btn primary sm" name="action" value="activate">Activate</button>
            <?php else: ?>
              <button class="btn ghost sm" name="action" value="suspend">Suspend</button>
            <?php endif; ?>
            <button class="btn ghost sm" name="action" value="reset_pw" onclick="return confirm('Reset password for this user?')">Reset PW</button>
            <button class="btn danger sm" name="action" value="delete" onclick="return confirm('Permanently delete this user and all data?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($data['pages'] > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
      <a class="page-link <?= $i === $page ? 'active' : '' ?>" href="?q=<?= urlencode($q) ?>&p=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
