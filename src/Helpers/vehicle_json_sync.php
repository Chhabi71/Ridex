<?php
/**
 * Purpose: Bidirectional sync between vehicles table and per-category JSON files.
*/

if (!function_exists('sync_vehicles_json_bidirectional')) {
	function sync_vehicles_json_bidirectional(PDO $pdo, string $jsonDirectory, array $options = []): array
	{
		$allowedTypes = ['cars', 'bikes', 'luxury'];
		$preferJson = array_key_exists('prefer_json', $options) ? !empty($options['prefer_json']) : true;
		$preferDbTimestamps = !empty($options['prefer_db_timestamps']);
		if ($preferDbTimestamps) {
			$preferJson = false;
		}
		$jsonDirectory = rtrim($jsonDirectory, "\\/");

		if (!is_dir($jsonDirectory) && !mkdir($jsonDirectory, 0775, true) && !is_dir($jsonDirectory)) {
			throw new RuntimeException('Unable to create JSON sync directory: ' . $jsonDirectory);
		}

		return vehicle_sync_with_lock(
			$jsonDirectory,
			static function () use ($pdo, $jsonDirectory, $allowedTypes, $preferJson, $preferDbTimestamps): array {
				$categoryIdByType = vehicle_sync_ensure_category_ids($pdo);

				$dbRowsByPlate = [];
				foreach (vehicle_sync_fetch_db_rows($pdo) as $row) {
					$plate = strtoupper(trim((string) ($row['license_plate'] ?? '')));
					if ($plate === '') {
						continue;
					}

					$row['license_plate'] = $plate;
					$dbRowsByPlate[$plate] = $row;
				}

				$jsonSnapshot = vehicle_sync_read_json_snapshot($jsonDirectory, $allowedTypes);
				$jsonRowsByPlate = $jsonSnapshot['rows_by_plate'];
				$jsonRowHashesByPlate = $jsonSnapshot['row_hashes_by_plate'];
				$fileMtimeByType = $jsonSnapshot['file_mtime_by_type'];

				$state = vehicle_sync_read_state($jsonDirectory);
				$resolution = vehicle_sync_resolve_rows(
					$dbRowsByPlate,
					$jsonRowsByPlate,
					$jsonRowHashesByPlate,
					$fileMtimeByType,
					$state,
					$preferJson,
					$preferDbTimestamps
				);

				$resolvedRowsByPlate = $resolution['rows_by_plate'];
				$resolutionStats = $resolution['stats'];

				$dbApply = vehicle_sync_apply_resolved_rows_to_db(
					$pdo,
					$resolvedRowsByPlate,
					$dbRowsByPlate,
					$categoryIdByType
				);

				$latestDbRows = vehicle_sync_fetch_db_rows($pdo);
				$exportedCounts = vehicle_sync_export_db_rows_to_json($latestDbRows, $jsonDirectory, $allowedTypes);

				$latestDbRowsByPlate = [];
				foreach ($latestDbRows as $row) {
					$plate = strtoupper(trim((string) ($row['license_plate'] ?? '')));
					if ($plate === '') {
						continue;
					}

					$row['license_plate'] = $plate;
					$latestDbRowsByPlate[$plate] = $row;
				}

				vehicle_sync_write_state($jsonDirectory, vehicle_sync_build_state($latestDbRowsByPlate));

				return [
					'imported_from_json' => $resolutionStats['upsert_from_json'],
					'applied_from_db' => $resolutionStats['upsert_from_db'],
					'deleted_from_json' => $resolutionStats['delete_from_json'],
					'deleted_from_db' => $resolutionStats['delete_from_db'],
					'db_upserts' => $dbApply['upserted'],
					'db_deletes' => $dbApply['deleted'],
					'exported_counts' => $exportedCounts,
					'prefer_json' => $preferJson,
					'prefer_db_timestamps' => $preferDbTimestamps,
					'json_directory' => $jsonDirectory,
					'synced_at' => date('c'),
				];
			}
		);
	}
}

if (!function_exists('vehicle_sync_ensure_category_ids')) {
	function vehicle_sync_ensure_category_ids(PDO $pdo): array
	{
		$map = [
			'cars' => 'Cars',
			'bikes' => 'Bikes',
			'luxury' => 'Luxury',
		];

		$selectStmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1');
		$insertStmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');

		$ids = [];
		foreach ($map as $type => $name) {
			$selectStmt->execute(['name' => $name]);
			$id = (int) $selectStmt->fetchColumn();

			if ($id <= 0) {
				$insertStmt->execute([
					'name' => $name,
					'description' => ucfirst($type) . ' category',
				]);
				$id = (int) $pdo->lastInsertId();
			}

			$ids[$type] = $id;
		}

		return $ids;
	}
}

if (!function_exists('vehicle_sync_fetch_db_rows')) {
	function vehicle_sync_fetch_db_rows(PDO $pdo): array
	{
		$sql = "
			SELECT
				id,
				category_id,
				vehicle_type,
				short_name,
				full_name,
				price_per_day,
				driver_age_requirement,
				image_path,
				number_of_seats,
				transmission_type,
				fuel_type,
				mileage_km_per_l,
				license_plate,
				status,
				gps_id,
				last_service_date,
				description,
				created_at,
				updated_at
			FROM vehicles
			WHERE vehicle_type IN ('cars', 'bikes', 'luxury')
			ORDER BY updated_at DESC, id DESC
		";

		$stmt = $pdo->query($sql);
		return $stmt->fetchAll() ?: [];
	}
}

if (!function_exists('vehicle_sync_read_json_rows')) {
	function vehicle_sync_read_json_rows(string $jsonDirectory, array $allowedTypes): array
	{
		$snapshot = vehicle_sync_read_json_snapshot($jsonDirectory, $allowedTypes);
		return $snapshot['rows_by_plate'];
	}
}

