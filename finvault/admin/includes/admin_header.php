<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/config.php';
Session::init();
Session::require('admin');

$currentAdmin = User::find(Session::userId());
$flashes      = Session::pullFlashes();
$activePage   = basename($_SERVER['SCRIPT_NAME']);
$pageTitle    = $pageTitle ?? 'Admin';

function adminNav(string $file, string $icon, string $label, string $active): string
{
    $cls = $file === $active ? 'nav-link active' : 'nav-link';
    return '<a class="' . $cls . '" href="' . BASE_URL . '/admin/' . $file . '">'
         . '<span class="nav-icon">' . $icon . '</span><span>' . $label . '</span></a>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= APP_NAME ?> Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<script>window.FV = { base: '<?= BASE_URL ?>', csrf: '<?= Session::csrfToken() ?>', admin: true };</script>
</head>
<body class="admin">
<div class="app-shell">
  <aside class="sidebar admin-sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo">FV</div>
      <div><strong><?= APP_NAME ?></strong><small>Admin Portal</small></div>
    </div>
    <nav class="nav">
      <?= adminNav('dashboard.php', '&#8962;', 'Dashboard', $activePage) ?>
      <?= adminNav('analytics.php', '&#128200;', 'Analytics', $activePage) ?>
      <?= adminNav('users.php', '&#128101;', 'Users', $activePage) ?>
      <?= adminNav('accounts.php', '&#127974;', 'Accounts', $activePage) ?>
      <?= adminNav('transactions.php', '&#128196;', 'Transactions', $activePage) ?>
      <?= adminNav('loans.php', '&#128176;', 'Loans', $activePage) ?>
      <?= adminNav('cards.php', '&#128179;', 'Cards', $activePage) ?>
      <?= adminNav('kyc.php', '&#10004;', 'KYC Review', $activePage) ?>
    </nav>
    <a class="nav-link logout" href="<?= BASE_URL ?>/admin/logout.php"><span class="nav-icon">&#9211;</span><span>Sign out</span></a>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="sidebarToggle" aria-label="Menu">&#9776;</button>
      <div class="global-search">
        <input type="text" id="globalSearch" placeholder="Search users, accounts, transactions, loans, cards..." autocomplete="off">
        <div class="search-results" id="globalSearchResults"></div>
      </div>
      <div class="topbar-actions">
        <button class="icon-btn" id="themeToggle" title="Toggle dark mode">&#127769;</button>
        <div class="avatar admin-avatar" title="<?= e($currentAdmin['full_name']) ?>">
          <?= strtoupper(substr($currentAdmin['full_name'], 0, 1)) ?>
        </div>
      </div>
    </header>
    <main class="content">
      <?php foreach ($flashes as $type => $msg): ?>
        <div class="toast-seed" data-type="<?= e($type) ?>" data-msg="<?= e($msg) ?>"></div>
      <?php endforeach; ?>
