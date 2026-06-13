<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/customer_header.php';

$userId   = Session::userId();
$account  = Account::forUser($userId);
$summary  = Transaction::summary($userId);
$recent   = Transaction::history($userId, 'all', null, null, '', 6);
$notifs   = Notification::forUser($userId, 5);
$bens     = Beneficiary::forUser($userId);
$loans    = array_filter(Loan::forUser($userId), fn ($l) => $l['status'] === 'approved');
$cards    = Card::forUser($userId);
$kycState = KYC::overallStatus($userId);

$activeCard = null;
foreach ($cards as $c) { if ($c['status'] === 'active') { $activeCard = $c; break; } }

$tasks = [];
if ($kycState === 'incomplete')       $tasks[] = ['Complete your KYC verification', 'kyc.php'];
if ($kycState === 'action_required')  $tasks[] = ['KYC needs your attention (re-upload required)', 'kyc.php'];
if (!$bens)                            $tasks[] = ['Add your first beneficiary', 'beneficiaries.php'];
if (!$cards)                           $tasks[] = ['Request your debit card', 'cards.php'];
?>
<div class="welcome-card glass-card">
  <div>
    <h1>Welcome back, <?= e(explode(' ', $currentUser['full_name'])[0]) ?> &#128075;</h1>
    <p class="muted">Account <?= e($account['account_number'] ?? '-') ?> · <?= e(ucfirst($account['account_type'] ?? '')) ?> account</p>
  </div>
  <div class="balance-display">
    <small>Available balance</small>
    <strong><?= money((float) ($account['balance'] ?? 0)) ?></strong>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card"><small>Current Balance</small><strong><?= money((float) ($account['balance'] ?? 0)) ?></strong></div>
  <div class="stat-card"><small>Total Deposits</small><strong><?= money((float) $summary['total_in']) ?></strong></div>
  <div class="stat-card"><small>Total Transfers</small><strong><?= (int) $summary['transfer_count'] ?></strong></div>
  <div class="stat-card"><small>Beneficiaries</small><strong><?= count($bens) ?></strong></div>
  <div class="stat-card"><small>Active Loans</small><strong><?= count($loans) ?></strong></div>
  <div class="stat-card"><small>Card Status</small><strong><?= $activeCard ? ucfirst($activeCard['card_type']) . ' active' : 'No active card' ?></strong></div>
</div>

<div class="grid-main">
  <div class="card">
    <div class="card-head"><h3>Recent transactions</h3><a class="btn ghost sm" href="transactions.php">View all</a></div>
    <table class="table">
      <thead><tr><th>Date</th><th>Reference</th><th>Details</th><th class="right">Amount</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="4" class="muted">No transactions yet.</td></tr><?php endif; ?>
      <?php foreach ($recent as $t): $in = in_array($t['type'], ['deposit', 'transfer_in']); ?>
        <tr>
          <td><?= date('d M', strtotime($t['created_at'])) ?></td>
          <td class="mono"><?= e($t['txn_ref']) ?></td>
          <td><?= e($t['counterparty_name'] ?: ucfirst(str_replace('_', ' ', $t['type']))) ?></td>
          <td class="right <?= $in ? 'pos' : 'neg' ?>"><?= $in ? '+' : '-' ?><?= money((float) $t['amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="side-stack">
    <div class="card">
      <h3>Quick actions</h3>
      <div class="quick-actions">
        <a class="btn primary" href="transfer.php">Send money</a>
        <a class="btn ghost" href="beneficiaries.php">Add payee</a>
        <a class="btn ghost" href="loans.php">Apply loan</a>
        <a class="btn ghost" href="transfer.php?tab=qr">QR pay</a>
      </div>
    </div>

    <?php if ($tasks): ?>
    <div class="card">
      <h3>Pending tasks</h3>
      <ul class="task-list">
        <?php foreach ($tasks as [$label, $link]): ?>
          <li><a href="<?= e($link) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3>Notifications</h3><a class="btn ghost sm" href="notifications.php">All</a></div>
      <ul class="notif-list compact">
        <?php if (!$notifs): ?><li class="muted">Nothing new.</li><?php endif; ?>
        <?php foreach ($notifs as $n): ?>
          <li class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
            <div><strong><?= e($n['title']) ?></strong><p><?= e($n['message']) ?></p></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
