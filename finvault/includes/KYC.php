<?php
declare(strict_types=1);

final class KYC
{
    public const DOC_TYPES = ['aadhaar' => 'Aadhaar Card', 'pan' => 'PAN Card', 'photo' => 'Passport Photo'];

    public static function upload(int $userId, string $docType, array $file): array
    {
        if (!isset(self::DOC_TYPES[$docType])) return ['success' => false, 'error' => 'Invalid document type.'];
        $path = User::saveUpload($file, 'kyc');
        if ($path === null) return ['success' => false, 'error' => 'Upload failed. Use JPG/PNG/WEBP/PDF up to 3 MB.'];

        Database::get()->prepare(
            "INSERT INTO kyc_documents (user_id, doc_type, file_path, status)
             VALUES (?,?,?, 'pending')
             ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), status = 'pending',
                                     admin_remarks = NULL, uploaded_at = NOW(), reviewed_at = NULL"
        )->execute([$userId, $docType, $path]);

        AuditLog::record($userId, 'KYC_UPLOAD', self::DOC_TYPES[$docType] . ' uploaded');
        return ['success' => true];
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM kyc_documents WHERE user_id = ?');
        $stmt->execute([$userId]);
        $docs = [];
        foreach ($stmt->fetchAll() as $row) $docs[$row['doc_type']] = $row;
        return $docs;
    }

    public static function overallStatus(int $userId): string
    {
        $docs = self::forUser($userId);
        if (count($docs) < count(self::DOC_TYPES)) return 'incomplete';
        $statuses = array_column($docs, 'status');
        if (in_array('rejected', $statuses, true) || in_array('reupload', $statuses, true)) return 'action_required';
        if (count(array_filter($statuses, fn ($s) => $s === 'approved')) === count(self::DOC_TYPES)) return 'approved';
        return 'pending';
    }

    public static function adminPending(int $limit = 100): array
    {
        $stmt = Database::get()->query(
            "SELECT k.*, u.full_name, u.email FROM kyc_documents k JOIN users u ON u.id = k.user_id
             WHERE k.status IN ('pending','reupload') ORDER BY k.uploaded_at ASC LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }

    public static function review(int $docId, string $action, string $remarks, int $adminId): array
    {
        $status = match ($action) {
            'approve'  => 'approved',
            'reject'   => 'rejected',
            'reupload' => 'reupload',
            default    => null,
        };
        if ($status === null) return ['success' => false, 'error' => 'Invalid action.'];

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM kyc_documents WHERE id = ?');
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) return ['success' => false, 'error' => 'Document not found.'];

        $db->prepare('UPDATE kyc_documents SET status = ?, admin_remarks = ?, reviewed_at = NOW() WHERE id = ?')
           ->execute([$status, trim($remarks) ?: null, $docId]);

        $label = self::DOC_TYPES[$doc['doc_type']] ?? $doc['doc_type'];
        AuditLog::record($adminId, 'KYC_' . strtoupper($action), "$label for user #{$doc['user_id']} -> $status");
        Notification::add((int) $doc['user_id'], 'KYC update',
            $label . ' was marked ' . $status . '.' . ($remarks ? ' Remarks: ' . $remarks : ''), 'kyc');

        // Individual document email
        $user = User::find((int) $doc['user_id']);
        if ($user && !empty($user['email'])) {
            $statusText = match ($status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                'reupload' => 'returned for re‑upload',
                default    => $status,
            };
            $remarksLine = $remarks ? '<p>Remarks: ' . e($remarks) . '</p>' : '';
            Mailer::send($user['email'], 'KYC document ' . $statusText,
                '<p>Hello ' . e($user['full_name']) . ',</p>' .
                '<p>Your document <b>' . e($label) . '</b> has been <b>' . $statusText . '</b>.</p>' .
                $remarksLine .
                ($status === 'reupload' ? '<p>Please upload a new document at your earliest convenience.</p>' : '')
            );
        }

        // Overall KYC approved email (already existed)
        if (self::overallStatus((int) $doc['user_id']) === 'approved') {
            $user = User::find((int) $doc['user_id']);
            if ($user) {
                Notification::add((int) $doc['user_id'], 'KYC approved', 'Your KYC verification is complete.', 'kyc');
                Mailer::send($user['email'], 'KYC approved',
                    '<p>Hello ' . e($user['full_name']) . ',</p><p>Your KYC verification is complete. Enjoy full access to FinVault.</p>');
            }
        }
        return ['success' => true];
    }
}