if (!function_exists('vehicle_sync_read_json_snapshot')) {
	function vehicle_sync_read_json_snapshot(string $jsonDirectory, array $allowedTypes): array
	{
		$rowsByPlate = [];
		$rowHashesByPlate = [];
		$fileMtimeByType = [];

		foreach ($allowedTypes as $defaultType) {
			$filePath = $jsonDirectory . DIRECTORY_SEPARATOR . $defaultType . '.json';
			$fileMtimeByType[$defaultType] = is_file($filePath) ? (int) @filemtime($filePath) : 0;
			if (!is_file($filePath)) {
				continue;
			}

			$json = file_get_contents($filePath);
			if ($json === false || trim($json) === '') {
				continue;
			}

			$decoded = json_decode($json, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new RuntimeException('Invalid JSON in file ' . $filePath . ': ' . json_last_error_msg());
			}

			if (!is_array($decoded)) {
				throw new RuntimeException('Invalid vehicles JSON structure in file ' . $filePath . ': expected object or list.');
			}

			$vehicles = [];
			if (isset($decoded['vehicles']) && is_array($decoded['vehicles'])) {
				$vehicles = $decoded['vehicles'];
			} elseif (array_is_list($decoded)) {
				$vehicles = $decoded;
			} else {
				throw new RuntimeException('Invalid vehicles JSON structure in file ' . $filePath . ': missing vehicles array.');
			}

			foreach ($vehicles as $row) {
				if (!is_array($row)) {
					continue;
				}

				$row = vehicle_sync_normalize_json_vehicle_row($row, $defaultType, $allowedTypes);

				$licensePlate = strtoupper(trim((string) ($row['license_plate'] ?? '')));
				if ($licensePlate === '') {
					continue;
				}

				$vehicleType = strtolower(trim((string) ($row['vehicle_type'] ?? $defaultType)));
				if (!in_array($vehicleType, $allowedTypes, true)) {
					$vehicleType = $defaultType;
				}

				$row['license_plate'] = $licensePlate;
				$row['vehicle_type'] = $vehicleType;
				$rowsByPlate[$licensePlate] = $row;
				$rowHashesByPlate[$licensePlate] = vehicle_sync_row_hash($row);
			}
		}

		return [
			'rows_by_plate' => $rowsByPlate,
			'row_hashes_by_plate' => $rowHashesByPlate,
			'file_mtime_by_type' => $fileMtimeByType,
		];
	}
}

if (!function_exists('vehicle_sync_normalize_json_vehicle_row')) {
	function vehicle_sync_normalize_json_vehicle_row(array $row, string $defaultType, array $allowedTypes): array
	{
		$normalized = $row;

		$canonicalMap = [
			'vehicle_type' => ['vehicle_type', 'vehicleType', 'type'],
			'short_name' => ['short_name', 'shortName', 'name'],
			'full_name' => ['full_name', 'fullName', 'model_name', 'modelName'],
			'price_per_day' => ['price_per_day', 'pricePerDay', 'price'],
			'driver_age_requirement' => ['driver_age_requirement', 'driverAgeRequirement', 'minimum_driver_age', 'minimumDriverAge'],
			'image_path' => ['image_path', 'imagePath', 'image'],
			'number_of_seats' => ['number_of_seats', 'numberOfSeats', 'seats'],
			'transmission_type' => ['transmission_type', 'transmissionType', 'transmission'],
			'fuel_type' => ['fuel_type', 'fuelType', 'fuel'],
			'mileage_km_per_l' => ['mileage_km_per_l', 'mileageKmPerL', 'mileage', 'km_per_l', 'kmPerL'],
			'status' => ['status', 'availability'],
			'gps_id' => ['gps_id', 'gpsId'],
			'last_service_date' => ['last_service_date', 'lastServiceDate'],
			'description' => ['description', 'details'],
			'created_at' => ['created_at', 'createdAt'],
			'updated_at' => ['updated_at', 'updatedAt'],
		];

		foreach ($canonicalMap as $targetKey => $candidateKeys) {
			$value = vehicle_sync_get_row_value_by_alias($row, $candidateKeys);
			if ($value !== null) {
				$normalized[$targetKey] = $value;
			}
		}

		$licensePlate = vehicle_sync_extract_license_plate($row);
		if ($licensePlate !== '') {
			$normalized['license_plate'] = $licensePlate;
		}

		$vehicleType = strtolower(trim((string) ($normalized['vehicle_type'] ?? $defaultType)));
		if (!in_array($vehicleType, $allowedTypes, true)) {
			$vehicleType = $defaultType;
		}
		$normalized['vehicle_type'] = $vehicleType;

		return $normalized;
	}
}

if (!function_exists('vehicle_sync_extract_license_plate')) {
	function vehicle_sync_extract_license_plate(array $row): string
	{
		$value = vehicle_sync_get_row_value_by_alias(
			$row,
			[
				'license_plate',
				'licensePlate',
				'plate',
				'plate_number',
				'plateNumber',
				'vehicle_plate',
				'vehiclePlate',
				'registration_number',
				'registrationNumber',
			]
		);

		return strtoupper(trim((string) ($value ?? '')));
	}
}

if (!function_exists('vehicle_sync_get_row_value_by_alias')) {
	function vehicle_sync_get_row_value_by_alias(array $row, array $candidateKeys): mixed
	{
		$index = [];
		foreach ($row as $key => $value) {
			if (!is_string($key)) {
				continue;
			}

			$index[vehicle_sync_normalize_alias_key($key)] = $value;
		}

		foreach ($candidateKeys as $candidateKey) {
			$normalizedCandidate = vehicle_sync_normalize_alias_key((string) $candidateKey);
			if (array_key_exists($normalizedCandidate, $index)) {
				return $index[$normalizedCandidate];
			}
		}

		return null;
	}
}

