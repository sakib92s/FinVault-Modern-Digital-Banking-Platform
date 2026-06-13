<?php
declare(strict_types=1);

final class Transaction
{
    public static function generateRef(): string
    {
        return 'FV' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
    }

    public static function transfer(int $userId, string $toAccountNumber, float $amount,
                                    string $note = '', string $channel = 'internal'): array
    {
        if ($amount <= 0)        return ['success' => false, 'error' => 'Amount must be greater than zero.'];
        if ($amount > 10000000)  return ['success' => false, 'error' => 'Amount exceeds the simulation limit.'];

        $db = Database::get();
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM accounts WHERE user_id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
            $stmt->execute([$userId]);
            $from = $stmt->fetch();
            if (!$from) { $db->rollBack(); return ['success' => false, 'error' => 'Your account is not active.']; }

            $stmt = $db->prepare('SELECT * FROM accounts WHERE account_number = ? FOR UPDATE');
            $stmt->execute([$toAccountNumber]);
            $to = $stmt->fetch();
            if (!$to)                       { $db->rollBack(); return ['success' => false, 'error' => 'Beneficiary account not found.']; }
            if ($to['status'] !== 'active') { $db->rollBack(); return ['success' => false, 'error' => 'Beneficiary account is not active.']; }
            if ((int) $to['id'] === (int) $from['id']) { $db->rollBack(); return ['success' => false, 'error' => 'Cannot transfer to your own account.']; }
            if ((float) $from['balance'] < $amount)    { $db->rollBack(); return ['success' => false, 'error' => 'Insufficient balance.']; }

            $fromBal = (float) $from['balance'] - $amount;
            $toBal   = (float) $to['balance'] + $amount;
            $db->prepare('UPDATE accounts SET balance = ? WHERE id = ?')->execute([$fromBal, $from['id']]);
            $db->prepare('UPDATE accounts SET balance = ? WHERE id = ?')->execute([$toBal, $to['id']]);

            $ref      = self::generateRef();
            $sender   = User::find($userId);
            $receiver = User::find((int) $to['user_id']);

            $ins = $db->prepare(
                'INSERT INTO transactions (txn_ref, account_id, user_id, counterparty_account, counterparty_name,
                 type, amount, balance_after, description, channel) VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([$ref, $from['id'], $userId, $to['account_number'], $receiver['full_name'] ?? '',
                'transfer_out', $amount, $fromBal, $note, $channel]);
            $ins->execute([$ref, $to['id'], $to['user_id'], $from['account_number'], $sender['full_name'] ?? '',
                'transfer_in', $amount, $toBal, $note, $channel]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'error' => 'Transfer failed. Please try again.'];
        }

        AuditLog::record($userId, 'TRANSFER', money($amount) . " to {$to['account_number']} ref $ref");
        Notification::add($userId, 'Transfer successful', money($amount) . ' sent to ' . ($receiver['full_name'] ?? $to['account_number']) . '. Ref: ' . $ref, 'transfer');
        Notification::add((int) $to['user_id'], 'Money received', money($amount) . ' received from ' . ($sender['full_name'] ?? '') . '. Ref: ' . $ref, 'transfer');

        // Email to sender
        if (!empty($sender['email'])) {
            Mailer::send($sender['email'], 'Transfer successful - ' . $ref,
                '<p>Hello ' . e($sender['full_name']) . ',</p><p>Your transfer of <b>' . money($amount) .
                '</b> to account <b>' . e($to['account_number']) . '</b> was successful.</p>' .
                '<p>Reference: <b>' . $ref . '</b><br>Available balance: <b>' . money($fromBal) . '</b></p>');
        }

        // Email to receiver (ADDED)
        if (!empty($receiver['email'])) {
            Mailer::send($receiver['email'], 'You received a payment - ' . $ref,
                '<p>Hello ' . e($receiver['full_name']) . ',</p><p>You have received <b>' . money($amount) .
                '</b> from ' . e($sender['full_name']) . ' (Account: ' . e($from['account_number']) . ').</p>' .
                '<p>Reference: <b>' . $ref . '</b><br>New balance: <b>' . money($toBal) . '</b></p>');
        }

        return ['success' => true, 'reference' => $ref, 'balance' => $fromBal,
                'beneficiary' => $receiver['full_name'] ?? '', 'account' => $to['account_number'], 'amount' => $amount];
    }

    public static function dateRange(string $period, ?string $from = null, ?string $to = null): array
    {
        $end = date('Y-m-d 23:59:59');
        return match ($period) {
            'today'      => [date('Y-m-d 00:00:00'), $end],
            'yesterday'  => [date('Y-m-d 00:00:00', strtotime('-1 day')), date('Y-m-d 23:59:59', strtotime('-1 day'))],
            'last7'      => [date('Y-m-d 00:00:00', strtotime('-6 days')), $end],
            'last30'     => [date('Y-m-d 00:00:00', strtotime('-29 days')), $end],
            'this_month' => [date('Y-m-01 00:00:00'), $end],
            'last_month' => [date('Y-m-01 00:00:00', strtotime('first day of last month')),
                             date('Y-m-t 23:59:59', strtotime('last day of last month'))],
            'this_year'  => [date('Y-01-01 00:00:00'), $end],
            'custom'     => [($from ?: date('Y-m-d')) . ' 00:00:00', ($to ?: date('Y-m-d')) . ' 23:59:59'],
            default      => ['1970-01-01 00:00:00', $end],
        };
    }

    public static function history(int $userId, string $period = 'all', ?string $from = null,
                                   ?string $to = null, string $q = '', int $limit = 200): array
    {
        [$start, $end] = self::dateRange($period, $from, $to);
        $sql = 'SELECT * FROM transactions WHERE user_id = ? AND created_at BETWEEN ? AND ?';
        $params = [$userId, $start, $end];
        if ($q !== '') {
            $sql .= ' AND (txn_ref LIKE ? OR description LIKE ? OR counterparty_name LIKE ? OR counterparty_account LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function summary(int $userId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT
               COALESCE(SUM(CASE WHEN type IN ('deposit','transfer_in') THEN amount END), 0)  AS total_in,
               COALESCE(SUM(CASE WHEN type IN ('withdrawal','transfer_out') THEN amount END), 0) AS total_out,
               COALESCE(SUM(CASE WHEN type = 'transfer_out' THEN 1 ELSE 0 END), 0) AS transfer_count
             FROM transactions WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public static function adminList(string $q = '', string $period = 'all', bool $largeOnly = false, int $limit = 200): array
    {
        [$start, $end] = self::dateRange($period);
        $sql = 'SELECT t.*, u.full_name, a.account_number
                FROM transactions t JOIN users u ON u.id = t.user_id JOIN accounts a ON a.id = t.account_id
                WHERE t.created_at BETWEEN ? AND ?';
        $params = [$start, $end];
        if ($q !== '') {
            $sql .= ' AND (t.txn_ref LIKE ? OR u.full_name LIKE ? OR a.account_number LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($largeOnly) {
            $sql .= ' AND t.amount >= ' . (int) LARGE_TRANSFER_THRESHOLD;
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT ' . $limit;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function search(string $q, ?int $userId = null, int $limit = 8): array
    {
        $sql = 'SELECT txn_ref, type, amount, created_at FROM transactions WHERE txn_ref LIKE ?';
        $params = [$q . '%'];
        if ($userId !== null) { $sql .= ' AND user_id = ?'; $params[] = $userId; }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}