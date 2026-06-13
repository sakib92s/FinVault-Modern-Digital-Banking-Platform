<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('customer');
$userId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $res = Loan::apply($userId, $_POST['loan_type'] ?? '', (float) ($_POST['amount'] ?? 0),
                       (int) ($_POST['tenure'] ?? 0), $_POST['purpose'] ?? '');
    Session::flash($res['success'] ? 'success' : 'error',
        $res['success'] ? 'Loan application submitted. Ref: ' . $res['reference'] : $res['error']);
    redirect('/customer/loans.php');
}

$pageTitle = 'Loans';
require_once __DIR__ . '/includes/customer_header.php';
$loans = Loan::forUser($userId);
?>
<h1 class="page-title">Loan Management</h1>

<div class="grid-2-cards">
  <div class="card">
    <h3>Apply for a loan</h3>
    <form method="post" id="loanForm">
      <?= Session::csrfField() ?>
      <label>Loan type
        <select name="loan_type" id="loanType" required>
          <option value="personal" data-rate="11.5">Personal loan · 11.5% p.a.</option>
          <option value="education" data-rate="8.5">Education loan · 8.5% p.a.</option>
          <option value="business" data-rate="13">Business loan · 13% p.a.</option>
        </select>
      </label>
      <label>Amount (₹)<input type="number" name="amount" id="loanAmount" min="10000" max="5000000" step="1000" value="100000" required></label>
      <label>Tenure (months)<input type="number" name="tenure" id="loanTenure" min="6" max="240" value="24" required></label>
      <label>Purpose<input type="text" name="purpose" maxlength="200" required></label>

      <div class="emi-box">
        <small>Estimated EMI</small>
        <strong id="emiValue">₹ -</strong>
        <small class="muted" id="emiTotal"></small>
      </div>
      <button class="btn primary block" type="submit">Submit application</button>
    </form>
  </div>

  <div class="card">
    <h3>My loan applications</h3>
    <table class="table">
      <thead><tr><th>Ref</th><th>Type</th><th class="right">Amount</th><th class="right">EMI</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$loans): ?><tr><td colspan="5" class="muted">No loan applications yet.</td></tr><?php endif; ?>
      <?php foreach ($loans as $l): ?>
        <tr>
          <td class="mono"><?= e($l['loan_ref']) ?><br><small class="muted"><?= date('d M Y', strtotime($l['created_at'])) ?></small></td>
          <td><?= e(ucfirst($l['loan_type'])) ?><br><small class="muted"><?= (int) $l['tenure_months'] ?> mo · <?= e($l['interest_rate']) ?>%</small></td>
          <td class="right"><?= money((float) $l['amount']) ?></td>
          <td class="right"><?= money((float) $l['emi']) ?></td>
          <td><span class="tag <?= e($l['status']) ?>"><?= e(ucfirst($l['status'])) ?></span>
              <?php if ($l['admin_remarks']): ?><br><small class="muted"><?= e($l['admin_remarks']) ?></small><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Live EMI calculator: P*r*(1+r)^n / ((1+r)^n - 1)
(function () {
  const type = document.getElementById('loanType'),
        amt  = document.getElementById('loanAmount'),
        ten  = document.getElementById('loanTenure'),
        out  = document.getElementById('emiValue'),
        tot  = document.getElementById('emiTotal');
  function calc() {
    const P = parseFloat(amt.value) || 0,
          n = parseInt(ten.value) || 0,
          rate = parseFloat(type.selectedOptions[0].dataset.rate),
          r = rate / 12 / 100;
    if (P <= 0 || n <= 0) { out.textContent = '\u20b9 -'; tot.textContent = ''; return; }
    const pow = Math.pow(1 + r, n),
          emi = P * r * pow / (pow - 1);
    out.textContent = '\u20b9 ' + emi.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    tot.textContent = 'Total payable: \u20b9 ' + (emi * n).toLocaleString('en-IN', { maximumFractionDigits: 0 });
  }
  [type, amt, ten].forEach(el => el.addEventListener('input', calc));
  calc();
})();
</script>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
