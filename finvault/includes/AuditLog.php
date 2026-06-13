<?php
declare(strict_types=1);

/**
 * AuditLog - security & activity trail (DB + file log).
 */
final class AuditLog
{
    public static function record(?int $userId, string $action, string $details = ''): void
    {
        $ip = client_ip();
        try {
            Database::get()->prepare(
                'INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)'
            )->execute([$userId, $action, $details, $ip]);
        } catch (Throwable $e) {
            // never break the app because of logging
        }
        if (!is_dir(LOG_PATH)) mkdir(LOG_PATH, 0775, true);
        $line = sprintf("[%s] user=%s ip=%s action=%s %s\n",
            date('Y-m-d H:i:s'), $userId ?? '-', $ip, $action, $details);
        @file_put_contents(LOG_PATH . '/audit.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function recent(int $limit = 50): array
    {
        $stmt = Database::get()->query(
            'SELECT l.*, u.full_name FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC LIMIT ' . $limit
        );
        return $stmt->fetchAll();
    }

    public static function forUser(int $userId, int $limit = 50): array
    {
        $stmt = Database::get()->prepare(
            'SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
