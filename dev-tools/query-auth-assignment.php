<?php
$c = require __DIR__ . '/../common/config/main-local.php';
$db = $c['components']['db'];
$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$userId = $argv[1] ?? '1';
$sql = "SELECT aa.user_id, aa.item_name role_name, child.child permission_name
FROM auth_assignment aa
LEFT JOIN auth_item_child child ON child.parent = aa.item_name
WHERE aa.user_id = :user_id
  AND (aa.item_name LIKE '%customer%' OR child.child LIKE '%customer%')
ORDER BY aa.item_name, child.child";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $userId]);
foreach ($stmt->fetchAll() as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
