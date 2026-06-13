<?php
/**
 * FinVault admin seeder.
 * Run once from CLI after importing finvault.sql:
 *   php database/seed_admin.php
 * Creates default admin: admin@finvault.local / Admin@123
 */
require_once __DIR__ . '/../includes/config.php';

$db = Database::get();
$email = 'admin@finvault.local';

$exists = $db->prepare('SELECT id FROM users WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetch()) {
    exit("Admin already exists.\n");
}

$stmt = $db->prepare(
    "INSERT INTO users (full_name, email, mobile, password_hash, role, status, email_verified)
     VALUES (?, ?, ?, ?, 'admin', 'active', 1)"
);
$stmt->execute([
    'FinVault Administrator',
    $email,
    '9999999999',
    password_hash('Admin@123', PASSWORD_DEFAULT),
]);

echo "Admin created.\nEmail: {$email}\nPassword: Admin@123\nPlease change this password after first login.\n";
