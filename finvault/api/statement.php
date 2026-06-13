<?php
declare(strict_types=1);
/**
 * PDF generator - account summary, transaction statement, transfer
 * receipt and admin reports. Uses TCPDF when installed; otherwise
 * falls back to a printable HTML document.
 */
require_once dirname(__DIR__) . '/includes/config.php';
Session::init();
if (!Session::isLoggedIn()) { http_response_code(401); exit('Unauthorized'); }

$mode    = $_GET['mode'] ?? 'statement';
$isAdmin = Session::role() === 'admin';
$userId  = Session::userId();

if ($mode === 'admin_report' && !$isAdmin) { http_response_code(403); exit('Forbidden'); }
if ($mode !== 'admin_report' && $isAdmin)  { http_response_code(403); exit('Use the admin report mode.'); }

/* ---------- Build the report content ---------- */
$title = APP_NAME . ' - ';
$html  = '';

function tableStart(): string
{
    return '<table border="0.5" cellpadding="5" style="border-collapse:collapse;width:100%;font-size:9pt">';
}

if ($mode === 'receipt') {
    $ref  = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['ref'] ?? ''));
    $stmt = Database::get()->prepare(
        "SELECT * FROM transactions WHERE txn_ref = ? AND user_id = ? AND type = 'transfer_out' LIMIT 1"
    );
    $stmt->execute([$ref, $userId]);
    $t = $stmt->fetch();
    if (!$t) { http_response_code(404); exit('Receipt not found.'); }
    $user  = User::find($userId);
    $title .= 'Transfer Receipt ' . $ref;
    $html  = '<h2>Transfer Receipt</h2>' . tableStart()
        . '<tr><td><b>Reference</b></td><td>' . e($t['txn_ref']) . '</td></tr>'
        . '<tr><td><b>Date</b></td><td>' . date('d M Y, H:i', strtotime($t['created_at'])) . '</td></tr>'
        . '<tr><td><b>From</b></td><td>' . e($user['full_name']) . '</td></tr>'
        . '<tr><td><b>To</b></td><td>' . e($t['counterparty_name']) . ' (' . e($t['counterparty_account']) . ')</td></tr>'
        . '<tr><td><b>Amount</b></td><td>INR ' . number_format((float) $t['amount'], 2) . '</td></tr>'
        . '<tr><td><b>Note</b></td><td>' . e($t['description'] ?: '-') . '</td></tr>'
        . '<tr><td><b>Status</b></td><td>SUCCESS</td></tr></table>';

} elseif ($mode === 'summary') {
    $user    = User::find($userId);
    $account = Account::forUser($userId);
    $summary = Transaction::summary($userId);
    $title  .= 'Account Summary';
    $html    = '<h2>Account Summary</h2>' . tableStart()
        . '<tr><td><b>Account holder</b></td><td>' . e($user['full_name']) . '</td></tr>'
        . '<tr><td><b>Account number</b></td><td>' . e($account['account_number'] ?? '-') . '</td></tr>'
        . '<tr><td><b>Account type</b></td><td>' . e(ucfirst($account['account_type'] ?? '-')) . '</td></tr>'
        . '<tr><td><b>Status</b></td><td>' . e(ucfirst($account['status'] ?? '-')) . '</td></tr>'
        . '<tr><td><b>Available balance</b></td><td>INR ' . number_format((float) ($account['balance'] ?? 0), 2) . '</td></tr>'
        . '<tr><td><b>Total credits</b></td><td>INR ' . number_format((float) $summary['total_in'], 2) . '</td></tr>'
        . '<tr><td><b>Total debits</b></td><td>INR ' . number_format((float) $summary['total_out'], 2) . '</td></tr>'
        . '<tr><td><b>Generated on</b></td><td>' . date('d M Y, H:i') . '</td></tr></table>';

} elseif ($mode === 'admin_report') {
    $rows  = Transaction::adminList(trim($_GET['q'] ?? ''), $_GET['period'] ?? 'last30', isset($_GET['large']), 500);
    $title .= 'Transaction Report';
    $html  = '<h2>Transaction Report</h2><p>Period: ' . e($_GET['period'] ?? 'last30') . ' | Generated: ' . date('d M Y H:i') . '</p>'
        . tableStart() . '<tr><th>Date</th><th>Reference</th><th>Customer</th><th>Type</th><th align="right">Amount</th></tr>';
    foreach ($rows as $t) {
        $html .= '<tr><td>' . date('d M y H:i', strtotime($t['created_at'])) . '</td><td>' . e($t['txn_ref'])
            . '</td><td>' . e($t['full_name']) . '</td><td>' . e($t['type'])
            . '</td><td align="right">' . number_format((float) $t['amount'], 2) . '</td></tr>';
    }
    $html .= '</table>';

} else { // statement
    $user    = User::find($userId);
    $account = Account::forUser($userId);
    $period  = $_GET['period'] ?? 'last30';
    [$start, $end] = Transaction::dateRange($period, $_GET['from'] ?? null, $_GET['to'] ?? null);
    $rows = array_reverse(Transaction::history($userId, $period, $_GET['from'] ?? null, $_GET['to'] ?? null, trim($_GET['q'] ?? ''), 500));

    $opening = $rows ? (float) $rows[0]['balance_after']
        + (in_array($rows[0]['type'], ['deposit', 'transfer_in']) ? -1 : 1) * (float) $rows[0]['amount']
        : (float) ($account['balance'] ?? 0);
    $closing = $rows ? (float) end($rows)['balance_after'] : $opening;

    $title .= 'Account Statement';
    $html   = '<h2>Account Statement</h2>'
        . '<p><b>' . e($user['full_name']) . '</b> | A/C ' . e($account['account_number'] ?? '-')
        . ' (' . e(ucfirst($account['account_type'] ?? '')) . ')<br>'
        . 'Period: ' . substr($start, 0, 10) . ' to ' . substr($end, 0, 10)
        . ' | Opening balance: INR ' . number_format($opening, 2)
        . ' | Closing balance: INR ' . number_format($closing, 2) . '</p>'
        . tableStart()
        . '<tr><th>Date</th><th>Reference</th><th>Description</th><th align="right">Debit</th><th align="right">Credit</th><th align="right">Balance</th></tr>';
    foreach ($rows as $t) {
        $in = in_array($t['type'], ['deposit', 'transfer_in']);
        $html .= '<tr><td>' . date('d M y', strtotime($t['created_at'])) . '</td><td>' . e($t['txn_ref'])
            . '</td><td>' . e(($t['counterparty_name'] ? $t['counterparty_name'] . ' - ' : '') . ($t['description'] ?: str_replace('_', ' ', $t['type'])))
            . '</td><td align="right">' . ($in ? '' : number_format((float) $t['amount'], 2))
            . '</td><td align="right">' . ($in ? number_format((float) $t['amount'], 2) : '')
            . '</td><td align="right">' . number_format((float) $t['balance_after'], 2) . '</td></tr>';
    }
    $html .= '</table>';
}

