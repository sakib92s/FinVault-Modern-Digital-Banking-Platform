<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('admin');
$adminId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $res = Card::review((int) $_POST['card_id'], $_POST['decision'] ?? '', $adminId, trim($_POST['remarks'] ?? ''));
    Session::flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Card updated.' : $res['error']);
    redirect('/admin/cards.php?status=' . urlencode($_POST['status_filter'] ?? ''));
}

$pageTitle = 'Cards';
require_once __DIR__ . '/includes/admin_header.php';

$status = $_GET['status'] ?? '';
$cards  = Card::adminList($status);
?>
<h1 class="page-title">Card Management</h1>

<div class="card">
  <form method="get" class="filter-bar">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach (['requested', 'active', 'blocked', 'rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn primary" type="submit">Apply</button>
  </form>

  <table class="table">
    <thead><tr><th>Customer</th><th>Type</th><th>Card number</th><th>Status</th><th>Requested</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php if (!$cards): ?><tr><td colspan="6" class="muted">No cards found.</td></tr><?php endif; ?>
    <?php foreach ($cards as $c): ?>
      <tr>
        <td><?= e($c['full_name']) ?><br><small class="muted"><?= e($c['email']) ?></small></td>
        <td><?= e(ucfirst($c['card_type'])) ?></td>
        <td class="mono"><?= $c['card_number'] ? 'XXXX XXXX XXXX ' . substr($c['card_number'], -4) : '-' ?></td>
        <td><span class="tag <?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
        <td class="right">
          <form method="post" class="inline"><?= Session::csrfField() ?>
            <input type="hidden" name="card_id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="status_filter" value="<?= e($status) ?>">
            <?php if ($c['status'] === 'requested'): ?>
              <button class="btn primary sm" name="decision" value="approve">Approve</button>
              <button class="btn danger sm" name="decision" value="reject">Reject</button>
            <?php elseif ($c['status'] === 'active'): ?>
              <button class="btn danger sm" name="decision" value="block">Block</button>
            <?php elseif ($c['status'] === 'blocked'): ?>
              <button class="btn primary sm" name="decision" value="unblock">Unblock</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
