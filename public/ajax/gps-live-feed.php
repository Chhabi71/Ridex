<?php
/**
 * Purpose: JSON endpoint for admin live GPS map data.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Helpers/gps.php';

ridex_gps_require_admin();

try {
    $payload = ridex_gps_fetch_live_payload(db());
    ridex_gps_json_response($payload);
} catch (Throwable $exception) {
    error_log('GPS live feed failed: ' . $exception->getMessage());
    ridex_gps_json_response([
        'ok' => false,
        'message' => 'Unable to load GPS live tracking data right now.',
    ], 500);
}
