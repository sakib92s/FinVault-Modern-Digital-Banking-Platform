<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('admin');
$adminId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    $res = KYC::review((int) $_POST['doc_id'], $_POST['decision'] ?? '', trim($_POST['remarks'] ?? ''), $adminId);
    Session::flash($res['success'] ? 'success' : 'error', $res['success'] ? 'KYC document reviewed.' : $res['error']);
    redirect('/admin/kyc.php');
}

$pageTitle = 'KYC Review';
require_once __DIR__ . '/includes/admin_header.php';
$pending = KYC::adminPending();
?>
<h1 class="page-title">KYC Review Queue</h1>

<div class="card">
  <table class="table">
    <thead><tr><th>Customer</th><th>Document</th><th>File</th><th>Uploaded</th><th class="right">Decision</th></tr></thead>
    <tbody>
    <?php if (!$pending): ?><tr><td colspan="5" class="muted">&#127881; Queue is empty - no pending KYC documents.</td></tr><?php endif; ?>
    <?php foreach ($pending as $doc): ?>
      <tr>
        <td><?= e($doc['full_name']) ?><br><small class="muted"><?= e($doc['email']) ?></small></td>
        <td><span class="tag"><?= e(KYC::DOC_TYPES[$doc['doc_type']] ?? $doc['doc_type']) ?></span><br>
            <small class="muted">Status: <?= e($doc['status']) ?></small></td>
        <td><a class="btn ghost sm" href="<?= BASE_URL ?>/uploads/<?= e($doc['file_path']) ?>" target="_blank">View document</a></td>
        <td><?= date('d M Y, H:i', strtotime($doc['uploaded_at'])) ?></td>
        <td class="right">
          <form method="post" class="stack-form"><?= Session::csrfField() ?>
            <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
            <input type="text" name="remarks" placeholder="Remarks (optional)">
            <div class="row gap">
              <button class="btn primary sm" name="decision" value="approve">Approve</button>
              <button class="btn ghost sm" name="decision" value="reupload">Request re-upload</button>
              <button class="btn danger sm" name="decision" value="reject">Reject</button>
            </div>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
