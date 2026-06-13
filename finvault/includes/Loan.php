<?php
declare(strict_types=1);

/**
 * Loan - applications, EMI calculation and approval workflow.
 */
final class Loan
{
    public const RATES = ['personal' => 11.5, 'education' => 8.5, 'business' => 13.0];

    /** Standard EMI formula: P*r*(1+r)^n / ((1+r)^n - 1). */
    public static function emi(float $principal, float $annualRate, int $months): float
    {
        if ($months <= 0) return 0.0;
        $r = $annualRate / 12 / 100;
        if ($r == 0.0) return round($principal / $months, 2);
        $pow = pow(1 + $r, $months);
        return round($principal * $r * $pow / ($pow - 1), 2);
    }

    public static function apply(int $userId, string $type, float $amount, int $months, string $purpose): array
    {
        if (!isset(self::RATES[$type]))        return ['success' => false, 'error' => 'Invalid loan type.'];
        if ($amount < 10000 || $amount > 5000000) return ['success' => false, 'error' => 'Amount must be between ₹10,000 and ₹50,00,000.'];
        if ($months < 6 || $months > 240)      return ['success' => false, 'error' => 'Tenure must be 6 to 240 months.'];

        $rate = self::RATES[$type];
        $ref  = 'LN' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        Database::get()->prepare(
            'INSERT INTO loans (user_id, loan_ref, loan_type, amount, tenure_months, interest_rate, emi, purpose)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$userId, $ref, $type, $amount, $months, $rate, self::emi($amount, $rate, $months), trim($purpose)]);

        AuditLog::record($userId, 'LOAN_APPLY', "$type loan " . money($amount) . " ref $ref");
        Notification::add($userId, 'Loan application received', ucfirst($type) . ' loan of ' . money($amount) . ' is under review. Ref: ' . $ref, 'loan');
        return ['success' => true, 'reference' => $ref];
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function adminList(string $status = '', string $q = '', int $limit = 100): array
    {
        $sql = 'SELECT l.*, u.full_name, u.email FROM loans l JOIN users u ON u.id = l.user_id WHERE 1=1';
        $params = [];
        if ($status !== '') { $sql .= ' AND l.status = ?'; $params[] = $status; }
        if ($q !== '')      { $sql .= ' AND (l.loan_ref LIKE ? OR u.full_name LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like); }
        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . $limit;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function review(int $loanId, string $status, string $remarks, int $adminId): array
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid status.'];
        }
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM loans WHERE id = ?');
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();
        if (!$loan) return ['success' => false, 'error' => 'Loan not found.'];

        $db->prepare('UPDATE loans SET status = ?, admin_remarks = ? WHERE id = ?')
           ->execute([$status, trim($remarks), $loanId]);

        // On approval, credit the simulated loan amount to the account
        if ($status === 'approved') {
            $acc = Account::forUser((int) $loan['user_id']);
            if ($acc) {
                Account::adminAdjust((int) $acc['id'], (float) $loan['amount'], 'deposit', $adminId,
                    'Loan disbursement ' . $loan['loan_ref'] . ' (simulated)');
            }
        }

        AuditLog::record($adminId, 'LOAN_' . strtoupper($status), 'Loan ' . $loan['loan_ref'] . ' ' . $status);
        Notification::add((int) $loan['user_id'], 'Loan ' . $status,
            'Your loan ' . $loan['loan_ref'] . ' was ' . $status . '.' . ($remarks ? ' Remarks: ' . $remarks : ''), 'loan');
        $user = User::find((int) $loan['user_id']);
        if ($user) {
            Mailer::send($user['email'], 'Loan ' . $status . ' - ' . $loan['loan_ref'],
                '<p>Hello ' . e($user['full_name']) . ',</p><p>Your ' . e($loan['loan_type']) . ' loan of <b>' .
                money((float) $loan['amount']) . '</b> has been <b>' . $status . '</b>.</p>' .
                ($remarks ? '<p>Remarks: ' . e($remarks) . '</p>' : ''));
        }
        return ['success' => true];
    }
}
