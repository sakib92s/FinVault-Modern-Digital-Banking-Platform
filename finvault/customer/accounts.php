<?php
declare(strict_types=1);
$pageTitle = 'My Account';
require_once __DIR__ . '/includes/customer_header.php';

$userId  = Session::userId();
$account = Account::forUser($userId);
$kyc     = KYC::overallStatus($userId);
?>
<h1 class="page-title">Account Details</h1>

<div class="grid-2-cards">
  <div class="card">
    <h3>Account information</h3>
    <dl class="detail-list">
      <dt>Account holder</dt><dd><?= e($currentUser['full_name']) ?></dd>
      <dt>Account number</dt><dd class="mono"><?= e($account['account_number'] ?? '-') ?></dd>
      <dt>Account type</dt><dd><?= e(ucfirst($account['account_type'] ?? '-')) ?></dd>
      <dt>Status</dt><dd><span class="tag <?= e($account['status'] ?? '') ?>"><?= e(ucfirst($account['status'] ?? '-')) ?></span></dd>
      <dt>Available balance</dt><dd><strong><?= money((float) ($account['balance'] ?? 0)) ?></strong></dd>
      <dt>Opened on</dt><dd><?= $account ? date('d M Y', strtotime($account['created_at'])) : '-' ?></dd>
      <dt>KYC status</dt><dd><span class="tag <?= e($kyc) ?>"><?= e(str_replace('_', ' ', ucfirst($kyc))) ?></span></dd>
    </dl>
    <a class="btn primary" href="<?= BASE_URL ?>/api/statement.php?mode=summary" target="_blank">Download account summary (PDF)</a>
  </div>

  <div class="card">
    <h3>Personal information</h3>
    <dl class="detail-list">
      <dt>Email</dt><dd><?= e($currentUser['email']) ?></dd>
      <dt>Mobile</dt><dd><?= e($currentUser['mobile']) ?></dd>
      <dt>Date of birth</dt><dd><?= $currentUser['dob'] ? date('d M Y', strtotime($currentUser['dob'])) : '-' ?></dd>
      <dt>Gender</dt><dd><?= e(ucfirst((string) $currentUser['gender'])) ?></dd>
      <dt>PAN</dt><dd class="mono"><?= e($currentUser['pan_number'] ?: '-') ?></dd>
      <dt>Aadhaar</dt><dd class="mono"><?= $currentUser['aadhaar_number'] ? 'XXXX XXXX ' . substr($currentUser['aadhaar_number'], -4) : '-' ?></dd>
      <dt>Address</dt><dd><?= e($currentUser['address'] ?: '-') ?></dd>
    </dl>
    <a class="btn ghost" href="profile.php">Edit profile</a>
  </div>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
