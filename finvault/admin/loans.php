<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('admin');
$adminId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $res = Loan::review((int) $_POST['loan_id'], $_POST['decision'] ?? '', trim($_POST['remarks'] ?? ''), $adminId);
    Session::flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Loan updated.' : $res['error']);
    redirect('/admin/loans.php?status=' . urlencode($_POST['status_filter'] ?? ''));
}

$pageTitle = 'Loans';
require_once __DIR__ . '/includes/admin_header.php';

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$loans  = Loan::adminList($status, $q);
?>
<h1 class="page-title">Loan Management</h1>

<div class="card">
  <form method="get" class="filter-bar">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Search reference or customer..." value="<?= e($q) ?>">
    <button class="btn primary" type="submit">Apply</button>
  </form>

  <table class="table">
    <thead><tr><th>Ref</th><th>Customer</th><th>Type</th><th class="right">Amount</th><th class="right">EMI</th><th>Status</th><th class="right">Decision</th></tr></thead>
    <tbody>
    <?php if (!$loans): ?><tr><td colspan="7" class="muted">No loans found.</td></tr><?php endif; ?>
    <?php foreach ($loans as $l): ?>
      <tr>
        <td class="mono"><?= e($l['loan_ref']) ?><br><small class="muted"><?= date('d M Y', strtotime($l['created_at'])) ?></small></td>
        <td><?= e($l['full_name']) ?><br><small class="muted"><?= e($l['email']) ?></small></td>
        <td><?= e(ucfirst($l['loan_type'])) ?><br><small class="muted"><?= (int) $l['tenure_months'] ?> mo · <?= e($l['interest_rate']) ?>%</small></td>
        <td class="right"><?= money((float) $l['amount']) ?></td>
        <td class="right"><?= money((float) $l['emi']) ?></td>
        <td><span class="tag <?= e($l['status']) ?>"><?= e(ucfirst($l['status'])) ?></span>
          <?php if ($l['admin_remarks']): ?><br><small class="muted"><?= e($l['admin_remarks']) ?></small><?php endif; ?></td>
        <td class="right">
          <?php if ($l['status'] === 'pending'): ?>
          <form method="post" class="stack-form"><?= Session::csrfField() ?>
            <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="status_filter" value="<?= e($status) ?>">
            <input type="text" name="remarks" placeholder="Remarks (optional)">
            <div class="row gap">
              <button class="btn primary sm" name="decision" value="approved">Approve</button>
              <button class="btn danger sm" name="decision" value="rejected">Reject</button>
            </div>
          </form>
          <?php else: ?><small class="muted">Decided</small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
