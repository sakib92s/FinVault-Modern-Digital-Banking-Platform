<?php
declare(strict_types=1);

/**
 * Beneficiary - manage payees with intelligent autocomplete.
 */
final class Beneficiary
{
    public static function add(int $userId, array $d): array
    {
        if (empty($d['name']) || empty($d['account_number'])) {
            return ['success' => false, 'error' => 'Name and account number are required.'];
        }
        $target = Account::byNumber($d['account_number']);
        if (!$target) return ['success' => false, 'error' => 'No FinVault account found with that number.'];
        if ((int) $target['user_id'] === $userId) {
            return ['success' => false, 'error' => 'You cannot add your own account.'];
        }
        $dup = Database::get()->prepare('SELECT id FROM beneficiaries WHERE user_id = ? AND account_number = ?');
        $dup->execute([$userId, $d['account_number']]);
        if ($dup->fetch()) return ['success' => false, 'error' => 'Beneficiary already added.'];

        Database::get()->prepare(
            'INSERT INTO beneficiaries (user_id, name, account_number, email, mobile, verified) VALUES (?,?,?,?,?,1)'
        )->execute([$userId, trim($d['name']), $d['account_number'],
                    trim($d['email'] ?? '') ?: null, trim($d['mobile'] ?? '') ?: null]);
        AuditLog::record($userId, 'BENEFICIARY_ADD', 'Added beneficiary ' . $d['account_number']);
        return ['success' => true];
    }

    public static function update(int $userId, int $id, array $d): array
    {
        Database::get()->prepare(
            'UPDATE beneficiaries SET name = ?, email = ?, mobile = ? WHERE id = ? AND user_id = ?'
        )->execute([trim($d['name']), trim($d['email'] ?? '') ?: null, trim($d['mobile'] ?? '') ?: null, $id, $userId]);
        AuditLog::record($userId, 'BENEFICIARY_EDIT', "Edited beneficiary #$id");
        return ['success' => true];
    }

    public static function delete(int $userId, int $id): void
    {
        Database::get()->prepare('DELETE FROM beneficiaries WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        AuditLog::record($userId, 'BENEFICIARY_DELETE', "Deleted beneficiary #$id");
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $userId, int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM beneficiaries WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Intelligent autocomplete while typing a name, account number,
     * email or mobile - searches the user's saved beneficiaries first,
     * then other FinVault account holders.
     */
    public static function autocomplete(int $userId, string $q, int $limit = 8): array
    {
        $like = $q . '%';
        $any  = '%' . $q . '%';
        $db   = Database::get();

        $stmt = $db->prepare(
            'SELECT name, account_number, email, mobile, "saved" AS source FROM beneficiaries
             WHERE user_id = ? AND (name LIKE ? OR account_number LIKE ? OR email LIKE ? OR mobile LIKE ?)
             ORDER BY name LIMIT ' . $limit
        );
        $stmt->execute([$userId, $any, $like, $any, $like]);
        $results = $stmt->fetchAll();

        if (count($results) < $limit) {
            $stmt = $db->prepare(
                'SELECT u.full_name AS name, a.account_number, u.email, u.mobile, "bank" AS source
                 FROM accounts a JOIN users u ON u.id = a.user_id
                 WHERE a.status = "active" AND u.id <> ? AND u.role = "customer"
                   AND (u.full_name LIKE ? OR a.account_number LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)
                 ORDER BY u.full_name LIMIT ' . ($limit - count($results))
            );
            $stmt->execute([$userId, $any, $like, $any, $like]);
            $results = array_merge($results, $stmt->fetchAll());
        }
        return $results;
    }
}
