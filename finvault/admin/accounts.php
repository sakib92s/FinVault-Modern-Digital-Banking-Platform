<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('admin');
$adminId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    switch ($_POST['action'] ?? '') {
        case 'freeze':   Account::setStatus((int) $_POST['account_id'], 'frozen', $adminId); Session::flash('success', 'Account frozen.'); break;
        case 'unfreeze': Account::setStatus((int) $_POST['account_id'], 'active', $adminId); Session::flash('success', 'Account unfrozen.'); break;
        case 'create':
            $user = User::find((int) $_POST['user_id']);
            if ($user && $user['role'] === 'customer') {
                $acc = Account::create((int) $user['id'], $_POST['account_type'] === 'current' ? 'current' : 'savings');
                Session::flash('success', 'Account ' . $acc['account_number'] . ' created.');
            } else Session::flash('error', 'Customer not found.');
            break;
        case 'adjust':
            $res = Account::adminAdjust((int) $_POST['account_id'], (float) $_POST['amount'],
                $_POST['type'] === 'withdrawal' ? 'withdrawal' : 'deposit', $adminId, trim($_POST['note'] ?? 'Admin adjustment'));
            Session::flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Balance adjusted.' : $res['error']);
            break;
    }
    redirect('/admin/accounts.php?q=' . urlencode($_POST['q'] ?? ''));
}

$pageTitle = 'Accounts';
require_once __DIR__ . '/includes/admin_header.php';

$q  = trim($_GET['q'] ?? '');
$db = Database::get();
$like = '%' . $q . '%';
$sql = 'SELECT a.*, u.full_name, u.email FROM accounts a JOIN users u ON u.id = a.user_id';
$params = [];
if ($q !== '') { $sql .= ' WHERE a.account_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?'; $params = [$like, $like, $like]; }
$sql .= ' ORDER BY a.created_at DESC LIMIT 100';
$stmt = $db->prepare($sql); $stmt->execute($params);
$accounts = $stmt->fetchAll();

$history = null;
if (isset($_GET['history'])) {
    $h = $db->prepare('SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 50');
    $h->execute([(int) $_GET['history']]);
    $history = $h->fetchAll();
}
?>
<h1 class="page-title">Account Management</h1>

<div class="card">
  <h3>Create account for an existing customer</h3>
  <form method="post" class="filter-bar">
    <?= Session::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <input type="number" name="user_id" placeholder="Customer user ID" required>
    <select name="account_type"><option value="savings">Savings</option><option value="current">Current</option></select>
    <button class="btn primary" type="submit">Create account</button>
  </form>
</div>

<div class="card">
  <form method="get" class="filter-bar">
    <input type="text" name="q" placeholder="Search account number or customer..." value="<?= e($q) ?>">
    <button class="btn primary" type="submit">Search</button>
  </form>

  <table class="table">
    <thead><tr><th>Account</th><th>Customer</th><th>Type</th><th class="right">Balance</th><th>Status</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): ?>
      <tr>
        <td class="mono"><?= e($a['account_number']) ?></td>
        <td><?= e($a['full_name']) ?><br><small class="muted"><?= e($a['email']) ?></small></td>
        <td><?= e(ucfirst($a['account_type'])) ?></td>
        <td class="right"><strong><?= money((float) $a['balance']) ?></strong></td>
        <td><span class="tag <?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
        <td class="right">
          <a class="btn ghost sm" href="?history=<?= (int) $a['id'] ?>&q=<?= urlencode($q) ?>">History</a>
          <form method="post" class="inline"><?= Session::csrfField() ?>
            <input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
            <?php if ($a['status'] === 'frozen'): ?>
              <button class="btn primary sm" name="action" value="unfreeze">Unfreeze</button>
            <?php elseif ($a['status'] === 'active'): ?>
              <button class="btn danger sm" name="action" value="freeze">Freeze</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php if ($history !== null && isset($_GET['history']) && (int) $_GET['history'] === (int) $a['id']): ?>
      <tr><td colspan="6">
        <div class="sub-panel">
          <h4>Last 50 transactions · <?= e($a['account_number']) ?></h4>
          <table class="table compact">
            <thead><tr><th>Date</th><th>Ref</th><th>Type</th><th class="right">Amount</th><th class="right">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($history as $t): ?>
              <tr><td><?= date('d M H:i', strtotime($t['created_at'])) ?></td>
                  <td class="mono"><?= e($t['txn_ref']) ?></td>
                  <td><?= e(str_replace('_', ' ', $t['type'])) ?></td>
                  <td class="right"><?= money((float) $t['amount']) ?></td>
                  <td class="right"><?= money((float) $t['balance_after']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <h4>Adjust balance (simulation)</h4>
          <form method="post" class="filter-bar"><?= Session::csrfField() ?>
            <input type="hidden" name="action" value="adjust">
            <input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
            <select name="type"><option value="deposit">Credit</option><option value="withdrawal">Debit</option></select>
            <input type="number" name="amount" min="1" step="0.01" placeholder="Amount" required>
            <input type="text" name="note" placeholder="Note">
            <button class="btn primary" type="submit">Apply</button>
          </form>
        </div>
      </td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
