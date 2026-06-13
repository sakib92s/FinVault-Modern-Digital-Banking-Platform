<?php
declare(strict_types=1);
$pageTitle = 'Analytics';
require_once __DIR__ . '/includes/admin_header.php';

$db     = Database::get();
$period = $_GET['period'] ?? 'last30';
$from   = $_GET['from'] ?? null;
$to     = $_GET['to'] ?? null;
[$start, $end] = Transaction::dateRange($period, $from, $to);

/* ----- Executive KPIs: current period vs previous equal-length period ----- */
$lenSec    = max(86400, strtotime($end) - strtotime($start));
$prevStart = date('Y-m-d H:i:s', strtotime($start) - $lenSec);
$prevEnd   = $start;

function kpiPair(PDO $db, string $sql, string $s1, string $e1, string $s2, string $e2): array
{
    $stmt = $db->prepare($sql); $stmt->execute([$s1, $e1]); $cur  = (float) $stmt->fetch()['v'];
    $stmt = $db->prepare($sql); $stmt->execute([$s2, $e2]); $prev = (float) $stmt->fetch()['v'];
    $pct = $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : ($cur > 0 ? 100.0 : 0.0);
    return [$cur, $pct];
}

[$curUsers, $usersPct] = kpiPair($db,
    "SELECT COUNT(*) v FROM users WHERE role='customer' AND created_at BETWEEN ? AND ?", $start, $end, $prevStart, $prevEnd);
[$curTxns, $txnsPct] = kpiPair($db,
    'SELECT COUNT(*) v FROM transactions WHERE created_at BETWEEN ? AND ?', $start, $end, $prevStart, $prevEnd);
[$curVol, $volPct] = kpiPair($db,
    "SELECT COALESCE(SUM(amount),0) v FROM transactions WHERE type='transfer_out' AND created_at BETWEEN ? AND ?",
    $start, $end, $prevStart, $prevEnd);

$loanStats = $db->query(
    "SELECT COUNT(*) total, SUM(status='approved') approved FROM loans"
)->fetch();
$loanRate = ($loanStats['total'] ?? 0) > 0 ? round($loanStats['approved'] / $loanStats['total'] * 100, 1) : 0;

$kycStats = $db->query(
    "SELECT (SELECT COUNT(DISTINCT user_id) FROM kyc_documents) uploaded,
            (SELECT COUNT(*) FROM users WHERE role='customer') total"
)->fetch();
$kycRate = ($kycStats['total'] ?? 0) > 0 ? round($kycStats['uploaded'] / $kycStats['total'] * 100, 1) : 0;

$revenuePct = $volPct; // revenue simulation tracks volume

$periods = ['today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 days', 'last30' => 'Last 30 days',
            'this_month' => 'This month', 'last_month' => 'Last month', 'this_year' => 'This year', 'custom' => 'Custom range'];

function kpiCard(string $label, string $value, float $pct): string
{
    $cls  = $pct >= 0 ? 'pos' : 'neg';
    $sign = $pct >= 0 ? '&#9650;' : '&#9660;';
    return '<div class="stat-card kpi"><small>' . $label . '</small><strong>' . $value . '</strong>'
         . '<span class="kpi-delta ' . $cls . '">' . $sign . ' ' . abs($pct) . '%</span></div>';
}
?>
<h1 class="page-title">Advanced Analytics</h1>

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
    <button class="btn primary" type="submit">Apply</button>
  </form>
</div>

<div class="stat-grid five">
  <?= kpiCard('Users Growth', (string) (int) $curUsers . ' new', $usersPct) ?>
  <?= kpiCard('Transaction Growth', (string) (int) $curTxns, $txnsPct) ?>
  <?= kpiCard('Loan Approval Rate', $loanRate . '%', $loanRate - 50) ?>
  <?= kpiCard('KYC Completion Rate', $kycRate . '%', $kycRate - 50) ?>
  <?= kpiCard('Revenue Growth (sim)', money($curVol * 0.005), $revenuePct) ?>
</div>

<div class="chart-grid">
  <div class="card"><h3>User growth trend</h3><canvas id="chUserGrowth"></canvas></div>
  <div class="card"><h3>Registration trend</h3><canvas id="chRegistrations"></canvas></div>
  <div class="card"><h3>Transaction trend</h3><canvas id="chTxns"></canvas></div>
  <div class="card"><h3>Transfer volume trend</h3><canvas id="chVolume"></canvas></div>
  <div class="card"><h3>Login activity</h3><canvas id="chLogins"></canvas></div>
  <div class="card"><h3>Revenue simulation</h3><canvas id="chRevenue"></canvas></div>
  <div class="card"><h3>Loan analytics</h3><canvas id="chLoans"></canvas></div>
  <div class="card"><h3>KYC analytics</h3><canvas id="chKyc"></canvas></div>
  <div class="card"><h3>Card request analytics</h3><canvas id="chCards"></canvas></div>
  <div class="card"><h3>Geographic user distribution</h3><canvas id="chGeo"></canvas></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.FV_ANALYTICS_QS = 'period=<?= e($period) ?>&from=<?= e((string) $from) ?>&to=<?= e((string) $to) ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/charts-init.js"></script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
