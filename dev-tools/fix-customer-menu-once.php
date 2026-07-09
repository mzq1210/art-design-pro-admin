<?php
$c = require __DIR__ . '/../common/config/main-local.php';
$db = $c['components']['db'];
$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = "DELETE FROM menu
WHERE parent_id IN (SELECT id FROM (SELECT id FROM menu WHERE name = 'CustomerContact') t)
  AND type = 3
  AND permission IN ('customer.create', 'customer.update', 'customer.delete')";
echo 'deleted=' . $pdo->exec($sql) . PHP_EOL;
