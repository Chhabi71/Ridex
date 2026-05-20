<?php
/**
 * Purpose: GPS tracking helper functions for admin live map, route history,
 * distance calculation, simulated movement, and risk management.
 */

if (!function_exists('ridex_gps_json_response')) {
    function ridex_gps_json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        exit;
    }
}

if (!function_exists('ridex_gps_require_admin')) {
    function ridex_gps_require_admin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionUser = $_SESSION['auth_user'] ?? [];
        $isAdmin = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');
        if (!$isAdmin) {
            ridex_gps_json_response([
                'ok' => false,
                'message' => 'Unauthorized. Please log in as admin.',
            ], 401);
        }
    }
}

if (!function_exists('ridex_gps_ensure_tables')) {
    function ridex_gps_ensure_tables(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS trip_tracking_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                vehicle_id INT NOT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ended_at DATETIME NULL,
                start_latitude DECIMAL(9,6) NULL,
                start_longitude DECIMAL(9,6) NULL,
                end_latitude DECIMAL(9,6) NULL,
                end_longitude DECIMAL(9,6) NULL,
                total_distance_km DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                risk_status ENUM("low","medium","high") NOT NULL DEFAULT "low",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_trip_tracking_booking (booking_id),
                INDEX idx_trip_tracking_vehicle (vehicle_id),
                CONSTRAINT fk_trip_tracking_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                CONSTRAINT fk_trip_tracking_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS gps_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NULL,
                vehicle_id INT NOT NULL,
                alert_type ENUM("gps_lost","route_deviation","overdue_trip","outside_zone","manual_emergency") NOT NULL,
                severity ENUM("low","medium","high") NOT NULL DEFAULT "medium",
                message VARCHAR(255) NOT NULL,
                is_resolved TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                INDEX idx_gps_alert_vehicle_resolved (vehicle_id, is_resolved),
                INDEX idx_gps_alert_booking (booking_id),
                CONSTRAINT fk_gps_alert_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
                CONSTRAINT fk_gps_alert_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }
}

if (!function_exists('ridex_gps_demo_routes')) {
    function ridex_gps_demo_routes(): array
    {
        return [
            'airport_east' => [
                'label' => 'Airport → Baneshwor → Koteshwor Route',
                'points' => [
                    ['name' => 'Tribhuvan International Airport', 'lat' => 27.700769, 'lng' => 85.359203, 'heading' => 250],
                    ['name' => 'Sinamangal', 'lat' => 27.699159, 'lng' => 85.350850, 'heading' => 255],
                    ['name' => 'New Baneshwor', 'lat' => 27.690590, 'lng' => 85.334198, 'heading' => 270],
                    ['name' => 'Tinkune', 'lat' => 27.685200, 'lng' => 85.345900, 'heading' => 95],
                    ['name' => 'Koteshwor', 'lat' => 27.677273, 'lng' => 85.349059, 'heading' => 120],
                ],
            ],
            'thamel_north' => [
                'label' => 'Thamel → Lazimpat → Baluwatar Route',
                'points' => [
                    ['name' => 'Thamel', 'lat' => 27.715390, 'lng' => 85.312330, 'heading' => 35],
                    ['name' => 'Lazimpat', 'lat' => 27.721500, 'lng' => 85.320600, 'heading' => 45],
                    ['name' => 'Baluwatar', 'lat' => 27.733600, 'lng' => 85.331000, 'heading' => 60],
                    ['name' => 'Maharajgunj', 'lat' => 27.739900, 'lng' => 85.337300, 'heading' => 70],
                    ['name' => 'Narayan Gopal Chowk', 'lat' => 27.738100, 'lng' => 85.345400, 'heading' => 100],
                ],
            ],
            'patan_south' => [
                'label' => 'Patan → Jawalakhel → Ekantakuna Route',
                'points' => [
                    ['name' => 'Patan Durbar Square', 'lat' => 27.673600, 'lng' => 85.325800, 'heading' => 245],
                    ['name' => 'Pulchowk', 'lat' => 27.678800, 'lng' => 85.315600, 'heading' => 250],
                    ['name' => 'Jawalakhel', 'lat' => 27.676600, 'lng' => 85.313000, 'heading' => 220],
                    ['name' => 'Ekantakuna', 'lat' => 27.660900, 'lng' => 85.301400, 'heading' => 210],
                    ['name' => 'Satdobato', 'lat' => 27.650900, 'lng' => 85.325600, 'heading' => 105],
                ],
            ],
            'bhaktapur_east' => [
                'label' => 'Bhaktapur → Sallaghari → Suryabinayak Route',
                'points' => [
                    ['name' => 'Koteshwor', 'lat' => 27.677273, 'lng' => 85.349059, 'heading' => 80],
                    ['name' => 'Lokanthali', 'lat' => 27.674600, 'lng' => 85.367500, 'heading' => 82],
                    ['name' => 'Sallaghari', 'lat' => 27.672482, 'lng' => 85.410200, 'heading' => 88],
                    ['name' => 'Bhaktapur Durbar Area', 'lat' => 27.671000, 'lng' => 85.429800, 'heading' => 92],
                    ['name' => 'Suryabinayak', 'lat' => 27.665600, 'lng' => 85.438900, 'heading' => 110],
                ],
            ],
            'naxal_west' => [
                'label' => 'Naxal → Durbar Marg → Kalanki Route',
                'points' => [
                    ['name' => 'Naxal', 'lat' => 27.717245, 'lng' => 85.323959, 'heading' => 230],
                    ['name' => 'Durbar Marg', 'lat' => 27.712800, 'lng' => 85.318900, 'heading' => 245],
                    ['name' => 'Tripureshwor', 'lat' => 27.695500, 'lng' => 85.313800, 'heading' => 230],
                    ['name' => 'Kalimati', 'lat' => 27.695000, 'lng' => 85.298300, 'heading' => 260],
                    ['name' => 'Kalanki', 'lat' => 27.693500, 'lng' => 85.281900, 'heading' => 275],
                ],
            ],
        ];
    }
}


