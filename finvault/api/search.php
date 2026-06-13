<?php
declare(strict_types=1);
/**
 * Smart global search - AJAX live suggestions across modules.
 * Role aware: customers search their own data, admins search everything.
 */
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
if (!Session::isLoggedIn()) json_response(['error' => 'Unauthorized'], 401);

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) json_response(['results' => []]);

$db      = Database::get();
$userId  = Session::userId();
$isAdmin = Session::role() === 'admin';
$like    = '%' . $q . '%';
$results = [];

function push(array &$results, string $category, string $label, string $sub, string $url): void
{
    $results[] = compact('category', 'label', 'sub', 'url');
}

if ($isAdmin) {
    foreach (User::search($q, 5) as $u) {
        push($results, 'Users', $u['full_name'], $u['email'] . ' · ' . $u['mobile'],
             BASE_URL . '/admin/users.php?view=' . $u['id']);
    }
    foreach (Account::search($q, 5) as $a) {
        push($results, 'Accounts', $a['account_number'], $a['full_name'] . ' · ' . $a['account_type'],
             BASE_URL . '/admin/accounts.php?q=' . urlencode($a['account_number']));
    }
    foreach (Transaction::search($q, null, 5) as $t) {
        push($results, 'Transactions', $t['txn_ref'], money((float) $t['amount']) . ' · ' . $t['created_at'],
             BASE_URL . '/admin/transactions.php?q=' . urlencode($t['txn_ref']) . '&period=all');
    }
    $stmt = $db->prepare('SELECT l.loan_ref, l.status, u.full_name FROM loans l JOIN users u ON u.id = l.user_id
                          WHERE l.loan_ref LIKE ? OR u.full_name LIKE ? LIMIT 5');
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll() as $l) {
        push($results, 'Loans', $l['loan_ref'], $l['full_name'] . ' · ' . $l['status'],
             BASE_URL . '/admin/loans.php?q=' . urlencode($l['loan_ref']));
    }
    $stmt = $db->prepare('SELECT c.id, c.card_type, c.status, u.full_name FROM cards c JOIN users u ON u.id = c.user_id
                          WHERE u.full_name LIKE ? LIMIT 5');
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll() as $c) {
        push($results, 'Cards', ucfirst($c['card_type']) . ' card', $c['full_name'] . ' · ' . $c['status'],
             BASE_URL . '/admin/cards.php?status=' . $c['status']);
    }
} else {
    foreach (Transaction::search($q, $userId, 5) as $t) {
        push($results, 'Transactions', $t['txn_ref'], money((float) $t['amount']) . ' · ' . $t['created_at'],
             BASE_URL . '/customer/transactions.php?q=' . urlencode($t['txn_ref']) . '&period=all');
    }
    $stmt = $db->prepare('SELECT id, name, account_number FROM beneficiaries WHERE user_id = ?
                          AND (name LIKE ? OR account_number LIKE ? OR email LIKE ? OR mobile LIKE ?) LIMIT 5');
    $stmt->execute([$userId, $like, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $b) {
        push($results, 'Beneficiaries', $b['name'], $b['account_number'],
             BASE_URL . '/customer/beneficiaries.php?edit=' . $b['id']);
    }
    $stmt = $db->prepare('SELECT loan_ref, loan_type, status FROM loans WHERE user_id = ? AND (loan_ref LIKE ? OR loan_type LIKE ?) LIMIT 5');
    $stmt->execute([$userId, $like, $like]);
    foreach ($stmt->fetchAll() as $l) {
        push($results, 'Loans', $l['loan_ref'], $l['loan_type'] . ' · ' . $l['status'], BASE_URL . '/customer/loans.php');
    }
    $stmt = $db->prepare('SELECT card_type, status FROM cards WHERE user_id = ? AND card_type LIKE ? LIMIT 3');
    $stmt->execute([$userId, $like]);
    foreach ($stmt->fetchAll() as $c) {
        push($results, 'Cards', ucfirst($c['card_type']) . ' card', $c['status'], BASE_URL . '/customer/cards.php');
    }
    $stmt = $db->prepare('SELECT title, message FROM notifications WHERE user_id = ? AND (title LIKE ? OR message LIKE ?) ORDER BY created_at DESC LIMIT 3');
    $stmt->execute([$userId, $like, $like]);
    foreach ($stmt->fetchAll() as $n) {
        push($results, 'Notifications', $n['title'], mb_substr($n['message'], 0, 60), BASE_URL . '/customer/notifications.php');
    }
}

json_response(['results' => $results]);
