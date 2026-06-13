<?php
declare(strict_types=1);
$pageTitle = 'Transactions';
require_once __DIR__ . '/includes/admin_header.php';

$q      = trim($_GET['q'] ?? '');
$period = $_GET['period'] ?? 'last30';
$large  = isset($_GET['large']);
$rows   = Transaction::adminList($q, $period, $large);

$periods = ['today' => 'Today', 'last7' => 'Last 7 days', 'last30' => 'Last 30 days',
            'this_month' => 'This month', 'this_year' => 'This year', 'all' => 'All time'];
?>
<h1 class="page-title">Transaction Monitoring</h1>

<div class="card">
  <form method="get" class="filter-bar">
    <select name="period">
      <?php foreach ($periods as $key => $label): ?>
        <option value="<?= $key ?>" <?= $period === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Search reference, customer or account..." value="<?= e($q) ?>">
    <label class="check"><input type="checkbox" name="large" value="1" <?= $large ? 'checked' : '' ?>> Large transfers only (≥ <?= money(LARGE_TRANSFER_THRESHOLD) ?>)</label>
    <button class="btn primary" type="submit">Apply</button>
    <a class="btn ghost" href="<?= BASE_URL ?>/api/statement.php?mode=admin_report&period=<?= e($period) ?>&q=<?= urlencode($q) ?><?= $large ? '&large=1' : '' ?>" target="_blank">Export PDF report</a>
  </form>

  <table class="table">
    <thead><tr><th>Date &amp; time</th><th>Reference</th><th>Customer</th><th>Account</th><th>Type</th><th>Counterparty</th><th class="right">Amount</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted">No transactions found.</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): $flag = (float) $t['amount'] >= LARGE_TRANSFER_THRESHOLD && $t['type'] === 'transfer_out'; ?>
      <tr class="<?= $flag ? 'flagged' : '' ?>">
        <td><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
        <td class="mono"><?= e($t['txn_ref']) ?><?= $flag ? ' &#9888;' : '' ?></td>
        <td><?= e($t['full_name']) ?></td>
        <td class="mono"><?= e($t['account_number']) ?></td>
        <td><span class="tag"><?= e(str_replace('_', ' ', $t['type'])) ?></span></td>
        <td><?= e($t['counterparty_name'] ?: '-') ?></td>
        <td class="right"><strong><?= money((float) $t['amount']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