if (!function_exists('ridex_gps_normalize_location')) {
    function ridex_gps_normalize_location(string $location): string
    {
        $value = strtolower(trim($location));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim($value);
    }
}

if (!function_exists('ridex_gps_known_locations')) {
    function ridex_gps_known_locations(): array
    {
        return [
            'tia' => ['name' => 'Tribhuvan International Airport', 'lat' => 27.700769, 'lng' => 85.359203],
            'airport' => ['name' => 'Tribhuvan International Airport', 'lat' => 27.700769, 'lng' => 85.359203],
            'tribhuvan international airport' => ['name' => 'Tribhuvan International Airport', 'lat' => 27.700769, 'lng' => 85.359203],
            'thamel' => ['name' => 'Thamel', 'lat' => 27.715390, 'lng' => 85.312330],
            'lazimpat' => ['name' => 'Lazimpat', 'lat' => 27.721500, 'lng' => 85.320600],
            'baluwatar' => ['name' => 'Baluwatar', 'lat' => 27.733600, 'lng' => 85.331000],
            'maharajgunj' => ['name' => 'Maharajgunj', 'lat' => 27.739900, 'lng' => 85.337300],
            'naxal' => ['name' => 'Naxal', 'lat' => 27.717245, 'lng' => 85.323959],
            'durbar marg' => ['name' => 'Durbar Marg', 'lat' => 27.712800, 'lng' => 85.318900],
            'baneshwor' => ['name' => 'New Baneshwor', 'lat' => 27.690590, 'lng' => 85.334198],
            'new baneshwor' => ['name' => 'New Baneshwor', 'lat' => 27.690590, 'lng' => 85.334198],
            'koteshwor' => ['name' => 'Koteshwor', 'lat' => 27.677273, 'lng' => 85.349059],
            'tinkune' => ['name' => 'Tinkune', 'lat' => 27.685200, 'lng' => 85.345900],
            'patan' => ['name' => 'Patan Durbar Square', 'lat' => 27.673600, 'lng' => 85.325800],
            'pulchowk' => ['name' => 'Pulchowk', 'lat' => 27.678800, 'lng' => 85.315600],
            'jawalakhel' => ['name' => 'Jawalakhel', 'lat' => 27.676600, 'lng' => 85.313000],
            'ekantakuna' => ['name' => 'Ekantakuna', 'lat' => 27.660900, 'lng' => 85.301400],
            'satdobato' => ['name' => 'Satdobato', 'lat' => 27.650900, 'lng' => 85.325600],
            'bhaktapur' => ['name' => 'Bhaktapur Durbar Area', 'lat' => 27.671000, 'lng' => 85.429800],
            'sallaghari' => ['name' => 'Sallaghari', 'lat' => 27.672482, 'lng' => 85.410200],
            'suryabinayak' => ['name' => 'Suryabinayak', 'lat' => 27.665600, 'lng' => 85.438900],
            'tripureshwor' => ['name' => 'Tripureshwor', 'lat' => 27.695500, 'lng' => 85.313800],
            'kalanki' => ['name' => 'Kalanki', 'lat' => 27.693500, 'lng' => 85.281900],
            'kalimati' => ['name' => 'Kalimati', 'lat' => 27.695000, 'lng' => 85.298300],
        ];
    }
}