/* ---------- Render with TCPDF when available, HTML otherwise ---------- */
$tcpdfLoaded = class_exists('TCPDF');
if (!$tcpdfLoaded) {
    foreach ([ROOT_PATH . '/vendor/autoload.php', ROOT_PATH . '/assets/vendors/tcpdf/tcpdf.php'] as $f) {
        if (is_file($f)) { require_once $f; }
    }
    $tcpdfLoaded = class_exists('TCPDF');
}

$branding = '<table width="100%"><tr>'
    . '<td><h1 style="color:#1e3a8a">' . APP_NAME . '</h1><p>' . APP_TAGLINE . '</p></td>'
    . '<td align="right"><small>Educational banking simulation<br>No real money involved</small></td>'
    . '</tr></table><hr>';

if ($tcpdfLoaded) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator(APP_NAME);
    $pdf->SetTitle($title);
    $pdf->SetMargins(12, 12, 12);
    $pdf->setPrintHeader(false);
    $pdf->AddPage();
    $pdf->writeHTML($branding . $html, true, false, true, false, '');
    $pdf->Output(preg_replace('/[^A-Za-z0-9_-]/', '_', $title) . '.pdf', 'I');
    exit;
}

// HTML fallback (printable, user can "Save as PDF" from the browser)
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>'
   . '<style>body{font-family:Segoe UI,Arial,sans-serif;max-width:800px;margin:24px auto;color:#1e293b}'
   . 'table{border-collapse:collapse;width:100%}td,th{border:1px solid #cbd5e1;padding:6px;font-size:13px}'
   . '@media print{.no-print{display:none}}</style></head><body>'
   . '<p class="no-print"><i>TCPDF is not installed - showing printable HTML. Use your browser\'s "Print &gt; Save as PDF".</i> '
   . '<button onclick="window.print()">Print</button></p>'
   . $branding . $html . '</body></html>';
