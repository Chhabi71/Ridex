<?php
require __DIR__ . '/config/database.php';
$pdo = db();
$dbName = DB_NAME;
echo "DB=" . $dbName . PHP_EOL;
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
  echo $t . PHP_EOL;
}