if (!function_exists('vehicle_sync_normalize_alias_key')) {
	function vehicle_sync_normalize_alias_key(string $key): string
	{
		$normalized = preg_replace('/[^a-z0-9]+/i', '_', $key);
		$normalized = strtolower(trim((string) $normalized, '_'));

		return $normalized;
	}
}

if (!function_exists('vehicle_sync_with_lock')) {
	function vehicle_sync_with_lock(string $jsonDirectory, callable $callback): mixed
	{
		$lockPath = $jsonDirectory . DIRECTORY_SEPARATOR . '.vehicles-sync.lock';
		$lockHandle = fopen($lockPath, 'c+');
		if ($lockHandle === false) {
			throw new RuntimeException('Unable to open sync lock file: ' . $lockPath);
		}

		if (!flock($lockHandle, LOCK_EX)) {
			fclose($lockHandle);
			throw new RuntimeException('Unable to lock sync file: ' . $lockPath);
		}

		try {
			return $callback();
		} finally {
			flock($lockHandle, LOCK_UN);
			fclose($lockHandle);
		}
	}
}

if (!function_exists('vehicle_sync_state_path')) {
	function vehicle_sync_state_path(string $jsonDirectory): string
	{
		return $jsonDirectory . DIRECTORY_SEPARATOR . '.vehicles-sync-state.json';
	}
}

if (!function_exists('vehicle_sync_read_state')) {
	function vehicle_sync_read_state(string $jsonDirectory): array
	{
		$defaultState = [
			'version' => 1,
			'last_synced_at' => null,
			'plates' => [],
		];

		$statePath = vehicle_sync_state_path($jsonDirectory);
		if (!is_file($statePath)) {
			return $defaultState;
		}

		$raw = file_get_contents($statePath);
		if ($raw === false || trim($raw) === '') {
			return $defaultState;
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return $defaultState;
		}

		$plates = $decoded['plates'] ?? [];
		if (!is_array($plates)) {
			$plates = [];
		}

		return [
			'version' => 1,
			'last_synced_at' => is_scalar($decoded['last_synced_at'] ?? null) ? (string) $decoded['last_synced_at'] : null,
			'plates' => $plates,
		];
	}
}

if (!function_exists('vehicle_sync_write_state')) {
	function vehicle_sync_write_state(string $jsonDirectory, array $state): void
	{
		$statePath = vehicle_sync_state_path($jsonDirectory);
		$tempPath = $statePath . '.tmp';

		$json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new RuntimeException('Unable to encode vehicle sync state.');
		}

		file_put_contents($tempPath, $json . PHP_EOL);
		if (!rename($tempPath, $statePath)) {
			@unlink($tempPath);
			throw new RuntimeException('Unable to write vehicle sync state file: ' . $statePath);
		}
	}
}

if (!function_exists('vehicle_sync_build_state')) {
	function vehicle_sync_build_state(array $rowsByPlate): array
	{
		$plates = [];
		foreach ($rowsByPlate as $licensePlate => $row) {
			$plate = strtoupper(trim((string) $licensePlate));
			if ($plate === '') {
				continue;
			}

			$plates[$plate] = [
				'vehicle_type' => vehicle_sync_pick_enum($row['vehicle_type'] ?? 'cars', ['cars', 'bikes', 'luxury'], 'cars'),
				'db_present' => true,
				'json_present' => true,
				'updated_at' => (string) ($row['updated_at'] ?? ''),
				'row_hash' => vehicle_sync_row_hash($row),
			];
		}

		return [
			'version' => 1,
			'last_synced_at' => date('c'),
			'plates' => $plates,
		];
	}
}

if (!function_exists('vehicle_sync_row_hash')) {
	function vehicle_sync_row_hash(array $row): string
	{
		$copy = $row;
		unset($copy['id'], $copy['created_at'], $copy['updated_at']);
		ksort($copy);

		$encoded = json_encode($copy, JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			$encoded = '';
		}

		return hash('sha256', $encoded);
	}
}

