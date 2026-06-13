<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('customer');
$userId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $type = $_POST['doc_type'] ?? '';
    if (isset($_FILES['document'])) {
        $res = KYC::upload($userId, $type, $_FILES['document']);
        Session::flash($res['success'] ? 'success' : 'error',
            $res['success'] ? ucfirst($type) . ' uploaded and pending review.' : $res['error']);
    }
    redirect('/customer/kyc.php');
}

$pageTitle = 'KYC';
require_once __DIR__ . '/includes/customer_header.php';
$docs    = KYC::forUser($userId);
$overall = KYC::overallStatus($userId);
?>
<h1 class="page-title">KYC Verification</h1>

<div class="card">
  <p>Overall status:
    <span class="tag <?= e($overall) ?>"><?= e(str_replace('_', ' ', ucfirst($overall))) ?></span>
  </p>
  <p class="muted">Upload clear copies of your documents (JPG, PNG, WEBP or PDF, max 3 MB each).</p>
</div>

<div class="stat-grid three">
  <?php foreach (KYC::DOC_TYPES as $key => $label):
      $doc = $docs[$key] ?? null; ?>
    <div class="card kyc-card">
      <h3><?= e($label) ?></h3>
      <?php if ($doc): ?>
        <p><span class="tag <?= e($doc['status']) ?>"><?= e(ucfirst($doc['status'])) ?></span></p>
        <?php if ($doc['admin_remarks']): ?><p class="muted"><small>Remarks: <?= e($doc['admin_remarks']) ?></small></p><?php endif; ?>
        <p class="muted"><small>Uploaded <?= date('d M Y', strtotime($doc['uploaded_at'])) ?></small></p>
      <?php else: ?>
        <p class="muted">Not uploaded yet.</p>
      <?php endif; ?>
      <?php if (!$doc || in_array($doc['status'], ['rejected', 'reupload'], true)): ?>
        <form method="post" enctype="multipart/form-data">
          <?= Session::csrfField() ?>
          <input type="hidden" name="doc_type" value="<?= e($key) ?>">
          <label>Choose file<input type="file" name="document" accept="image/*,.pdf" required></label>
          <button class="btn primary sm" type="submit"><?= $doc ? 'Re-upload' : 'Upload' ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
