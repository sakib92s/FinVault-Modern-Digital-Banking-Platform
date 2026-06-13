<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/config.php';
Session::init();
Session::require('customer');

$currentUser  = User::find(Session::userId());
$unreadCount  = Notification::unreadCount(Session::userId());
$flashes      = Session::pullFlashes();
$activePage   = basename($_SERVER['SCRIPT_NAME']);
$pageTitle    = $pageTitle ?? 'FinVault';

function navItem(string $file, string $icon, string $label, string $active): string
{
    $cls = $file === $active ? 'nav-link active' : 'nav-link';
    return '<a class="' . $cls . '" href="' . BASE_URL . '/customer/' . $file . '">'
         . '<span class="nav-icon">' . $icon . '</span><span>' . $label . '</span></a>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>window.FV = { base: '<?= BASE_URL ?>', csrf: '<?= Session::csrfToken() ?>' };</script>
</head>
<body>
<div class="app-shell">
  <!-- ===== Sidebar ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo">FV</div>
      <div><strong><?= APP_NAME ?></strong><small><?= APP_TAGLINE ?></small></div>
    </div>
    <nav class="nav">
      <?= navItem('dashboard.php', '&#8962;', 'Dashboard', $activePage) ?>
      <?= navItem('accounts.php', '&#127974;', 'My Account', $activePage) ?>
      <?= navItem('transfer.php', '&#8644;', 'Fund Transfer', $activePage) ?>
      <?= navItem('beneficiaries.php', '&#128101;', 'Beneficiaries', $activePage) ?>
      <?= navItem('transactions.php', '&#128196;', 'Transactions', $activePage) ?>
      <?= navItem('loans.php', '&#128176;', 'Loans', $activePage) ?>
      <?= navItem('cards.php', '&#128179;', 'Cards', $activePage) ?>
      <?= navItem('kyc.php', '&#10004;', 'KYC', $activePage) ?>
      <?= navItem('profile.php', '&#9881;', 'Profile', $activePage) ?>
    </nav>
    <a class="nav-link logout" href="<?= BASE_URL ?>/customer/logout.php"><span class="nav-icon">&#9211;</span><span>Sign out</span></a>
  </aside>

  <!-- ===== Main ===== -->
  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="sidebarToggle" aria-label="Menu">&#9776;</button>
      <div class="global-search">
        <input type="text" id="globalSearch" placeholder="Search transactions, beneficiaries, loans, cards..." autocomplete="off">
        <div class="search-results" id="globalSearchResults"></div>
      </div>
      <div class="topbar-actions">
        <button class="icon-btn" id="themeToggle" title="Toggle dark mode">&#127769;</button>
        <a class="icon-btn bell" href="<?= BASE_URL ?>/customer/notifications.php" title="Notifications">&#128276;
          <?php if ($unreadCount > 0): ?><span class="badge-dot"><?= $unreadCount ?></span><?php endif; ?>
        </a>
        <div class="avatar" title="<?= e($currentUser['full_name']) ?>">
          <?php if (!empty($currentUser['profile_photo'])): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= e($currentUser['profile_photo']) ?>" alt="">
          <?php else: ?><?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?><?php endif; ?>
        </div>
      </div>
    </header>
    <main class="content">
      <?php foreach ($flashes as $type => $msg): ?>
        <div class="toast-seed" data-type="<?= e($type) ?>" data-msg="<?= e($msg) ?>"></div>
      <?php endforeach; ?>
