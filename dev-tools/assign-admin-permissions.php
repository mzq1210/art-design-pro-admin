<?php
$c = require __DIR__ . '/../common/config/main-local.php';
$db = $c['components']['db'];
$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$role = $argv[1] ?? 'admin';
$permissions = array_slice($argv, 2);
if (!$permissions) {
    fwrite(STDERR, "Usage: php dev-tools/assign-admin-permissions.php admin permission.one permission.two\n");
    exit(1);
}
$now = time();
$stmt = $pdo->prepare('INSERT IGNORE INTO auth_item_child (parent, child) VALUES (:parent, :child)');
foreach ($permissions as $permission) {
    $stmt->execute([':parent' => $role, ':child' => $permission]);
    echo "assigned {$role} -> {$permission}\n";
}