if (!function_exists('ridex_gps_location_coordinates')) {
    function ridex_gps_location_coordinates(string $location): ?array
    {
        $normalized = ridex_gps_normalize_location($location);
        if ($normalized === '') {
            return null;
        }

        foreach (ridex_gps_known_locations() as $keyword => $coords) {
            if ($normalized === $keyword || str_contains($normalized, $keyword) || str_contains($keyword, $normalized)) {
                return $coords;
            }
        }

        return null;
    }
}

if (!function_exists('ridex_gps_route_from_booking')) {
    function ridex_gps_route_from_booking(array $bookingRow, int $vehicleId): array
    {
        $pickupLocation = (string) ($bookingRow['pickup_location'] ?? '');
        $returnLocation = (string) ($bookingRow['return_location'] ?? '');
        $pickup = ridex_gps_location_coordinates($pickupLocation);
        $return = ridex_gps_location_coordinates($returnLocation);
        $fallback = ridex_gps_demo_route_for_vehicle($vehicleId);
        $fallbackPoints = $fallback['points'] ?? [];

        if (!$pickup) {
            return $fallback;
        }

        $points = [[
            'name' => 'Pickup: ' . ($pickup['name'] ?? $pickupLocation),
            'lat' => (float) $pickup['lat'],
            'lng' => (float) $pickup['lng'],
            'heading' => 45,
        ]];

        if ($return) {
            $startLat = (float) $pickup['lat'];
            $startLng = (float) $pickup['lng'];
            $endLat = (float) $return['lat'];
            $endLng = (float) $return['lng'];
            $latDiff = $endLat - $startLat;
            $lngDiff = $endLng - $startLng;
            $offset = 0.004 + (($vehicleId % 4) * 0.0015);

            $points[] = [
                'name' => 'Route checkpoint 1',
                'lat' => round($startLat + ($latDiff * 0.33) + $offset, 6),
                'lng' => round($startLng + ($lngDiff * 0.33) - $offset, 6),
                'heading' => 80,
            ];
            $points[] = [
                'name' => 'Route checkpoint 2',
                'lat' => round($startLat + ($latDiff * 0.66) - ($offset / 2), 6),
                'lng' => round($startLng + ($lngDiff * 0.66) + ($offset / 2), 6),
                'heading' => 110,
            ];
            $points[] = [
                'name' => 'Return area: ' . ($return['name'] ?? $returnLocation),
                'lat' => $endLat,
                'lng' => $endLng,
                'heading' => 140,
            ];
        } else {
            $offsets = [
                [0.006, 0.004, 40],
                [0.011, -0.005, 85],
                [0.016, 0.003, 120],
                [0.020, 0.009, 150],
            ];
            foreach ($offsets as $index => $offset) {
                $points[] = [
                    'name' => 'Demo checkpoint ' . ($index + 1),
                    'lat' => round(((float) $pickup['lat']) + $offset[0], 6),
                    'lng' => round(((float) $pickup['lng']) + $offset[1], 6),
                    'heading' => $offset[2],
                ];
            }
        }

        return [
            'label' => 'Pickup-based route from ' . ($pickup['name'] ?? $pickupLocation),
            'points' => $points,
        ];
    }
}

