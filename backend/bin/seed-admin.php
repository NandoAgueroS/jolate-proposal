<?php
/**
 * JOLATE 2026 — Admin: bcrypt seeding (uses crypt() $2y$, PHP 5.3 compatible).
 *
 * Uso CLI:
 *   php backend/bin/seed-admin.php <username> <password>
 *
 * HTTP access is denied by backend/.htaccess.
 */

date_default_timezone_set('UTC');

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo "CLI only.\n";
    exit(1);
}

if ($argc < 3) {
    echo "Uso: php seed-admin.php <username> <password>\n";
    exit(1);
}

$user = $argv[1];
$pass = $argv[2];

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    echo "Error: backend/config.php no existe.\n";
    exit(1);
}
$config = require $configPath;
require __DIR__ . '/../registrations.php';

$alphabet = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
$salt = '';
for ($i = 0; $i < 22; $i++) {
    $r = ord(openssl_random_pseudo_bytes(1));
    $salt .= $alphabet[$r % 64];
}
$hash = crypt($pass, '$2y$10$' . $salt);

try {
    $pdo = get_pdo($config);
    $sql = "INSERT INTO `admins` (`username`, `password_hash`) VALUES (:u, :h)"
         . " ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':u' => $user, ':h' => $hash));
    echo "Admin '{$user}' creado/actualizado.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
