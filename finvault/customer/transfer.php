<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('customer');
$userId = Session::userId();

$step    = 'form';
$review  = null;
$result  = null;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $action = $_POST['action'] ?? '';

    if ($action === 'review') {
        $accNo  = trim($_POST['account_number'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $note   = trim($_POST['note'] ?? '');
        $target = Account::byNumber($accNo);
        $mine   = Account::forUser($userId);

        if (!$target)                          $error = 'Beneficiary account not found.';
        elseif ($amount <= 0)                  $error = 'Enter a valid amount.';
        elseif (!$mine || (float) $mine['balance'] < $amount) $error = 'Insufficient balance.';
        else { $step = 'review'; $review = ['target' => $target, 'amount' => $amount, 'note' => $note]; }
    }

    if ($action === 'confirm') {
        $res = Transaction::transfer(
            $userId,
            trim($_POST['account_number'] ?? ''),
            (float) ($_POST['amount'] ?? 0),
            trim($_POST['note'] ?? ''),
            ($_POST['channel'] ?? 'internal') === 'qr' ? 'qr' : 'internal'
        );
        if ($res['success']) { $step = 'success'; $result = $res; }
        else $error = $res['error'];
    }
}

$pageTitle = 'Fund Transfer';
require_once __DIR__ . '/includes/customer_header.php';
$account = Account::forUser($userId);
$bens    = Beneficiary::forUser($userId);
$tab     = $_GET['tab'] ?? 'transfer';
?>
<h1 class="page-title">Fund Transfer</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<?php if ($step === 'success' && $result): ?>
  <div class="card success-screen">
    <div class="success-icon">&#10003;</div>
    <h2>Transfer successful!</h2>
    <p><strong><?= money((float) $result['amount']) ?></strong> sent to <strong><?= e($result['beneficiary']) ?></strong> (<?= e($result['account']) ?>)</p>
    <p class="mono">Reference: <?= e($result['reference']) ?></p>
    <p class="muted">Updated balance: <?= money((float) $result['balance']) ?></p>
    <div class="row gap">
      <a class="btn primary" href="<?= BASE_URL ?>/api/statement.php?mode=receipt&ref=<?= e($result['reference']) ?>" target="_blank">Download receipt</a>
      <a class="btn ghost" href="transfer.php">New transfer</a>
      <a class="btn ghost" href="dashboard.php">Dashboard</a>
    </div>
  </div>

<?php elseif ($step === 'review' && $review): ?>
  <div class="card review-card">
    <h3>Review transfer</h3>
    <dl class="detail-list">
      <dt>To</dt><dd><?= e($review['target']['full_name']) ?></dd>
      <dt>Account</dt><dd class="mono"><?= e($review['target']['account_number']) ?></dd>
      <dt>Amount</dt><dd><strong><?= money($review['amount']) ?></strong></dd>
      <dt>Note</dt><dd><?= e($review['note'] ?: '-') ?></dd>
    </dl>
    <form method="post" class="row gap">
      <?= Session::csrfField() ?>
      <input type="hidden" name="action" value="confirm">
      <input type="hidden" name="account_number" value="<?= e($review['target']['account_number']) ?>">
      <input type="hidden" name="amount" value="<?= e((string) $review['amount']) ?>">
      <input type="hidden" name="note" value="<?= e($review['note']) ?>">
      <button class="btn primary" type="submit">Confirm &amp; send</button>
      <a class="btn ghost" href="transfer.php">Cancel</a>
    </form>
  </div>

<?php else: ?>
  <div class="tabs">
    <a class="tab <?= $tab === 'transfer' ? 'active' : '' ?>" href="?tab=transfer">Bank transfer</a>
    <a class="tab <?= $tab === 'qr' ? 'active' : '' ?>" href="?tab=qr">QR pay</a>
  </div>

  <div class="grid-2-cards">
    <div class="card">
      <h3><?= $tab === 'qr' ? 'Pay via QR' : 'Send money' ?></h3>
      <p class="muted">Available balance: <strong><?= money((float) ($account['balance'] ?? 0)) ?></strong></p>
      <form method="post">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="review">
        <?php if ($tab === 'qr'): ?>
          <input type="hidden" name="channel" value="qr">
          <label>Paste scanned QR data
            <textarea id="qrPayload" rows="2" placeholder='{"account":"100012345678","name":"...","amount":500}'></textarea>
          </label>
          <button type="button" class="btn ghost sm" id="qrFill">Fill from QR data</button>
        <?php endif; ?>
        <label>Beneficiary (search name, account, email or mobile)
          <div class="autocomplete-wrap">
            <input type="text" id="benSearch" placeholder="Type 'Rah' or '9876'..." autocomplete="off">
            <div class="search-results" id="benResults"></div>
          </div>
        </label>
        <label>Account number<input type="text" name="account_number" id="benAccount" required pattern="[0-9]{12}"></label>
        <label>Amount (₹)<input type="number" name="amount" id="benAmount" min="1" step="0.01" required></label>
        <label>Note (optional)<input type="text" name="note" maxlength="100"></label>
        <button class="btn primary block" type="submit">Review transfer</button>
      </form>
    </div>

    <div class="side-stack">
      <div class="card">
        <h3>My beneficiaries</h3>
        <ul class="ben-list">
          <?php if (!$bens): ?><li class="muted">No beneficiaries yet. <a href="beneficiaries.php">Add one</a></li><?php endif; ?>
          <?php foreach ($bens as $b): ?>
            <li><button class="ben-pick" data-account="<?= e($b['account_number']) ?>" data-name="<?= e($b['name']) ?>">
              <strong><?= e($b['name']) ?></strong><span class="mono"><?= e($b['account_number']) ?></span>
            </button></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="card">
        <h3>Receive via QR</h3>
        <p class="muted">Share this QR to receive money into your account.</p>
        <div id="myQr" class="qr-box"
             data-account="<?= e($account['account_number'] ?? '') ?>"
             data-name="<?= e($currentUser['full_name']) ?>"></div>
        <label>Request amount (optional)<input type="number" id="qrAmount" min="0" step="0.01" placeholder="0.00"></label>
      </div>
    </div>
  </div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/qrcode.js"></script>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
