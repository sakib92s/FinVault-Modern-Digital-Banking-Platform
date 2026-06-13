<?php
declare(strict_types=1);

/**
 * FinVault - global configuration & bootstrap.
 * Educational banking simulation. No real money moves anywhere.
 */

date_default_timezone_set('Asia/Kolkata');

/* ---------- Database ---------- */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'finvault';
const DB_USER = 'root';
const DB_PASS = '';

/* ---------- Application ---------- */
const APP_NAME    = 'FinVault';
const APP_TAGLINE = 'Modern Digital Banking Platform';
const BASE_URL    = 'http://localhost/finvault'; // no trailing slash

const SESSION_TIMEOUT          = 900;   // seconds (15 min)
const MAX_LOGIN_ATTEMPTS       = 5;
const LOCKOUT_MINUTES          = 15;
const OTP_EXPIRY_MINUTES       = 5;
const LARGE_TRANSFER_THRESHOLD = 100000; // flagged in admin monitoring
const MIN_OPENING_BALANCE      = 1000;   // simulated opening credit

/* ---------- Paths ---------- */
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('REPORT_PATH', ROOT_PATH . '/reports');
define('LOG_PATH', ROOT_PATH . '/logs');

/* ---------- SMTP (PHPMailer) ---------- */
// DEV MODE: while SMTP is disabled, OTP codes are shown on screen and all
// emails are logged to logs/mail.log. Set to false for production-like testing.
const DEV_SHOW_OTP   = false;
const SMTP_ENABLED   = true;

const SMTP_HOST      = 'smtp.gmail.com';
const SMTP_PORT      = 587;

const SMTP_USER      = 'your_mail_id';
const SMTP_PASS      = 'your_app_password';

const SMTP_FROM      = 'your_mail_id';
const SMTP_FROM_NAME = 'FinVault Banking';

/* ---------- Autoloader for /includes classes ---------- */
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/* ---------- Small global helpers ---------- */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : BASE_URL . $path));
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function money(float|string $amount): string
{
    return '₹' . number_format((float) $amount, 2);
}
