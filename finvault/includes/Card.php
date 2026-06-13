<?php
declare(strict_types=1);

final class Card
{
    public static function request(int $userId, string $type): array
    {
        if (!in_array($type, ['debit', 'credit'], true)) {
            return ['success' => false, 'error' => 'Invalid card type.'];
        }
        $dup = Database::get()->prepare(
            "SELECT id FROM cards WHERE user_id = ? AND card_type = ? AND status IN ('requested','active','blocked')"
        );
        $dup->execute([$userId, $type]);
        if ($dup->fetch()) return ['success' => false, 'error' => 'You already have a ' . $type . ' card or pending request.'];

        Database::get()->prepare('INSERT INTO cards (user_id, card_type) VALUES (?,?)')->execute([$userId, $type]);
        AuditLog::record($userId, 'CARD_REQUEST', ucfirst($type) . ' card requested');
        Notification::add($userId, 'Card request received', 'Your ' . $type . ' card request is pending approval.', 'card');
        return ['success' => true];
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM cards WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function toggleBlock(int $userId, int $cardId, string $action): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM cards WHERE id = ? AND user_id = ?');
        $stmt->execute([$cardId, $userId]);
        $card = $stmt->fetch();
        if (!$card) return ['success' => false, 'error' => 'Card not found.'];

        $new = $action === 'block' ? 'blocked' : 'active';
        $ok  = ($action === 'block' && $card['status'] === 'active')
            || ($action === 'unblock' && $card['status'] === 'blocked');
        if (!$ok) return ['success' => false, 'error' => 'Action not allowed for current card status.'];

        Database::get()->prepare('UPDATE cards SET status = ? WHERE id = ?')->execute([$new, $cardId]);
        AuditLog::record($userId, 'CARD_' . strtoupper($action), ucfirst($card['card_type']) . " card #$cardId $new");
        Notification::add($userId, 'Card ' . $new, 'Your ' . $card['card_type'] . ' card is now ' . $new . '.', 'card');

        $user = User::find($userId);
        if ($user && !empty($user['email'])) {
            Mailer::send($user['email'], 'Your card has been ' . $new,
                '<p>Hello ' . e($user['full_name']) . ',</p>' .
                '<p>Your ' . e($card['card_type']) . ' card has been <b>' . $new . '</b>.</p>'
            );
        }
        return ['success' => true];
    }

    public static function adminList(string $status = '', int $limit = 100): array
    {
        $sql = 'SELECT c.*, u.full_name, u.email FROM cards c JOIN users u ON u.id = c.user_id';
        $params = [];
        if ($status !== '') { $sql .= ' WHERE c.status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY c.created_at DESC LIMIT ' . $limit;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function review(int $cardId, string $action, int $adminId, string $remarks = ''): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM cards WHERE id = ?');
        $stmt->execute([$cardId]);
        $card = $stmt->fetch();
        if (!$card) return ['success' => false, 'error' => 'Card not found.'];

        $status = match ($action) {
            'approve' => 'active',
            'reject'  => 'rejected',
            'block'   => 'blocked',
            'unblock' => 'active',
            default   => null,
        };
        if ($status === null) return ['success' => false, 'error' => 'Invalid action.'];

        $number = $card['card_number'];
        if ($action === 'approve' && empty($number)) {
            $number = '4' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT)
                          . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }
        Database::get()->prepare('UPDATE cards SET status = ?, card_number = ?, admin_remarks = ? WHERE id = ?')
            ->execute([$status, $number, trim($remarks) ?: $card['admin_remarks'], $cardId]);

        AuditLog::record($adminId, 'CARD_' . strtoupper($action), ucfirst($card['card_type']) . " card #$cardId $status");
        Notification::add((int) $card['user_id'], 'Card update',
            'Your ' . $card['card_type'] . ' card is now ' . $status . '.', 'card');

        $user = User::find((int) $card['user_id']);
        if ($user && !empty($user['email'])) {
            $actionText = match ($action) {
                'approve' => 'approved and is now active',
                'reject'  => 'rejected',
                'block'   => 'blocked',
                'unblock' => 'unblocked',
                default   => $status,
            };
            $remarksLine = $remarks ? '<p>Remarks: ' . e($remarks) . '</p>' : '';
            Mailer::send($user['email'], 'Card ' . $actionText,
                '<p>Hello ' . e($user['full_name']) . ',</p>' .
                '<p>Your ' . e($card['card_type']) . ' card has been <b>' . $actionText . '</b>.</p>' .
                $remarksLine
            );
        }

        return ['success' => true];
    }
}