<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/common/config/main-local.php';
$db = $config['components']['db'];
$keyword = $argv[1] ?? '';

$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$sql = <<<'SQL'
SELECT id, parent_id, type, title, name, path, component, permission, visible, sort
FROM menu
WHERE :keyword = ''
   OR title LIKE :likeKeyword
   OR name LIKE :likeKeyword
   OR path LIKE :likeKeyword
   OR permission LIKE :likeKeyword
ORDER BY parent_id, sort, id
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':keyword' => $keyword,
    ':likeKeyword' => '%' . $keyword . '%',
]);

foreach ($stmt->fetchAll() as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
