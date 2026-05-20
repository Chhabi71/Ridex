<?php
/**
 * Purpose: Demo endpoint to simulate the next GPS coordinate for a vehicle.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Helpers/gps.php';

ridex_gps_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ridex_gps_json_response([
        'ok' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$vehicleId = (int) ($payload['vehicle_id'] ?? $payload['vehicleId'] ?? 0);

try {
    $point = ridex_gps_simulate_next_location(db(), $vehicleId);
    ridex_gps_json_response([
        'ok' => true,
        'message' => 'Simulated GPS point added successfully.',
        'point' => $point,
    ]);
} catch (Throwable $exception) {
    error_log('GPS simulation failed: ' . $exception->getMessage());
    ridex_gps_json_response([
        'ok' => false,
        'message' => $exception->getMessage() ?: 'Unable to simulate GPS movement.',
    ], 400);
}
