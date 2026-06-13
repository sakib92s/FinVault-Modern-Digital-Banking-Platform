<?php
declare(strict_types=1);
$pageTitle = 'Transactions';
require_once __DIR__ . '/includes/customer_header.php';

$userId = Session::userId();
$period = $_GET['period'] ?? 'last30';
$from   = $_GET['from'] ?? null;
$to     = $_GET['to'] ?? null;
$q      = trim($_GET['q'] ?? '');
$rows   = Transaction::history($userId, $period, $from, $to, $q);

$periods = [
    'today' => 'Today', 'last7' => 'Last 7 days', 'last30' => 'Last 30 days',
    'this_month' => 'This month', 'this_year' => 'This year', 'all' => 'All time', 'custom' => 'Custom range',
];
$qs = http_build_query(['period' => $period, 'from' => $from, 'to' => $to, 'q' => $q]);
?>
<h1 class="page-title">Transaction History</h1>

<div class="card">
  <form method="get" class="filter-bar">
    <select name="period" onchange="this.form.querySelector('.custom-range').style.display=this.value==='custom'?'flex':'none'">
      <?php foreach ($periods as $key => $label): ?>
        <option value="<?= $key ?>" <?= $period === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <span class="custom-range" style="display:<?= $period === 'custom' ? 'flex' : 'none' ?>;gap:8px">
      <input type="date" name="from" value="<?= e((string) $from) ?>">
      <input type="date" name="to" value="<?= e((string) $to) ?>">
    </span>
    <input type="text" name="q" placeholder="Search reference, payee, note..." value="<?= e($q) ?>">
    <button class="btn primary" type="submit">Apply</button>
    <a class="btn ghost" href="<?= BASE_URL ?>/api/statement.php?mode=statement&<?= e($qs) ?>" target="_blank">Export PDF statement</a>
  </form>

  <table class="table">
    <thead><tr><th>Date &amp; time</th><th>Reference</th><th>Type</th><th>Counterparty</th><th>Note</th><th class="right">Amount</th><th class="right">Balance</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted">No transactions found for this filter.</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): $in = in_array($t['type'], ['deposit', 'transfer_in']); ?>
      <tr>
        <td><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
        <td class="mono"><?= e($t['txn_ref']) ?></td>
        <td><span class="tag <?= $in ? 'approved' : 'pending' ?>"><?= e(str_replace('_', ' ', $t['type'])) ?></span></td>
        <td><?= e($t['counterparty_name'] ?: '-') ?><br><small class="mono muted"><?= e($t['counterparty_account'] ?: '') ?></small></td>
        <td><?= e($t['description'] ?: '-') ?></td>
        <td class="right <?= $in ? 'pos' : 'neg' ?>"><?= $in ? '+' : '-' ?><?= money((float) $t['amount']) ?></td>
        <td class="right"><?= money((float) $t['balance_after']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