if (!function_exists('vehicle_sync_resolve_rows')) {
	function vehicle_sync_resolve_rows(
		array $dbRowsByPlate,
		array $jsonRowsByPlate,
		array $jsonRowHashesByPlate,
		array $fileMtimeByType,
		array $state,
		bool $preferJson = false,
		bool $preferDbTimestamps = false
	): array {
		$allPlateKeys = array_unique(array_merge(
			array_keys($dbRowsByPlate),
			array_keys($jsonRowsByPlate),
			array_keys($state['plates'] ?? [])
		));

		$resolvedRowsByPlate = [];
		$stats = [
			'upsert_from_json' => 0,
			'upsert_from_db' => 0,
			'delete_from_json' => 0,
			'delete_from_db' => 0,
		];

		$managedJsonTypes = [];
		foreach ($fileMtimeByType as $vehicleType => $fileMtime) {
			if ((int) $fileMtime > 0) {
				$managedJsonTypes[(string) $vehicleType] = true;
			}
		}

		// JSON-first mode: any plate present in a managed JSON file is authoritative for that type.
		if ($preferJson && !$preferDbTimestamps && !empty($managedJsonTypes)) {
			$resolvedRowsByPlate = [];

			foreach ($dbRowsByPlate as $licensePlate => $dbRow) {
				$dbVehicleType = vehicle_sync_pick_enum($dbRow['vehicle_type'] ?? 'cars', ['cars', 'bikes', 'luxury'], 'cars');
				if (!isset($managedJsonTypes[$dbVehicleType])) {
					$resolvedRowsByPlate[$licensePlate] = $dbRow;
					$stats['upsert_from_db']++;
				}
			}

			foreach ($jsonRowsByPlate as $licensePlate => $jsonRow) {
				$resolvedRowsByPlate[$licensePlate] = $jsonRow;
				$stats['upsert_from_json']++;
			}

			foreach ($dbRowsByPlate as $licensePlate => $dbRow) {
				$dbVehicleType = vehicle_sync_pick_enum($dbRow['vehicle_type'] ?? 'cars', ['cars', 'bikes', 'luxury'], 'cars');
				if (!isset($managedJsonTypes[$dbVehicleType])) {
					continue;
				}

				if (!isset($jsonRowsByPlate[$licensePlate])) {
					$stats['delete_from_json']++;
				}
			}

			return [
				'rows_by_plate' => $resolvedRowsByPlate,
				'stats' => $stats,
			];
		}

		$lastSyncedAtTs = vehicle_sync_to_timestamp($state['last_synced_at'] ?? null);
		if ($lastSyncedAtTs <= 0) {
			$lastSyncedAtTs = time();
		}

		foreach ($allPlateKeys as $plateKey) {
			$licensePlate = strtoupper(trim((string) $plateKey));
			if ($licensePlate === '') {
				continue;
			}

			$dbRow = $dbRowsByPlate[$licensePlate] ?? null;
			$jsonRow = $jsonRowsByPlate[$licensePlate] ?? null;
			$statePlate = $state['plates'][$licensePlate] ?? null;
			$stateVehicleType = vehicle_sync_pick_enum(
				is_array($statePlate) ? ($statePlate['vehicle_type'] ?? 'cars') : 'cars',
				['cars', 'bikes', 'luxury'],
				'cars'
			);
			$jsonFileExistsForStateType = (int) ($fileMtimeByType[$stateVehicleType] ?? 0) > 0;

			$events = [];

			if (is_array($dbRow)) {
				$events[] = [
					'kind' => 'upsert',
					'source' => 'db',
					'timestamp' => vehicle_sync_effective_db_timestamp($dbRow, is_array($statePlate) ? $statePlate : null, $lastSyncedAtTs),
					'row' => $dbRow,
				];
			}

			if (is_array($jsonRow)) {
				$jsonHash = $jsonRowHashesByPlate[$licensePlate] ?? vehicle_sync_row_hash($jsonRow);
				$events[] = [
					'kind' => 'upsert',
					'source' => 'json',
					'timestamp' => vehicle_sync_effective_json_timestamp(
						$jsonRow,
						$jsonHash,
						is_array($statePlate) ? $statePlate : null,
						$fileMtimeByType,
						$lastSyncedAtTs
					),
					'row' => $jsonRow,
				];
			}

			if (is_array($statePlate) && !empty($statePlate['json_present']) && $jsonRow === null && $jsonFileExistsForStateType) {
				$events[] = [
					'kind' => 'delete',
					'source' => 'json',
					'timestamp' => vehicle_sync_json_deletion_timestamp($statePlate, $fileMtimeByType, $lastSyncedAtTs),
					'row' => null,
				];
			}

			if (is_array($statePlate) && !empty($statePlate['db_present']) && $dbRow === null) {
				$events[] = [
					'kind' => 'delete',
					'source' => 'db',
					'timestamp' => time(),
					'row' => null,
				];
			}

			if (empty($events)) {
				continue;
			}

			$winner = vehicle_sync_pick_winner_event($events, $preferJson, $preferDbTimestamps);
			if (($winner['kind'] ?? '') === 'delete') {
				if (($winner['source'] ?? '') === 'json') {
					$stats['delete_from_json']++;
				} else {
					$stats['delete_from_db']++;
				}
				continue;
			}

			$resolvedRowsByPlate[$licensePlate] = $winner['row'];
			if (($winner['source'] ?? '') === 'json') {
				$stats['upsert_from_json']++;
			} else {
				$stats['upsert_from_db']++;
			}
		}

		return [
			'rows_by_plate' => $resolvedRowsByPlate,
			'stats' => $stats,
		];
	}
}

if (!function_exists('vehicle_sync_pick_winner_event')) {
	function vehicle_sync_pick_winner_event(array $events, bool $preferJson = false, bool $preferDbTimestamps = false): array
	{
		if ($preferJson) {
			foreach ($events as $event) {
				if (($event['source'] ?? '') === 'json' && ($event['kind'] ?? '') === 'upsert') {
					return $event;
				}
			}

			foreach ($events as $event) {
				if (($event['source'] ?? '') === 'json' && ($event['kind'] ?? '') === 'delete') {
					return $event;
				}
			}
		}

		if ($preferDbTimestamps) {
			foreach ($events as $event) {
				if (($event['source'] ?? '') === 'db' && ($event['kind'] ?? '') === 'upsert') {
					return $event;
				}
			}

			foreach ($events as $event) {
				if (($event['source'] ?? '') === 'db' && ($event['kind'] ?? '') === 'delete') {
					return $event;
				}
			}
		}

		usort(
			$events,
			static function (array $left, array $right): int {
				$leftTs = (int) ($left['timestamp'] ?? 0);
				$rightTs = (int) ($right['timestamp'] ?? 0);
				if ($leftTs !== $rightTs) {
					return $rightTs <=> $leftTs;
				}

				return vehicle_sync_event_priority($right) <=> vehicle_sync_event_priority($left);
			}
		);

		return $events[0];
	}
}

if (!function_exists('vehicle_sync_event_priority')) {
	function vehicle_sync_event_priority(array $event): int
	{
		$kind = (string) ($event['kind'] ?? 'upsert');
		$source = (string) ($event['source'] ?? 'db');

		$kindWeight = $kind === 'delete' ? 20 : 10;
		$sourceWeight = $source === 'json' ? 2 : 1;

		return $kindWeight + $sourceWeight;
	}
}

