<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
Session::require('customer');
$userId = Session::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCsrfOrFail();
    switch ($_POST['action'] ?? '') {
        case 'request':
            $res = Card::request($userId, $_POST['card_type'] ?? '');
            Session::flash($res['success'] ? 'success' : 'error',
                $res['success'] ? 'Card request submitted for approval.' : $res['error']);
            break;
        case 'block':
        case 'unblock':
            $res = Card::toggleBlock($userId, (int) $_POST['card_id'], $_POST['action']);
            Session::flash($res['success'] ? 'success' : 'error',
                $res['success'] ? 'Card status updated.' : $res['error']);
            break;
    }
    redirect('/customer/cards.php');
}

$pageTitle = 'Cards';
require_once __DIR__ . '/includes/customer_header.php';
$cards = Card::forUser($userId);
?>
<h1 class="page-title">Card Management</h1>

<div class="grid-2-cards">
  <div class="card">
    <h3>Request a new card</h3>
    <form method="post" class="row gap wrap">
      <?= Session::csrfField() ?>
      <input type="hidden" name="action" value="request">
      <label class="radio-card"><input type="radio" name="card_type" value="debit" checked>
        <span><strong>Debit card</strong><small>Linked to your savings/current account</small></span></label>
      <label class="radio-card"><input type="radio" name="card_type" value="credit">
        <span><strong>Credit card</strong><small>Subject to admin approval</small></span></label>
      <button class="btn primary" type="submit">Request card</button>
    </form>
  </div>

  <div class="card">
    <h3>My cards</h3>
    <?php if (!$cards): ?><p class="muted">No cards yet. Request one to get started.</p><?php endif; ?>
    <div class="card-stack">
    <?php foreach ($cards as $c): ?>
      <div class="bank-card <?= e($c['card_type']) ?> <?= e($c['status']) ?>">
        <div class="bank-card-top"><span><?= APP_NAME ?></span><span class="tag <?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></div>
        <div class="bank-card-number mono">
          <?= $c['card_number'] ? chunk_split('XXXXXXXXXXXX' . substr($c['card_number'], -4), 4, ' ') : 'XXXX XXXX XXXX XXXX' ?>
        </div>
        <div class="bank-card-bottom">
          <span><?= e(strtoupper($currentUser['full_name'])) ?></span>
          <span><?= e(strtoupper($c['card_type'])) ?></span>
        </div>
        <div class="row gap mt-1">
          <?php if ($c['status'] === 'active'): ?>
            <form method="post"><?= Session::csrfField() ?>
              <input type="hidden" name="action" value="block"><input type="hidden" name="card_id" value="<?= (int) $c['id'] ?>">
              <button class="btn danger sm" type="submit">Block card</button></form>
          <?php elseif ($c['status'] === 'blocked'): ?>
            <form method="post"><?= Session::csrfField() ?>
              <input type="hidden" name="action" value="unblock"><input type="hidden" name="card_id" value="<?= (int) $c['id'] ?>">
              <button class="btn primary sm" type="submit">Unblock card</button></form>
          <?php elseif ($c['status'] === 'requested'): ?>
            <small class="muted">Awaiting admin approval</small>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
