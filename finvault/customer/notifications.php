<?php
declare(strict_types=1);
$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/customer_header.php';

$items = Notification::forUser(Session::userId(), 50);
Notification::markAllRead(Session::userId());
?>
<h1 class="page-title">Notification Center</h1>
<div class="card">
  <?php if (!$items): ?>
    <p class="muted">No notifications yet.</p>
  <?php else: ?>
    <ul class="notif-list">
      <?php foreach ($items as $n): ?>
        <li class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
          <span class="notif-type tag <?= e($n['type']) ?>"><?= e($n['type']) ?></span>
          <div>
            <strong><?= e($n['title']) ?></strong>
            <p><?= e($n['message']) ?></p>
            <small class="muted"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></small>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>
