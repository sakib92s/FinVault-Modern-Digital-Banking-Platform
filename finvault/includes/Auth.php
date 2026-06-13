<?php
declare(strict_types=1);

final class Auth
{
    /* ================= REGISTRATION ================= */

    public static function register(array $d): array
    {
        $required = ['full_name', 'dob', 'gender', 'email', 'mobile', 'address', 'password'];
        foreach ($required as $f) {
            if (empty(trim((string) ($d[$f] ?? '')))) {
                return ['success' => false, 'error' => 'Please fill all required fields.'];
            }
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address.'];
        }
        if (!preg_match('/^[6-9]\d{9}$/', $d['mobile'])) {
            return ['success' => false, 'error' => 'Invalid 10-digit mobile number.'];
        }
        if (strlen($d['password']) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if (!empty($d['pan_number']) && !preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', strtoupper($d['pan_number']))) {
            return ['success' => false, 'error' => 'Invalid PAN number format.'];
        }
        if (!empty($d['aadhaar_number']) && !preg_match('/^\d{12}$/', $d['aadhaar_number'])) {
            return ['success' => false, 'error' => 'Aadhaar must be 12 digits.'];
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$d['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }

        $stmt = $db->prepare(
            'INSERT INTO users (full_name, dob, gender, email, mobile, address, city, state, pan_number, aadhaar_number, profile_photo, password_hash)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            trim($d['full_name']), $d['dob'], $d['gender'], strtolower(trim($d['email'])),
            $d['mobile'], trim($d['address']), trim($d['city'] ?? ''), trim($d['state'] ?? ''),
            strtoupper(trim($d['pan_number'] ?? '')), trim($d['aadhaar_number'] ?? ''),
            $d['profile_photo'] ?? null,
            password_hash($d['password'], PASSWORD_DEFAULT),
        ]);
        $userId = (int) $db->lastInsertId();

        self::sendOtp($userId, 'email_verify');
        AuditLog::record($userId, 'REGISTER', 'New customer registration');
        return ['success' => true, 'user_id' => $userId];
    }

    /* ================= OTP ================= */

    public static function sendOtp(int $userId, string $purpose = 'email_verify'): bool
    {
        $db  = Database::get();
        $otp = (string) random_int(100000, 999999);

        $db->prepare('UPDATE otp_codes SET used = 1 WHERE user_id = ? AND purpose = ? AND used = 0')
           ->execute([$userId, $purpose]);
        $db->prepare('INSERT INTO otp_codes (user_id, otp_hash, purpose, expires_at) VALUES (?,?,?,?)')
           ->execute([$userId, password_hash($otp, PASSWORD_DEFAULT), $purpose,
                      date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60)]);

        if (!SMTP_ENABLED && defined('DEV_SHOW_OTP') && DEV_SHOW_OTP) {
            Session::flash('warning', 'DEV MODE (no SMTP configured): your OTP is ' . $otp);
        }

        $user = User::find($userId);
        if ($user) {
            Mailer::send(
                $user['email'],
                'Your FinVault verification code',
                '<p>Hello ' . e($user['full_name']) . ',</p>' .
                '<p>Your one-time verification code is:</p>' .
                '<p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . $otp . '</p>' .
                '<p>This code expires in ' . OTP_EXPIRY_MINUTES . ' minutes.</p>'
            );
        }
        return true;
    }

    public static function verifyOtp(int $userId, string $otp, string $purpose = 'email_verify'): array
    {
        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT * FROM otp_codes WHERE user_id = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $row = $stmt->fetch();

        if (!$row) return ['success' => false, 'error' => 'No active OTP found. Please request a new one.'];
        if (strtotime($row['expires_at']) < time()) {
            return ['success' => false, 'error' => 'OTP has expired. Please request a new one.'];
        }
        if (!password_verify($otp, $row['otp_hash'])) {
            return ['success' => false, 'error' => 'Incorrect OTP.'];
        }

        $db->prepare('UPDATE otp_codes SET used = 1 WHERE id = ?')->execute([$row['id']]);

        if ($purpose === 'email_verify') {
            $db->prepare("UPDATE users SET email_verified = 1, status = 'active' WHERE id = ?")->execute([$userId]);
            $account = Account::create($userId, 'savings');
            Notification::add($userId, 'Welcome to FinVault', 'Your account ' . $account['account_number'] . ' is now active.', 'general');
            AuditLog::record($userId, 'EMAIL_VERIFIED', 'Email verified, account opened: ' . $account['account_number']);
            $user = User::find($userId);
            Mailer::send($user['email'], 'Welcome to FinVault',
                '<p>Hello ' . e($user['full_name']) . ',</p><p>Your FinVault savings account <b>' .
                $account['account_number'] . '</b> is active with a simulated opening balance of ' . money(MIN_OPENING_BALANCE) . '.</p>');

            // KYC reminder email (ADDED)
            Mailer::send($user['email'], 'Complete your KYC verification',
                '<p>Hello ' . e($user['full_name']) . ',</p>' .
                '<p>Your account is now active. To unlock all features, please upload your KYC documents (Aadhaar, PAN, photo) from the portal.</p>' .
                '<p><a href="' . BASE_URL . '/customer/kyc.php">Upload KYC documents</a></p>'
            );
        }
        return ['success' => true];
    }

    /* ================= LOGIN / LOGOUT ================= */

    public static function login(string $email, string $password, bool $remember = false, string $expectedRole = 'customer'): array
    {
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND role = ?');
        $stmt->execute([strtolower(trim($email)), $expectedRole]);
        $user = $stmt->fetch();

        if (!$user) return ['success' => false, 'error' => 'Invalid email or password.'];

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'error' => 'Account locked due to failed attempts. Try again after ' .
                    date('H:i', strtotime($user['locked_until'])) . '.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_attempts'] + 1;
            $lock = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60) : null;
            $db->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?')
               ->execute([$lock ? 0 : $attempts, $lock, $user['id']]);
            AuditLog::record((int) $user['id'], 'LOGIN_FAILED', 'Failed login attempt #' . $attempts);
            return ['success' => false, 'error' => $lock
                ? 'Too many failed attempts. Account locked for ' . LOCKOUT_MINUTES . ' minutes.'
                : 'Invalid email or password.'];
        }

        if (!$user['email_verified']) {
            return ['success' => false, 'error' => 'Please verify your email first.', 'unverified_user_id' => (int) $user['id']];
        }
        if ($user['status'] === 'suspended') {
            return ['success' => false, 'error' => 'Your account is suspended. Contact support.'];
        }

        $db->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')
           ->execute([$user['id']]);
        Session::loginUser($user);

        if ($remember) self::issueRememberToken((int) $user['id']);

        AuditLog::record((int) $user['id'], 'LOGIN', 'Successful login');
        Notification::add((int) $user['id'], 'New login', 'Login from IP ' . client_ip() . ' at ' . date('d M Y H:i'), 'login');
        return ['success' => true, 'role' => $user['role']];
    }

    public static function logout(): void
    {
        $userId = Session::userId();
        if ($userId) AuditLog::record($userId, 'LOGOUT', 'User signed out');
        if (!empty($_COOKIE['fv_remember'])) {
            [$selector] = explode(':', $_COOKIE['fv_remember']) + [null];
            if ($selector) {
                Database::get()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
            }
            setcookie('fv_remember', '', time() - 3600, '/');
        }
        Session::destroy();
    }

    /* ================= REMEMBER ME ================= */

    private static function issueRememberToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        Database::get()->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?,?,?,?)'
        )->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', time() + 30 * 86400)]);
        setcookie('fv_remember', $selector . ':' . $validator, [
            'expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }

    public static function tryRememberLogin(): void
    {
        if (Session::isLoggedIn() || empty($_COOKIE['fv_remember'])) return;
        $parts = explode(':', $_COOKIE['fv_remember']);
        if (count($parts) !== 2) return;
        [$selector, $validator] = $parts;

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()');
        $stmt->execute([$selector]);
        $token = $stmt->fetch();
        if (!$token || !hash_equals($token['token_hash'], hash('sha256', $validator))) return;

        $user = User::find((int) $token['user_id']);
        if ($user && $user['status'] === 'active') {
            Session::loginUser($user);
            AuditLog::record((int) $user['id'], 'LOGIN', 'Auto login via remember-me token');
        }
    }

    /* ================= PASSWORD RESET ================= */

    public static function requestPasswordReset(string $email): void
    {
        $user = User::findByEmail($email);
        if (!$user) return;

        $selector = bin2hex(random_bytes(8));
        $token    = bin2hex(random_bytes(32));
        Database::get()->prepare(
            'INSERT INTO password_resets (user_id, selector, token_hash, expires_at) VALUES (?,?,?,?)'
        )->execute([$user['id'], $selector, hash('sha256', $token), date('Y-m-d H:i:s', time() + 1800)]);

        $link = BASE_URL . '/customer/index.php?page=reset&selector=' . $selector . '&token=' . $token;
        Mailer::send($user['email'], 'Reset your FinVault password',
            '<p>Hello ' . e($user['full_name']) . ',</p><p>Click the link below to reset your password (valid for 30 minutes):</p>' .
            '<p><a href="' . $link . '">' . $link . '</a></p><p>If you did not request this, ignore this email.</p>');
        AuditLog::record((int) $user['id'], 'PASSWORD_RESET_REQUEST', 'Password reset link generated');
    }

    public static function resetPassword(string $selector, string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 8) return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM password_resets WHERE selector = ? AND used = 0 AND expires_at > NOW()');
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $token))) {
            return ['success' => false, 'error' => 'Invalid or expired reset link.'];
        }
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $row['user_id']]);
        $db->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$row['id']]);
        Notification::add((int) $row['user_id'], 'Password changed', 'Your password was reset successfully.', 'security');
        AuditLog::record((int) $row['user_id'], 'PASSWORD_RESET', 'Password reset via email link');
        return ['success' => true];
    }

    public static function changePassword(int $userId, string $current, string $new): array
    {
        if (strlen($new) < 8) return ['success' => false, 'error' => 'New password must be at least 8 characters.'];
        $user = User::find($userId);
        if (!$user || !password_verify($current, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect.'];
        }
        Database::get()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        Notification::add($userId, 'Password changed', 'Your password was changed successfully.', 'security');
        AuditLog::record($userId, 'PASSWORD_CHANGE', 'Password changed from profile');
        Mailer::send($user['email'], 'FinVault password changed',
            '<p>Your FinVault password was changed. If this was not you, contact support immediately.</p>');
        return ['success' => true];
    }
}