if (!function_exists('ridex_gps_route_key_for_vehicle')) {
    function ridex_gps_route_key_for_vehicle(int $vehicleId): string
    {
        $keys = array_keys(ridex_gps_demo_routes());
        if ($vehicleId <= 0 || count($keys) === 0) {
            return 'airport_east';
        }

        return $keys[($vehicleId - 1) % count($keys)];
    }
}

if (!function_exists('ridex_gps_demo_route_for_vehicle')) {
    function ridex_gps_demo_route_for_vehicle(int $vehicleId): array
    {
        $routes = ridex_gps_demo_routes();
        $key = ridex_gps_route_key_for_vehicle($vehicleId);
        return $routes[$key] ?? $routes['airport_east'];
    }
}

if (!function_exists('ridex_gps_demo_route_points')) {
    function ridex_gps_demo_route_points(int $vehicleId = 0): array
    {
        $route = $vehicleId > 0 ? ridex_gps_demo_route_for_vehicle($vehicleId) : (ridex_gps_demo_routes()['airport_east'] ?? []);
        return $route['points'] ?? [];
    }
}

if (!function_exists('ridex_gps_haversine_km')) {
    function ridex_gps_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }
}

if (!function_exists('ridex_gps_fetch_route')) {
    function ridex_gps_fetch_route(PDO $pdo, int $vehicleId, int $limit = 80): array
    {
        if ($vehicleId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, vehicle_id, timestamp, latitude, longitude, heading
             FROM gps_logs
             WHERE vehicle_id = :vehicle_id
               AND ABS(latitude) > 0.00001
               AND ABS(longitude) > 0.00001
             ORDER BY timestamp ASC, id ASC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute(['vehicle_id' => $vehicleId]);

        $route = [];
        foreach (($stmt->fetchAll() ?: []) as $row) {
            $route[] = [
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'time' => (string) $row['timestamp'],
                'heading' => $row['heading'] !== null ? (float) $row['heading'] : null,
            ];
        }

        return $route;
    }
}

if (!function_exists('ridex_gps_route_distance_km')) {
    function ridex_gps_route_distance_km(array $route): float
    {
        $distance = 0.0;
        for ($i = 1; $i < count($route); $i++) {
            $previous = $route[$i - 1];
            $current = $route[$i];
            $distance += ridex_gps_haversine_km(
                (float) $previous['lat'],
                (float) $previous['lng'],
                (float) $current['lat'],
                (float) $current['lng']
            );
        }

        return round($distance, 2);
    }
}

if (!function_exists('ridex_gps_format_datetime')) {
    function ridex_gps_format_datetime($raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return 'Unavailable';
        }

        try {
            return (new DateTimeImmutable($value))->format('M d, Y h:i A');
        } catch (Throwable $exception) {
            return $value;
        }
    }
}

if (!function_exists('ridex_gps_calculate_risk')) {
    function ridex_gps_calculate_risk(array $row, bool $hasGpsSignal, ?DateTimeImmutable $latestGpsTime): array
    {
        $bookingStatus = strtolower(trim((string) ($row['booking_status'] ?? $row['status'] ?? 'reserved')));
        $gpsId = trim((string) ($row['gps_id'] ?? ''));
        $returnDateTimeRaw = trim((string) ($row['return_datetime'] ?? ''));
        $now = new DateTimeImmutable('now');

        if ($bookingStatus === 'overdue') {
            return [
                'level' => 'high',
                'label' => 'High Risk',
                'reason' => 'Booking is overdue. Vehicle should be monitored closely.',
            ];
        }

        if ($returnDateTimeRaw !== '') {
            try {
                $returnDateTime = new DateTimeImmutable($returnDateTimeRaw);
                if ($returnDateTime < $now && !in_array($bookingStatus, ['completed', 'cancelled'], true)) {
                    return [
                        'level' => 'high',
                        'label' => 'High Risk',
                        'reason' => 'Return deadline has passed while trip is still active.',
                    ];
                }
            } catch (Throwable $exception) {
                // Continue with GPS-based checks.
            }
        }

        if ($gpsId === '') {
            return [
                'level' => 'medium',
                'label' => 'Medium Risk',
                'reason' => 'Vehicle does not have a GPS ID assigned.',
            ];
        }

        if (!$hasGpsSignal) {
            return [
                'level' => 'medium',
                'label' => 'Medium Risk',
                'reason' => 'No valid GPS coordinate is available for this vehicle.',
            ];
        }

        if ($latestGpsTime instanceof DateTimeImmutable) {
            $minutesOld = (int) floor(($now->getTimestamp() - $latestGpsTime->getTimestamp()) / 60);
            if ($minutesOld > 30) {
                return [
                    'level' => 'medium',
                    'label' => 'Medium Risk',
                    'reason' => 'GPS has not updated for more than 30 minutes.',
                ];
            }
        }

        return [
            'level' => 'low',
            'label' => 'Low Risk',
            'reason' => 'GPS is active and no suspicious risk rule was triggered.',
        ];
    }
}


