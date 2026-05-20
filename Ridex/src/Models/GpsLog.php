<?php
/**
 * Purpose: Lightweight GPS log model for telemetry inserts and route retrieval.
 */

class GpsLog extends BaseModel
{
    public function latestForVehicle(int $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gps_logs
             WHERE vehicle_id = :vehicle_id
               AND ABS(latitude) > 0.00001
               AND ABS(longitude) > 0.00001
             ORDER BY timestamp DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['vehicle_id' => $vehicleId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function routeForVehicle(int $vehicleId, int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gps_logs
             WHERE vehicle_id = :vehicle_id
               AND ABS(latitude) > 0.00001
               AND ABS(longitude) > 0.00001
             ORDER BY timestamp ASC, id ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['vehicle_id' => $vehicleId]);

        return $stmt->fetchAll() ?: [];
    }

    public function insertPoint(int $vehicleId, float $latitude, float $longitude, ?float $heading = null): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gps_logs (vehicle_id, timestamp, latitude, longitude, heading)
             VALUES (:vehicle_id, NOW(), :latitude, :longitude, :heading)'
        );

        return $stmt->execute([
            'vehicle_id' => $vehicleId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'heading' => $heading,
        ]);
    }
}