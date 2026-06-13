<?php
declare(strict_types=1);

/**
 * Notification - in-app notification center.
 */
final class Notification
{
    public static function add(int $userId, string $title, string $message, string $type = 'general'): void
    {
        Database::get()->prepare(
            'INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)'
        )->execute([$userId, $title, $message, $type]);
    }

    public static function forUser(int $userId, int $limit = 20): array
    {
        $stmt = Database::get()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = Database::get()->prepare(
            'SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetch()['c'];
    }

    public static function markAllRead(int $userId): void
    {
        Database::get()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);
    }
}