if (!function_exists('ridex_gps_alert_type_for_risk')) {
    function ridex_gps_alert_type_for_risk(array $risk): string
    {
        $reason = strtolower((string) ($risk['reason'] ?? ''));
        if (str_contains($reason, 'overdue') || str_contains($reason, 'deadline')) {
            return 'overdue_trip';
        }
        if (str_contains($reason, 'gps')) {
            return 'gps_lost';
        }
        return 'route_deviation';
    }
}

if (!function_exists('ridex_gps_sync_alert')) {
    function ridex_gps_sync_alert(PDO $pdo, int $bookingId, int $vehicleId, array $risk): void
    {
        if ($bookingId <= 0 || $vehicleId <= 0) {
            return;
        }

        $level = (string) ($risk['level'] ?? 'low');
        if ($level === 'low') {
            $resolveStmt = $pdo->prepare(
                'UPDATE gps_alerts
                 SET is_resolved = 1, resolved_at = NOW()
                 WHERE booking_id = :booking_id
                   AND vehicle_id = :vehicle_id
                   AND is_resolved = 0'
            );
            $resolveStmt->execute([
                'booking_id' => $bookingId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        $alertType = ridex_gps_alert_type_for_risk($risk);
        $message = trim((string) ($risk['reason'] ?? 'GPS risk detected.'));
        if ($message === '') {
            $message = 'GPS risk detected.';
        }

        $checkStmt = $pdo->prepare(
            'SELECT id
             FROM gps_alerts
             WHERE booking_id = :booking_id
               AND vehicle_id = :vehicle_id
               AND alert_type = :alert_type
               AND is_resolved = 0
             LIMIT 1'
        );
        $checkStmt->execute([
            'booking_id' => $bookingId,
            'vehicle_id' => $vehicleId,
            'alert_type' => $alertType,
        ]);
        $existingId = (int) ($checkStmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $updateStmt = $pdo->prepare(
                'UPDATE gps_alerts
                 SET severity = :severity, message = :message
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'severity' => in_array($level, ['low', 'medium', 'high'], true) ? $level : 'medium',
                'message' => $message,
                'id' => $existingId,
            ]);
            return;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO gps_alerts (booking_id, vehicle_id, alert_type, severity, message, is_resolved)
             VALUES (:booking_id, :vehicle_id, :alert_type, :severity, :message, 0)'
        );
        $insertStmt->execute([
            'booking_id' => $bookingId,
            'vehicle_id' => $vehicleId,
            'alert_type' => $alertType,
            'severity' => in_array($level, ['low', 'medium', 'high'], true) ? $level : 'medium',
            'message' => $message,
        ]);
    }
}

if (!function_exists('ridex_gps_fetch_live_payload')) {
    function ridex_gps_fetch_live_payload(PDO $pdo): array
    {
        ridex_gps_ensure_tables($pdo);

        $payload = [
            'ok' => true,
            'generatedAt' => gmdate(DATE_ATOM),
            'kpis' => [
                'activeTrips' => 0,
                'gpsOnline' => 0,
                'gpsLost' => 0,
                'highRisk' => 0,
                'totalDistanceKm' => 0,
            ],
            'vehicles' => [],
        ];

        $stmt = $pdo->query(
            'SELECT
                b.id AS booking_id,
                b.booking_number,
                b.status AS booking_status,
                b.pickup_location,
                b.return_location,
                b.pickup_datetime,
                b.return_datetime,
                u.name AS customer_name,
                u.phone AS customer_phone,
                v.id AS vehicle_id,
                v.short_name,
                v.full_name,
                v.vehicle_type,
                v.license_plate,
                v.gps_id,
                latest_gps.latitude AS gps_latitude,
                latest_gps.longitude AS gps_longitude,
                latest_gps.heading AS gps_heading,
                latest_gps.timestamp AS gps_timestamp
             FROM bookings b
             INNER JOIN vehicles v ON v.id = b.vehicle_id
             LEFT JOIN users u ON u.id = b.user_id
             LEFT JOIN gps_logs latest_gps ON latest_gps.id = (
                SELECT g1.id
                FROM gps_logs g1
                WHERE g1.vehicle_id = b.vehicle_id
                  AND ABS(g1.latitude) > 0.00001
                  AND ABS(g1.longitude) > 0.00001
                ORDER BY g1.timestamp DESC, g1.id DESC
                LIMIT 1
             )
             WHERE b.status IN ("reserved", "on_trip", "overdue")
             ORDER BY
                CASE b.status WHEN "overdue" THEN 0 WHEN "on_trip" THEN 1 ELSE 2 END ASC,
                b.return_datetime ASC,
                b.id DESC'
        );

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as $row) {
            $vehicleId = (int) ($row['vehicle_id'] ?? 0);
            if ($vehicleId <= 0) {
                continue;
            }

            $lat = is_numeric($row['gps_latitude'] ?? null) ? (float) $row['gps_latitude'] : null;
            $lng = is_numeric($row['gps_longitude'] ?? null) ? (float) $row['gps_longitude'] : null;
            $hasGpsSignal = $lat !== null && $lng !== null && (abs($lat) > 0.00001 || abs($lng) > 0.00001);

            $latestGpsTime = null;
            if (trim((string) ($row['gps_timestamp'] ?? '')) !== '') {
                try {
                    $latestGpsTime = new DateTimeImmutable((string) $row['gps_timestamp']);
                } catch (Throwable $exception) {
                    $latestGpsTime = null;
                }
            }

            $route = ridex_gps_fetch_route($pdo, $vehicleId);
            $distanceKm = ridex_gps_route_distance_km($route);
            $risk = ridex_gps_calculate_risk($row, $hasGpsSignal, $latestGpsTime);
            ridex_gps_sync_alert($pdo, (int) ($row['booking_id'] ?? 0), $vehicleId, $risk);

            $payload['kpis']['activeTrips'] += 1;
            $payload['kpis']['gpsOnline'] += $hasGpsSignal ? 1 : 0;
            $payload['kpis']['gpsLost'] += $hasGpsSignal ? 0 : 1;
            $payload['kpis']['highRisk'] += $risk['level'] === 'high' ? 1 : 0;
            $payload['kpis']['totalDistanceKm'] += $distanceKm;

            $payload['vehicles'][] = [
                'bookingId' => (int) ($row['booking_id'] ?? 0),
                'bookingNumber' => (string) ($row['booking_number'] ?? ''),
                'bookingStatus' => (string) ($row['booking_status'] ?? 'reserved'),
                'pickupLocation' => (string) ($row['pickup_location'] ?? 'Unavailable'),
                'returnLocation' => (string) ($row['return_location'] ?? 'Unavailable'),
                'pickupDateTime' => ridex_gps_format_datetime($row['pickup_datetime'] ?? ''),
                'returnDateTime' => ridex_gps_format_datetime($row['return_datetime'] ?? ''),
                'customerName' => trim((string) ($row['customer_name'] ?? 'Unknown')) ?: 'Unknown',
                'customerPhone' => trim((string) ($row['customer_phone'] ?? 'Unavailable')) ?: 'Unavailable',
                'vehicleId' => $vehicleId,
                'vehicleName' => trim((string) ($row['full_name'] ?? '')) ?: (trim((string) ($row['short_name'] ?? 'Vehicle')) ?: 'Vehicle'),
                'vehicleType' => (string) ($row['vehicle_type'] ?? ''),
                'licensePlate' => (string) ($row['license_plate'] ?? 'Unavailable'),
                'gpsId' => (string) ($row['gps_id'] ?? ''),
                'demoRouteName' => (string) ((ridex_gps_route_from_booking($row, $vehicleId)['label'] ?? 'Demo GPS Route')),
                'lat' => $hasGpsSignal ? $lat : null,
                'lng' => $hasGpsSignal ? $lng : null,
                'heading' => is_numeric($row['gps_heading'] ?? null) ? (float) $row['gps_heading'] : null,
                'lastUpdated' => ridex_gps_format_datetime($row['gps_timestamp'] ?? ''),
                'hasGpsSignal' => $hasGpsSignal,
                'route' => $route,
                'distanceKm' => $distanceKm,
                'risk' => $risk,
            ];
        }

        $payload['kpis']['totalDistanceKm'] = round((float) $payload['kpis']['totalDistanceKm'], 2);

        return $payload;
    }
}

if (!function_exists('ridex_gps_simulate_next_location')) {
    function ridex_gps_simulate_next_location(PDO $pdo, int $vehicleId): array
    {
        ridex_gps_ensure_tables($pdo);

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException('Invalid vehicle selected.');
        }

        $vehicleStmt = $pdo->prepare('SELECT id, full_name, short_name FROM vehicles WHERE id = :id LIMIT 1');
        $vehicleStmt->execute(['id' => $vehicleId]);
        $vehicle = $vehicleStmt->fetch();
        if (!$vehicle) {
            throw new RuntimeException('Vehicle not found.');
        }

        $bookingStmt = $pdo->prepare(
            'SELECT id AS booking_id, pickup_location, return_location, pickup_datetime, return_datetime, status AS booking_status
             FROM bookings
             WHERE vehicle_id = :vehicle_id
               AND status IN ("reserved", "on_trip", "overdue")
             ORDER BY
                CASE status WHEN "overdue" THEN 0 WHEN "on_trip" THEN 1 ELSE 2 END ASC,
                return_datetime ASC,
                id DESC
             LIMIT 1'
        );
        $bookingStmt->execute(['vehicle_id' => $vehicleId]);
        $bookingRow = $bookingStmt->fetch() ?: [];

        $demoRoute = ridex_gps_route_from_booking(is_array($bookingRow) ? $bookingRow : [], $vehicleId);
        $route = $demoRoute['points'] ?? [];
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM gps_logs
             WHERE vehicle_id = :vehicle_id
               AND ABS(latitude) > 0.00001
               AND ABS(longitude) > 0.00001'
        );
        $countStmt->execute(['vehicle_id' => $vehicleId]);
        $validPointCount = (int) $countStmt->fetchColumn();
        if (count($route) === 0) {
            throw new RuntimeException('No demo GPS route points are configured.');
        }

        $nextIndex = $validPointCount % count($route);
        $point = $route[$nextIndex];

        $insertStmt = $pdo->prepare(
            'INSERT INTO gps_logs (vehicle_id, timestamp, latitude, longitude, heading)
             VALUES (:vehicle_id, NOW(), :latitude, :longitude, :heading)'
        );
        $insertStmt->execute([
            'vehicle_id' => $vehicleId,
            'latitude' => $point['lat'],
            'longitude' => $point['lng'],
            'heading' => $point['heading'],
        ]);

        if (is_array($bookingRow) && !empty($bookingRow['booking_id'])) {
            $latestGpsTime = new DateTimeImmutable('now');
            $risk = ridex_gps_calculate_risk($bookingRow, true, $latestGpsTime);
            ridex_gps_sync_alert($pdo, (int) $bookingRow['booking_id'], $vehicleId, $risk);
        }

        return [
            'vehicleId' => $vehicleId,
            'vehicleName' => trim((string) ($vehicle['full_name'] ?? '')) ?: (string) ($vehicle['short_name'] ?? 'Vehicle'),
            'routeName' => (string) ($demoRoute['label'] ?? 'Demo GPS Route'),
            'pointName' => $point['name'],
            'lat' => $point['lat'],
            'lng' => $point['lng'],
        ];
    }
}
