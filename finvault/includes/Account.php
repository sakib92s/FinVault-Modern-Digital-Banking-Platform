<?php
declare(strict_types=1);

final class Account
{
    public static function create(int $userId, string $type = 'savings'): array
    {
        $db = Database::get();
        do {
            $number = '1000' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $chk = $db->prepare('SELECT id FROM accounts WHERE account_number = ?');
            $chk->execute([$number]);
        } while ($chk->fetch());

        $db->prepare('INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?,?,?,?)')
           ->execute([$userId, $number, $type, MIN_OPENING_BALANCE]);
        $accountId = (int) $db->lastInsertId();

        $db->prepare(
            "INSERT INTO transactions (txn_ref, account_id, user_id, type, amount, balance_after, description, channel)
             VALUES (?,?,?,?,?,?,?, 'admin')"
        )->execute([
            Transaction::generateRef(), $accountId, $userId, 'deposit',
            MIN_OPENING_BALANCE, MIN_OPENING_BALANCE, 'Opening balance (simulated)',
        ]);

        return ['id' => $accountId, 'account_number' => $number];
    }

    public static function forUser(int $userId): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM accounts WHERE user_id = ? AND status <> 'closed' ORDER BY id LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function byNumber(string $number): ?array
    {
        $stmt = Database::get()->prepare(
            'SELECT a.*, u.full_name, u.email, u.mobile FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.account_number = ?'
        );
        $stmt->execute([$number]);
        return $stmt->fetch() ?: null;
    }

    public static function search(string $q, int $limit = 10): array
    {
        $like = '%' . $q . '%';
        $stmt = Database::get()->prepare(
            'SELECT a.account_number, a.account_type, a.status, u.full_name
             FROM accounts a JOIN users u ON u.id = a.user_id
             WHERE a.account_number LIKE ? OR u.full_name LIKE ? ORDER BY a.account_number LIMIT ' . $limit
        );
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    public static function setStatus(int $accountId, string $status, int $adminId): void
    {
        Database::get()->prepare('UPDATE accounts SET status = ? WHERE id = ?')->execute([$status, $accountId]);
        AuditLog::record($adminId, 'ACCOUNT_STATUS', "Account #$accountId set to $status");

        // Email to account holder
        $acc = Database::get()->prepare('SELECT user_id FROM accounts WHERE id = ?');
        $acc->execute([$accountId]);
        $accRow = $acc->fetch();
        if ($accRow) {
            $user = User::find((int) $accRow['user_id']);
            if ($user && !empty($user['email'])) {
                $statusText = $status === 'frozen' ? 'frozen' : 're‑activated';
                Mailer::send($user['email'], 'Your account has been ' . $statusText,
                    '<p>Hello ' . e($user['full_name']) . ',</p>' .
                    '<p>Your FinVault account has been <b>' . $statusText . '</b> by the bank.</p>' .
                    '<p>If you have any questions, please contact support.</p>'
                );
            }
        }
    }

    public static function adminAdjust(int $accountId, float $amount, string $type, int $adminId, string $note): array
    {
        if ($amount <= 0) return ['success' => false, 'error' => 'Amount must be positive.'];
        $db = Database::get();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT * FROM accounts WHERE id = ? FOR UPDATE');
            $stmt->execute([$accountId]);
            $acc = $stmt->fetch();
            if (!$acc) { $db->rollBack(); return ['success' => false, 'error' => 'Account not found.']; }

            $newBalance = $type === 'deposit' ? $acc['balance'] + $amount : $acc['balance'] - $amount;
            if ($newBalance < 0) { $db->rollBack(); return ['success' => false, 'error' => 'Insufficient balance.']; }

            $db->prepare('UPDATE accounts SET balance = ? WHERE id = ?')->execute([$newBalance, $accountId]);
            $db->prepare(
                "INSERT INTO transactions (txn_ref, account_id, user_id, type, amount, balance_after, description, channel)
                 VALUES (?,?,?,?,?,?,?, 'admin')"
            )->execute([Transaction::generateRef(), $accountId, $acc['user_id'], $type, $amount, $newBalance, $note]);
            $db->commit();

            AuditLog::record($adminId, 'ADMIN_ADJUST', ucfirst($type) . ' of ' . money($amount) . " on account #$accountId");
            Notification::add((int) $acc['user_id'], 'Account ' . $type,
                ucfirst($type) . ' of ' . money($amount) . ' processed by bank. ' . $note, 'transfer');
            return ['success' => true];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'error' => 'Operation failed.'];
        }
    }
}