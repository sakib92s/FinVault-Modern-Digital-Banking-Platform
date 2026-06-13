<?php
declare(strict_types=1);
/**
 * Beneficiary autocomplete - intelligent suggestions while typing a
 * name, account number, email or mobile (saved payees + bank customers).
 */
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
if (!Session::isLoggedIn() || Session::role() !== 'customer') {
    json_response(['error' => 'Unauthorized'], 401);
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) json_response(['suggestions' => []]);

json_response(['suggestions' => Beneficiary::autocomplete(Session::userId(), $q)]);
