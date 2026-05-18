<?php
require __DIR__ . '/config/database.php';
$pdo = db();
$sql = "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','categories','vehicles','bookings','payments','gps_logs','vehicle_maintenance_records') GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE ORDER BY TABLE_NAME, INDEX_NAME";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$current = '';
foreach ($rows as $r) {
  if ($current !== $r['TABLE_NAME']) {
    $current = $r['TABLE_NAME'];
    echo PHP_EOL . '## ' . $current . PHP_EOL;
  }
  echo $r['INDEX_NAME'] . ' | non_unique=' . $r['NON_UNIQUE'] . ' | ' . $r['cols'] . PHP_EOL;
}
