<?php
header('Content-Type: application/json');

// try database first
try {
    $pdo = new PDO("mysql:host=localhost;dbname=ridex_dbb", 'root', '');
    $stmt = $pdo->query("SELECT lat, lng FROM gps_logs ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row && $row['lat']) {
        echo json_encode($row);
        exit;
    }

} catch(Exception $e) {
    // db not available, use simulated
}

// simulate small movement around Sinamangal
$lat = 27.6942 + (rand(-50, 50) / 10000);
$lng = 85.3423 + (rand(-50, 50) / 10000);

echo json_encode([
    'lat' => $lat,
    'lng' => $lng
]);