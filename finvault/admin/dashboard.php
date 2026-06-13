<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$db = Database::get();
$stats = $db->query(
    "SELECT
       (SELECT COUNT(*) FROM users WHERE role = 'customer')                                          AS total_users,
       (SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active')                    AS active_users,
       (SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= NOW() - INTERVAL 30 DAY) AS new_users,
       (SELECT COUNT(*) FROM accounts)                                                               AS total_accounts,
       (SELECT COUNT(*) FROM transactions)                                                           AS total_txns,
       (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'transfer_out')               AS volume,
       (SELECT COUNT(*) FROM loans)                                                                  AS total_loans,
       (SELECT COUNT(*) FROM loans WHERE status = 'pending')                                         AS pending_loans,
       (SELECT COUNT(*) FROM kyc_documents WHERE status IN ('pending','reupload'))                   AS pending_kyc,
       (SELECT COUNT(*) FROM cards WHERE status = 'requested')                                       AS card_requests"
)->fetch();

$recentTxns  = Transaction::adminList('', 'all', false, 8);
$largeTxns   = Transaction::adminList('', 'last30', true, 5);
$recentAudit = AuditLog::recent(8);
?>
<h1 class="page-title">Admin Dashboard</h1>

<div class="stat-grid">
  <div class="stat-card"><small>Total Users</small><strong><?= (int) $stats['total_users'] ?></strong></div>
  <div class="stat-card"><small>Active Users</small><strong><?= (int) $stats['active_users'] ?></strong></div>
  <div class="stat-card"><small>New (30 days)</small><strong><?= (int) $stats['new_users'] ?></strong></div>
  <div class="stat-card"><small>Total Accounts</small><strong><?= (int) $stats['total_accounts'] ?></strong></div>
  <div class="stat-card"><small>Total Transactions</small><strong><?= (int) $stats['total_txns'] ?></strong></div>
  <div class="stat-card"><small>Transfer Volume</small><strong><?= money((float) $stats['volume']) ?></strong></div>
  <div class="stat-card"><small>Total Loans</small><strong><?= (int) $stats['total_loans'] ?></strong></div>
  <div class="stat-card alert-card"><small>Pending Loans</small><strong><?= (int) $stats['pending_loans'] ?></strong>
    <a href="loans.php?status=pending">Review</a></div>
  <div class="stat-card alert-card"><small>Pending KYC</small><strong><?= (int) $stats['pending_kyc'] ?></strong>
    <a href="kyc.php">Review</a></div>
  <div class="stat-card alert-card"><small>Card Requests</small><strong><?= (int) $stats['card_requests'] ?></strong>
    <a href="cards.php?status=requested">Review</a></div>
</div>

<div class="grid-main">
  <div class="card">
    <div class="card-head"><h3>Latest transactions</h3><a class="btn ghost sm" href="transactions.php">View all</a></div>
    <table class="table">
      <thead><tr><th>Date</th><th>Reference</th><th>Customer</th><th>Type</th><th class="right">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($recentTxns as $t): ?>
        <tr>
          <td><?= date('d M H:i', strtotime($t['created_at'])) ?></td>
          <td class="mono"><?= e($t['txn_ref']) ?></td>
          <td><?= e($t['full_name']) ?></td>
          <td><span class="tag"><?= e(str_replace('_', ' ', $t['type'])) ?></span></td>
          <td class="right"><?= money((float) $t['amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="side-stack">
    <div class="card">
      <div class="card-head"><h3>&#9888; Large transfers (30d)</h3>
        <a class="btn ghost sm" href="transactions.php?large=1">All</a></div>
      <ul class="notif-list compact">
        <?php if (!$largeTxns): ?><li class="muted">No transfers above <?= money(LARGE_TRANSFER_THRESHOLD) ?>.</li><?php endif; ?>
        <?php foreach ($largeTxns as $t): if ($t['type'] !== 'transfer_out') continue; ?>
          <li class="notif-item unread"><div>
            <strong><?= money((float) $t['amount']) ?> · <?= e($t['full_name']) ?></strong>
            <p class="mono"><?= e($t['txn_ref']) ?> · <?= date('d M H:i', strtotime($t['created_at'])) ?></p>
          </div></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h3>Recent audit activity</h3>
      <ul class="audit-mini">
        <?php foreach ($recentAudit as $log): ?>
          <li><span class="mono"><?= e($log['action']) ?></span> <?= e($log['full_name'] ?? 'System') ?>
            <small class="muted"><?= date('d M H:i', strtotime($log['created_at'])) ?> · <?= e($log['ip_address']) ?></small></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