if (!function_exists('vehicle_sync_effective_json_timestamp')) {
	function vehicle_sync_effective_json_timestamp(array $jsonRow, string $jsonHash, ?array $statePlate, array $fileMtimeByType, int $lastSyncedAtTs): int
	{
		$jsonUpdatedAtTs = vehicle_sync_to_timestamp($jsonRow['updated_at'] ?? null);
		$vehicleType = vehicle_sync_pick_enum(
			$jsonRow['vehicle_type'] ?? ($statePlate['vehicle_type'] ?? 'cars'),
			['cars', 'bikes', 'luxury'],
			'cars'
		);
		$fileMtime = (int) ($fileMtimeByType[$vehicleType] ?? 0);

		if ($jsonUpdatedAtTs <= 0) {
			$jsonUpdatedAtTs = $fileMtime > 0 ? $fileMtime : time();
		}

		if ($statePlate !== null) {
			$previousHash = (string) ($statePlate['row_hash'] ?? '');
			if ($previousHash !== '' && $previousHash !== $jsonHash && $jsonUpdatedAtTs <= $lastSyncedAtTs) {
				$jsonUpdatedAtTs = max($jsonUpdatedAtTs, $fileMtime, $lastSyncedAtTs + 1);
			}
		}

		return $jsonUpdatedAtTs;
	}
}

if (!function_exists('vehicle_sync_effective_db_timestamp')) {
	function vehicle_sync_effective_db_timestamp(array $dbRow, ?array $statePlate, int $lastSyncedAtTs): int
	{
		$dbUpdatedAtTs = vehicle_sync_to_timestamp($dbRow['updated_at'] ?? null);
		if ($dbUpdatedAtTs <= 0) {
			$dbUpdatedAtTs = time();
		}

		if ($statePlate !== null) {
			$previousHash = (string) ($statePlate['row_hash'] ?? '');
			$currentHash = vehicle_sync_row_hash($dbRow);
			if ($previousHash !== '' && $previousHash !== $currentHash && $dbUpdatedAtTs <= $lastSyncedAtTs) {
				$dbUpdatedAtTs = max($dbUpdatedAtTs, $lastSyncedAtTs + 1);
			}
		}

		return $dbUpdatedAtTs;
	}
}

if (!function_exists('vehicle_sync_json_deletion_timestamp')) {
	function vehicle_sync_json_deletion_timestamp(array $statePlate, array $fileMtimeByType, int $lastSyncedAtTs): int
	{
		$vehicleType = vehicle_sync_pick_enum($statePlate['vehicle_type'] ?? 'cars', ['cars', 'bikes', 'luxury'], 'cars');
		$fileMtime = (int) ($fileMtimeByType[$vehicleType] ?? 0);
		if ($fileMtime <= 0) {
			$fileMtime = $lastSyncedAtTs + 1;
		}

		return max($fileMtime, $lastSyncedAtTs + 1);
	}
}

if (!function_exists('vehicle_sync_apply_resolved_rows_to_db')) {
	function vehicle_sync_apply_resolved_rows_to_db(PDO $pdo, array $resolvedRowsByPlate, array $dbRowsByPlate, array $categoryIdByType): array
	{
		$insertSql = "
			INSERT INTO vehicles (
				id,
				category_id,
				vehicle_type,
				short_name,
				full_name,
				price_per_day,
				driver_age_requirement,
				image_path,
				number_of_seats,
				transmission_type,
				fuel_type,
				mileage_km_per_l,
				license_plate,
				status,
				gps_id,
				last_service_date,
				description,
				created_at,
				updated_at
			) VALUES (
				:id,
				:category_id,
				:vehicle_type,
				:short_name,
				:full_name,
				:price_per_day,
				:driver_age_requirement,
				:image_path,
				:number_of_seats,
				:transmission_type,
				:fuel_type,
				:mileage_km_per_l,
				:license_plate,
				:status,
				:gps_id,
				:last_service_date,
				:description,
				:created_at,
				:updated_at
			)
		";

		$updateSql = "
			UPDATE vehicles
			SET
				category_id = :category_id,
				vehicle_type = :vehicle_type,
				short_name = :short_name,
				full_name = :full_name,
				price_per_day = :price_per_day,
				driver_age_requirement = :driver_age_requirement,
				image_path = :image_path,
				number_of_seats = :number_of_seats,
				transmission_type = :transmission_type,
				fuel_type = :fuel_type,
				mileage_km_per_l = :mileage_km_per_l,
				status = :status,
				gps_id = :gps_id,
				last_service_date = :last_service_date,
				description = :description,
				updated_at = :updated_at
			WHERE id = :id
		";

		$insertStmt = $pdo->prepare($insertSql);
		$updateStmt = $pdo->prepare($updateSql);
		$deleteStmt = $pdo->prepare('DELETE FROM vehicles WHERE license_plate = :license_plate');

		$upsertedCount = 0;
		$deletedCount = 0;
		$now = date('Y-m-d H:i:s');

		$usedVehicleIds = [];
		$maxVehicleId = 0;
		foreach ($dbRowsByPlate as $existingRow) {
			$existingId = (int) ($existingRow['id'] ?? 0);
			if ($existingId > 0) {
				$usedVehicleIds[$existingId] = true;
				if ($existingId > $maxVehicleId) {
					$maxVehicleId = $existingId;
				}
			}
		}

		$nextSequentialVehicleId = $maxVehicleId + 1;
		while (isset($usedVehicleIds[$nextSequentialVehicleId])) {
			$nextSequentialVehicleId++;
		}

		$allocateSequentialVehicleId = static function () use (&$usedVehicleIds, &$nextSequentialVehicleId): int {
			while (isset($usedVehicleIds[$nextSequentialVehicleId])) {
				$nextSequentialVehicleId++;
			}

			$allocatedId = $nextSequentialVehicleId;
			$usedVehicleIds[$allocatedId] = true;
			$nextSequentialVehicleId++;

			return $allocatedId;
		};

		$pdo->beginTransaction();
		try {
			foreach ($resolvedRowsByPlate as $licensePlate => $resolvedRow) {
				$dbRow = $dbRowsByPlate[$licensePlate] ?? null;
				$persistentRow = vehicle_sync_build_vehicle_persistable_row(
					$resolvedRow,
					is_array($dbRow) ? $dbRow : null,
					$categoryIdByType,
					$licensePlate
				);

				if (!is_array($dbRow)) {
					$insertStmt->execute(array_merge(
						[
							'id' => $allocateSequentialVehicleId(),
						],
						$persistentRow,
						[
							'created_at' => $now,
							'updated_at' => $now,
						]
					));
					$upsertedCount++;
					continue;
				}

				$existingRow = vehicle_sync_build_vehicle_persistable_row(
					$dbRow,
					$dbRow,
					$categoryIdByType,
					$licensePlate
				);

				if (vehicle_sync_vehicle_rows_match($persistentRow, $existingRow)) {
					continue;
				}

				$updatePayload = $persistentRow;
				unset($updatePayload['license_plate']);

				$updateStmt->execute(array_merge(
					[
						'id' => (int) ($dbRow['id'] ?? 0),
					],
					$updatePayload,
					[
						'updated_at' => $now,
					]
				));
				$upsertedCount++;
			}

			foreach ($dbRowsByPlate as $licensePlate => $_dbRow) {
				if (isset($resolvedRowsByPlate[$licensePlate])) {
					continue;
				}

				$deleteStmt->execute([
					'license_plate' => $licensePlate,
				]);

				if ($deleteStmt->rowCount() > 0) {
					$deletedCount++;
				}
			}

			$pdo->commit();
		} catch (Throwable $exception) {
			$pdo->rollBack();
			throw $exception;
		}

		return [
			'upserted' => $upsertedCount,
			'deleted' => $deletedCount,
		];
	}
}

