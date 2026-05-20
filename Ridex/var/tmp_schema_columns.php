<?php
require __DIR__ . '/config/database.php';
$pdo = db();
$sql = "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','categories','vehicles','bookings','payments','gps_logs','vehicle_maintenance_records') ORDER BY TABLE_NAME,ORDINAL_POSITION";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$current = '';
foreach ($rows as $r) {
  if ($current !== $r['TABLE_NAME']) {
    $current = $r['TABLE_NAME'];
    echo PHP_EOL . '## ' . $current . PHP_EOL;
  }
  echo $r['COLUMN_NAME'] . ' | ' . $r['COLUMN_TYPE'] . ' | null=' . $r['IS_NULLABLE'] . ' | default=' . ($r['COLUMN_DEFAULT'] === null ? 'NULL' : $r['COLUMN_DEFAULT']) . ' | extra=' . $r['EXTRA'] . PHP_EOL;
}
