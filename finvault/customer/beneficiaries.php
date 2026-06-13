<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('customer');
$userId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    switch ($_POST['action'] ?? '') {
        case 'add':
            $res = Beneficiary::add($userId, $_POST);
            Session::flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Beneficiary added.' : $res['error']);
            break;
        case 'edit':
            Beneficiary::update($userId, (int) $_POST['id'], $_POST);
            Session::flash('success', 'Beneficiary updated.');
            break;
        case 'delete':
            Beneficiary::delete($userId, (int) $_POST['id']);
            Session::flash('success', 'Beneficiary removed.');
            break;
    }
    redirect('/customer/beneficiaries.php');
}

$pageTitle = 'Beneficiaries';
require_once __DIR__ . '/includes/customer_header.php';
$bens = Beneficiary::forUser($userId);
$edit = null;
if (isset($_GET['edit'])) $edit = Beneficiary::find($userId, (int) $_GET['edit']);
?>
<h1 class="page-title">Beneficiary Management</h1>

<div class="grid-2-cards">
  <div class="card">
    <h3><?= $edit ? 'Edit beneficiary' : 'Add beneficiary' ?></h3>
    <form method="post">
      <?= Session::csrfField() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>

      <?php if (!$edit): ?>
      <label>Search FinVault customers (name, account, email or mobile)
        <div class="autocomplete-wrap">
          <input type="text" id="benSearch" placeholder="Type 'Rah' or '9876'..." autocomplete="off">
          <div class="search-results" id="benResults"></div>
        </div>
      </label>
      <?php endif; ?>

      <label>Name<input type="text" name="name" id="benName" value="<?= e($edit['name'] ?? '') ?>" required></label>
      <?php if (!$edit): ?>
      <label>Account number<input type="text" name="account_number" id="benAccount" pattern="[0-9]{12}" required></label>
      <?php else: ?>
      <label>Account number<input type="text" value="<?= e($edit['account_number']) ?>" disabled></label>
      <?php endif; ?>
      <div class="grid-2">
        <label>Email (optional)<input type="email" name="email" id="benEmail" value="<?= e($edit['email'] ?? '') ?>"></label>
        <label>Mobile (optional)<input type="tel" name="mobile" id="benMobile" value="<?= e($edit['mobile'] ?? '') ?>"></label>
      </div>
      <div class="row gap">
        <button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Add beneficiary' ?></button>
        <?php if ($edit): ?><a class="btn ghost" href="beneficiaries.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Saved beneficiaries (<?= count($bens) ?>)</h3>
    <table class="table">
      <thead><tr><th>Name</th><th>Account</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php if (!$bens): ?><tr><td colspan="4" class="muted">No beneficiaries added yet.</td></tr><?php endif; ?>
      <?php foreach ($bens as $b): ?>
        <tr>
          <td><strong><?= e($b['name']) ?></strong><br><small class="muted"><?= e($b['email'] ?: $b['mobile'] ?: '') ?></small></td>
          <td class="mono"><?= e($b['account_number']) ?></td>
          <td><span class="tag <?= $b['verified'] ? 'approved' : 'pending' ?>"><?= $b['verified'] ? 'Verified' : 'Pending' ?></span></td>
          <td class="right">
            <a class="btn ghost sm" href="?edit=<?= (int) $b['id'] ?>">Edit</a>
            <a class="btn ghost sm" href="transfer.php">Send</a>
            <form method="post" class="inline" onsubmit="return confirm('Remove this beneficiary?')">
              <?= Session::csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn danger sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