if (!function_exists('vehicle_sync_build_vehicle_persistable_row')) {
	function vehicle_sync_build_vehicle_persistable_row(array $sourceRow, ?array $dbRow, array $categoryIdByType, string $licensePlate): array
	{
		$vehicleType = vehicle_sync_pick_enum(
			$sourceRow['vehicle_type'] ?? ($dbRow['vehicle_type'] ?? 'cars'),
			['cars', 'bikes', 'luxury'],
			'cars'
		);

		$categoryId = $categoryIdByType[$vehicleType] ?? ($categoryIdByType['cars'] ?? 1);
		$fullName = vehicle_sync_pick_text($sourceRow['full_name'] ?? ($dbRow['full_name'] ?? ''), 'Unknown Vehicle');
		$shortName = vehicle_sync_pick_text($sourceRow['short_name'] ?? ($dbRow['short_name'] ?? ''), $fullName);

		return [
			'category_id' => $categoryId,
			'vehicle_type' => $vehicleType,
			'short_name' => $shortName,
			'full_name' => $fullName,
			'price_per_day' => vehicle_sync_pick_int($sourceRow['price_per_day'] ?? ($dbRow['price_per_day'] ?? 0), 0),
			'driver_age_requirement' => vehicle_sync_pick_int($sourceRow['driver_age_requirement'] ?? ($dbRow['driver_age_requirement'] ?? 18), 18),
			'image_path' => vehicle_sync_pick_nullable_text($sourceRow['image_path'] ?? ($dbRow['image_path'] ?? null)),
			'number_of_seats' => vehicle_sync_pick_nullable_int($sourceRow['number_of_seats'] ?? ($dbRow['number_of_seats'] ?? null)),
			'transmission_type' => vehicle_sync_pick_enum(
				$sourceRow['transmission_type'] ?? ($dbRow['transmission_type'] ?? 'manual'),
				['manual', 'automatic', 'hybrid', 'n/a'],
				'manual'
			),
			'fuel_type' => vehicle_sync_pick_enum(
				$sourceRow['fuel_type'] ?? ($dbRow['fuel_type'] ?? 'petrol'),
				['petrol', 'diesel', 'electric'],
				'petrol'
			),
			'mileage_km_per_l' => vehicle_sync_pick_nullable_float($sourceRow['mileage_km_per_l'] ?? ($dbRow['mileage_km_per_l'] ?? null)),
			'license_plate' => strtoupper(trim((string) $licensePlate)),
			'status' => vehicle_sync_pick_enum(
				$sourceRow['status'] ?? ($dbRow['status'] ?? 'available'),
				['available', 'reserved', 'on_trip', 'overdue', 'maintenance'],
				'available'
			),
			'gps_id' => vehicle_sync_pick_nullable_text($sourceRow['gps_id'] ?? ($dbRow['gps_id'] ?? null)),
			'last_service_date' => vehicle_sync_normalize_date($sourceRow['last_service_date'] ?? ($dbRow['last_service_date'] ?? null)),
			'description' => vehicle_sync_pick_nullable_text($sourceRow['description'] ?? ($dbRow['description'] ?? null)),
		];
	}
}

