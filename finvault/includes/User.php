<?php
declare(strict_types=1);

/**
 * User - profile, photo upload, admin user management & search.
 */
final class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function updateProfile(int $id, array $d): array
    {
        $stmt = Database::get()->prepare(
            'UPDATE users SET full_name = ?, mobile = ?, address = ?, city = ?, state = ? WHERE id = ?'
        );
        $stmt->execute([
            trim($d['full_name']), trim($d['mobile']), trim($d['address']),
            trim($d['city'] ?? ''), trim($d['state'] ?? ''), $id,
        ]);
        AuditLog::record($id, 'PROFILE_UPDATE', 'Profile details updated');
        return ['success' => true];
    }

    /** Validates and stores an uploaded image; returns relative path or null. */
    public static function saveUpload(array $file, string $subdir = 'profile'): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 3 * 1024 * 1024) return null;
        $mime = mime_content_type($file['tmp_name']);
        $ext  = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'application/pdf' => 'pdf', default => null,
        };
        if ($ext === null) return null;
        $dir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = $subdir . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
        return move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $name) ? $name : null;
    }

    public static function setProfilePhoto(int $id, string $path): void
    {
        Database::get()->prepare('UPDATE users SET profile_photo = ? WHERE id = ?')->execute([$path, $id]);
    }

    /* ---------- Admin operations ---------- */

    public static function search(string $q, int $limit = 10): array
    {
        $like = '%' . $q . '%';
        $stmt = Database::get()->prepare(
            "SELECT id, full_name, email, mobile, status FROM users WHERE role = 'customer'
             AND (full_name LIKE ? OR email LIKE ? OR mobile LIKE ?) ORDER BY full_name LIMIT " . $limit
        );
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    }

    public static function paginate(string $q = '', int $page = 1, int $perPage = 15): array
    {
        $db = Database::get();
        $like = '%' . $q . '%';
        $where = "role = 'customer'" . ($q !== '' ? ' AND (full_name LIKE ? OR email LIKE ? OR mobile LIKE ?)' : '');
        $params = $q !== '' ? [$like, $like, $like] : [];

        $count = $db->prepare("SELECT COUNT(*) c FROM users WHERE $where");
        $count->execute($params);
        $total = (int) $count->fetch()['c'];

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $db->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int) ceil($total / $perPage)];
    }

    public static function setStatus(int $id, string $status, int $adminId): void
    {
        Database::get()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
        AuditLog::record($adminId, 'USER_STATUS', "User #$id set to $status");
        Notification::add($id, 'Account ' . $status, 'Your account status was changed to ' . $status . '.', 'security');
    }

    public static function delete(int $id, int $adminId): void
    {
        Database::get()->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'")->execute([$id]);
        AuditLog::record($adminId, 'USER_DELETE', "User #$id deleted");
    }

    public static function adminResetPassword(int $id, int $adminId): string
    {
        $temp = 'Fv@' . bin2hex(random_bytes(4));
        Database::get()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        AuditLog::record($adminId, 'ADMIN_PASSWORD_RESET', "Password reset for user #$id");
        $user = self::find($id);
        if ($user) {
            Mailer::send($user['email'], 'FinVault temporary password',
                '<p>An administrator reset your password. Temporary password: <b>' . $temp . '</b></p><p>Please change it after login.</p>');
        }
        return $temp;
    }
}
