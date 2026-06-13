<?php
declare(strict_types=1);

/**
 * Session - secure session handling, timeout, CSRF and flash messages.
 */
final class Session
{
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
        session_start();

        // Idle timeout
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last !== null && (time() - $last) > SESSION_TIMEOUT) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['warning'] = 'Session expired. Please sign in again.';
        }
        $_SESSION['_last_activity'] = time();

        // Periodic ID regeneration (every 10 min)
        if (!isset($_SESSION['_regenerated']) || time() - $_SESSION['_regenerated'] > 600) {
            session_regenerate_id(true);
            $_SESSION['_regenerated'] = time();
        }
    }

    public static function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['_regenerated'] = time();
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        return self::userId() !== null;
    }

    /** Guard a page: redirects to the right login when role does not match. */
    public static function require(string $role): void
    {
        if (!self::isLoggedIn()) {
            Auth::tryRememberLogin();
        }
        if (!self::isLoggedIn() || self::role() !== $role) {
            redirect($role === 'admin' ? '/admin/index.php' : '/customer/index.php');
        }
    }

    /* ---------- CSRF ---------- */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::csrfToken() . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }

    public static function checkCsrfOrFail(): void
    {
        if (!self::verifyCsrf($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }

    /* ---------- Flash messages ---------- */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    public static function pullFlashes(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
