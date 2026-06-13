<?php
declare(strict_types=1);
/**
 * AJAX transfer endpoint (JSON in / JSON out) - used for QR pay and
 * programmatic transfers from the frontend.
 */
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
if (!Session::isLoggedIn() || Session::role() !== 'customer') {
    json_response(['error' => 'Unauthorized'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
if (!Session::verifyCsrf($input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    json_response(['error' => 'Invalid CSRF token'], 419);
}

$res = Transaction::transfer(
    Session::userId(),
    trim((string) ($input['account_number'] ?? '')),
    (float) ($input['amount'] ?? 0),
    trim((string) ($input['note'] ?? '')),
    ($input['channel'] ?? 'internal') === 'qr' ? 'qr' : 'internal'
);

json_response($res, $res['success'] ? 200 : 422);
