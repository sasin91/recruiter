<?php
/**
 * Apply db/schema.sql (and optionally the taxonomy seed) to the database in
 * config/database.php, and optionally create the first admin account.
 *
 * Unlike bin/import-db.sh it drops nothing and ignores the schema's
 * CREATE DATABASE / USE lines, so it works on a hosted database whose name
 * and user the platform chose. Every statement is re-runnable.
 *
 *   php bin/import-db.php                      schema only
 *   php bin/import-db.php --taxonomy           schema + taxonomy seed
 *   ADMIN_PASSWORD=... php bin/import-db.php --admin=jonas
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$options = getopt('', ['taxonomy', 'admin:']);

$databases = [];
require $root . '/config/database.php';
$db = $databases['default'];

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?: '3306', $db['database']),
    $db['user'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$files = ['db/schema.sql'];
if (isset($options['taxonomy'])) {
    $files[] = 'db/taxonomy.sql';
}

foreach ($files as $file) {
    $sql = file_get_contents($root . '/' . $file);
    // The platform owns the database name, so skip the local-dev lines.
    $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);
    $pdo->exec($sql);
    echo "Applied $file to {$db['database']}\n";
}

if (isset($options['admin'])) {
    $username = $options['admin'];
    $password = getenv('ADMIN_PASSWORD') ?: '';

    if (strlen($password) < 8) {
        fwrite(STDERR, "Set ADMIN_PASSWORD (8+ characters) to create the admin account.\n");
        exit(1);
    }

    $exists = $pdo->prepare('SELECT id FROM trongate_administrators WHERE username = ?');
    $exists->execute([$username]);

    if ($exists->fetch()) {
        echo "Admin '$username' already exists; left as is.\n";
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO trongate_users (code, user_level_id) VALUES (?, 1)')
        ->execute([bin2hex(random_bytes(16))]);
    $pdo->prepare('INSERT INTO trongate_administrators (username, password, trongate_user_id, active) VALUES (?, ?, ?, 1)')
        ->execute([$username, password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]), $pdo->lastInsertId()]);
    $pdo->commit();

    echo "Created admin '$username'.\n";
}