if (!function_exists('vehicle_sync_vehicle_rows_match')) {
	function vehicle_sync_vehicle_rows_match(array $leftRow, array $rightRow): bool
	{
		$keys = [
			'category_id',
			'vehicle_type',
			'short_name',
			'full_name',
			'price_per_day',
			'driver_age_requirement',
			'image_path',
			'number_of_seats',
			'transmission_type',
			'fuel_type',
			'mileage_km_per_l',
			'license_plate',
			'status',
			'gps_id',
			'last_service_date',
			'description',
		];

		foreach ($keys as $key) {
			if (($leftRow[$key] ?? null) !== ($rightRow[$key] ?? null)) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('vehicle_sync_apply_json_rows_to_db')) {
	function vehicle_sync_apply_json_rows_to_db(PDO $pdo, array $jsonRowsByPlate, array $dbRowsByPlate, array $categoryIdByType, bool $preferJson = false): int
	{
		if (empty($jsonRowsByPlate)) {
			return 0;
		}

		$upsertSql = "
			INSERT INTO vehicles (
				category_id,
				vehicle_type,
				short_name,
				full_name,
				price_per_day,
				driver_age_requirement,
				image_path,
				number_of_seats,
				transmission_type,
				fuel_type,
				mileage_km_per_l,
				license_plate,
				status,
				gps_id,
				last_service_date,
				description,
				created_at,
				updated_at
			) VALUES (
				:category_id,
				:vehicle_type,
				:short_name,
				:full_name,
				:price_per_day,
				:driver_age_requirement,
				:image_path,
				:number_of_seats,
				:transmission_type,
				:fuel_type,
				:mileage_km_per_l,
				:license_plate,
				:status,
				:gps_id,
				:last_service_date,
				:description,
				:created_at,
				:updated_at
			)
			ON DUPLICATE KEY UPDATE
				category_id = VALUES(category_id),
				vehicle_type = VALUES(vehicle_type),
				short_name = VALUES(short_name),
				full_name = VALUES(full_name),
				price_per_day = VALUES(price_per_day),
				driver_age_requirement = VALUES(driver_age_requirement),
				image_path = VALUES(image_path),
				number_of_seats = VALUES(number_of_seats),
				transmission_type = VALUES(transmission_type),
				fuel_type = VALUES(fuel_type),
				mileage_km_per_l = VALUES(mileage_km_per_l),
				status = VALUES(status),
				gps_id = VALUES(gps_id),
				last_service_date = VALUES(last_service_date),
				description = VALUES(description),
				updated_at = VALUES(updated_at)
		";

		$stmt = $pdo->prepare($upsertSql);
		$updatedCount = 0;
		$now = date('Y-m-d H:i:s');

		$pdo->beginTransaction();
		try {
			foreach ($jsonRowsByPlate as $licensePlate => $jsonRow) {
				$dbRow = $dbRowsByPlate[$licensePlate] ?? null;
				if ($dbRow !== null && !$preferJson && !vehicle_sync_is_json_newer($jsonRow, $dbRow)) {
					continue;
				}

				$vehicleType = vehicle_sync_pick_enum(
					$jsonRow['vehicle_type'] ?? ($dbRow['vehicle_type'] ?? 'cars'),
					['cars', 'bikes', 'luxury'],
					'cars'
				);

				$categoryId = $categoryIdByType[$vehicleType] ?? ($categoryIdByType['cars'] ?? 1);
				$fullName = vehicle_sync_pick_text($jsonRow['full_name'] ?? ($dbRow['full_name'] ?? ''), 'Unknown Vehicle');
				$shortName = vehicle_sync_pick_text($jsonRow['short_name'] ?? ($dbRow['short_name'] ?? ''), $fullName);

				$stmt->execute([
					'category_id' => $categoryId,
					'vehicle_type' => $vehicleType,
					'short_name' => $shortName,
					'full_name' => $fullName,
					'price_per_day' => vehicle_sync_pick_int($jsonRow['price_per_day'] ?? ($dbRow['price_per_day'] ?? 0), 0),
					'driver_age_requirement' => vehicle_sync_pick_int($jsonRow['driver_age_requirement'] ?? ($dbRow['driver_age_requirement'] ?? 18), 18),
					'image_path' => vehicle_sync_pick_nullable_text($jsonRow['image_path'] ?? ($dbRow['image_path'] ?? null)),
					'number_of_seats' => vehicle_sync_pick_nullable_int($jsonRow['number_of_seats'] ?? ($dbRow['number_of_seats'] ?? null)),
					'transmission_type' => vehicle_sync_pick_enum(
						$jsonRow['transmission_type'] ?? ($dbRow['transmission_type'] ?? 'manual'),
						['manual', 'automatic', 'hybrid', 'n/a'],
						'manual'
					),
					'fuel_type' => vehicle_sync_pick_enum(
						$jsonRow['fuel_type'] ?? ($dbRow['fuel_type'] ?? 'petrol'),
						['petrol', 'diesel', 'electric'],
						'petrol'
					),
					'mileage_km_per_l' => vehicle_sync_pick_nullable_float($jsonRow['mileage_km_per_l'] ?? ($dbRow['mileage_km_per_l'] ?? null)),
					'license_plate' => $licensePlate,
					'status' => vehicle_sync_pick_enum(
						$jsonRow['status'] ?? ($dbRow['status'] ?? 'available'),
						['available', 'reserved', 'on_trip', 'overdue', 'maintenance'],
						'available'
					),
					'gps_id' => vehicle_sync_pick_nullable_text($jsonRow['gps_id'] ?? ($dbRow['gps_id'] ?? null)),
					'last_service_date' => vehicle_sync_normalize_date($jsonRow['last_service_date'] ?? ($dbRow['last_service_date'] ?? null)),
					'description' => vehicle_sync_pick_nullable_text($jsonRow['description'] ?? ($dbRow['description'] ?? null)),
					'created_at' => vehicle_sync_normalize_datetime(
						$jsonRow['created_at'] ?? ($dbRow['created_at'] ?? $now),
						$dbRow['created_at'] ?? $now
					),
					'updated_at' => vehicle_sync_normalize_datetime($jsonRow['updated_at'] ?? $now, $now),
				]);

				$updatedCount++;
			}

			$pdo->commit();
		} catch (Throwable $exception) {
			$pdo->rollBack();
			throw $exception;
		}

		return $updatedCount;
	}
}

if (!function_exists('vehicle_sync_export_db_rows_to_json')) {
	function vehicle_sync_export_db_rows_to_json(array $dbRows, string $jsonDirectory, array $allowedTypes): array
	{
		$grouped = [
			'cars' => [],
			'bikes' => [],
			'luxury' => [],
		];

		foreach ($dbRows as $row) {
			$vehicleType = strtolower((string) ($row['vehicle_type'] ?? 'cars'));
			if (!isset($grouped[$vehicleType])) {
				continue;
			}

			$grouped[$vehicleType][] = [
				'id' => (int) ($row['id'] ?? 0),
				'category_id' => (int) ($row['category_id'] ?? 0),
				'vehicle_type' => $vehicleType,
				'short_name' => (string) ($row['short_name'] ?? ''),
				'full_name' => (string) ($row['full_name'] ?? ''),
				'price_per_day' => (int) ($row['price_per_day'] ?? 0),
				'driver_age_requirement' => (int) ($row['driver_age_requirement'] ?? 0),
				'image_path' => (string) ($row['image_path'] ?? ''),
				'number_of_seats' => isset($row['number_of_seats']) ? (int) $row['number_of_seats'] : null,
				'transmission_type' => (string) ($row['transmission_type'] ?? ''),
				'fuel_type' => (string) ($row['fuel_type'] ?? ''),
				'mileage_km_per_l' => isset($row['mileage_km_per_l']) ? (float) $row['mileage_km_per_l'] : null,
				'license_plate' => (string) ($row['license_plate'] ?? ''),
				'status' => (string) ($row['status'] ?? ''),
				'gps_id' => $row['gps_id'] !== null ? (string) $row['gps_id'] : null,
				'last_service_date' => $row['last_service_date'] !== null ? (string) $row['last_service_date'] : null,
				'description' => $row['description'] !== null ? (string) $row['description'] : null,
				'created_at' => (string) ($row['created_at'] ?? ''),
				'updated_at' => (string) ($row['updated_at'] ?? ''),
			];
		}

		$counts = [];
		foreach ($allowedTypes as $vehicleType) {
			$filePath = $jsonDirectory . DIRECTORY_SEPARATOR . $vehicleType . '.json';
			$payload = [
				'category' => $vehicleType,
				'synced_at' => date('c'),
				'count' => count($grouped[$vehicleType]),
				'vehicles' => $grouped[$vehicleType],
			];

			$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			if ($json === false) {
				throw new RuntimeException('Unable to encode vehicles JSON for category: ' . $vehicleType);
			}

			file_put_contents($filePath, $json . PHP_EOL);
			$counts[$vehicleType] = count($grouped[$vehicleType]);
		}

		return $counts;
	}
}

if (!function_exists('vehicle_sync_is_json_newer')) {
	function vehicle_sync_is_json_newer(array $jsonRow, array $dbRow): bool
	{
		$jsonUpdatedAt = vehicle_sync_to_timestamp($jsonRow['updated_at'] ?? null);
		$dbUpdatedAt = vehicle_sync_to_timestamp($dbRow['updated_at'] ?? null);

		return $jsonUpdatedAt > $dbUpdatedAt;
	}
}

if (!function_exists('vehicle_sync_to_timestamp')) {
	function vehicle_sync_to_timestamp(mixed $value): int
	{
		if (!is_scalar($value)) {
			return 0;
		}

		$timestamp = strtotime((string) $value);
		return $timestamp === false ? 0 : $timestamp;
	}
}

if (!function_exists('vehicle_sync_pick_text')) {
	function vehicle_sync_pick_text(mixed $value, string $fallback): string
	{
		$text = trim((string) $value);
		return $text !== '' ? $text : $fallback;
	}
}

if (!function_exists('vehicle_sync_pick_nullable_text')) {
	function vehicle_sync_pick_nullable_text(mixed $value): ?string
	{
		$text = trim((string) $value);
		return $text !== '' ? $text : null;
	}
}

if (!function_exists('vehicle_sync_pick_int')) {
	function vehicle_sync_pick_int(mixed $value, int $fallback): int
	{
		if (is_numeric($value)) {
			return (int) $value;
		}

		return $fallback;
	}
}

if (!function_exists('vehicle_sync_pick_nullable_int')) {
	function vehicle_sync_pick_nullable_int(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		return (int) $value;
	}
}

if (!function_exists('vehicle_sync_pick_nullable_float')) {
	function vehicle_sync_pick_nullable_float(mixed $value): ?float
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		return round((float) $value, 2);
	}
}

if (!function_exists('vehicle_sync_pick_enum')) {
	function vehicle_sync_pick_enum(mixed $value, array $allowedValues, string $fallback): string
	{
		$normalized = strtolower(trim((string) $value));
		return in_array($normalized, $allowedValues, true) ? $normalized : $fallback;
	}
}

if (!function_exists('vehicle_sync_normalize_date')) {
	function vehicle_sync_normalize_date(mixed $value): ?string
	{
		$text = trim((string) $value);
		if ($text === '') {
			return null;
		}

		$timestamp = strtotime($text);
		if ($timestamp === false) {
			return null;
		}

		return date('Y-m-d', $timestamp);
	}
}

if (!function_exists('vehicle_sync_normalize_datetime')) {
	function vehicle_sync_normalize_datetime(mixed $value, string $fallback): string
	{
		$text = trim((string) $value);
		if ($text === '') {
			return $fallback;
		}

		$timestamp = strtotime($text);
		if ($timestamp === false) {
			return $fallback;
		}

		return date('Y-m-d H:i:s', $timestamp);
	}
}
