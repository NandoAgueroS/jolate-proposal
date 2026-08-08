<?php
/**
 * JOLATE 2026 — Admin: bcrypt seeding via password_hash().
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

$hash = password_hash($pass, PASSWORD_BCRYPT);

try {
    $pdo = get_pdo($config);
    $sql = "INSERT INTO `admins` (`username`, `password_hash`) VALUES (:u, :h)"
         . " AS new ON DUPLICATE KEY UPDATE `password_hash` = new.password_hash";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => $user, ':h' => $hash]);
    echo "Admin '{$user}' creado/actualizado.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
