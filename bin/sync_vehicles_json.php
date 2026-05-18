<?php
/**
 * Purpose: CLI script to sync vehicles table bidirectionally with category JSON files.
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/BaseModel.php';
require_once __DIR__ . '/../src/Helpers/vehicle_json_sync.php';

$trackedJsonDir = APP_ROOT . '/data/vehicles-json';
$legacyJsonDir = APP_ROOT . '/var/cache/vehicles-json';

$toBoolEnv = static function ($value): bool {
	$normalized = strtolower(trim((string) $value));
	return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
};

$defaultLegacyMirror = strtolower((string) env('APP_ENV', 'production')) === 'local' ? '1' : '0';
$enableLegacyVehicleJsonMirror = $toBoolEnv(env('ENABLE_LEGACY_JSON_MIRROR', $defaultLegacyMirror));

$mirrorVehicleJsonFiles = static function (string $sourceDir, string $targetDir) use ($enableLegacyVehicleJsonMirror): void {
	if (!$enableLegacyVehicleJsonMirror) {
		return;
	}

	$vehicleTypes = ['cars', 'bikes', 'luxury'];
	if (!is_dir($sourceDir)) {
		return;
	}

	if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
		throw new RuntimeException('Unable to create vehicle JSON mirror directory: ' . $targetDir);
	}

	foreach ($vehicleTypes as $vehicleType) {
		$sourceFile = rtrim($sourceDir, '\\/') . DIRECTORY_SEPARATOR . $vehicleType . '.json';
		$targetFile = rtrim($targetDir, '\\/') . DIRECTORY_SEPARATOR . $vehicleType . '.json';
		if (!is_file($sourceFile)) {
			continue;
		}

		$sourceMtime = (int) (@filemtime($sourceFile) ?: 0);
		$targetMtime = is_file($targetFile) ? (int) (@filemtime($targetFile) ?: 0) : 0;
		if ($sourceMtime <= $targetMtime) {
			continue;
		}

		if (!@copy($sourceFile, $targetFile)) {
			throw new RuntimeException('Unable to copy vehicle JSON from ' . $sourceFile . ' to ' . $targetFile);
		}
	}
};

try {
	$args = array_slice($_SERVER['argv'] ?? [], 1);
	if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
		echo "Usage: php bin/sync_vehicles_json.php [--prefer-json|--force-json|--prefer-db-timestamps|--prefer-db]" . PHP_EOL;
		echo "Default mode is JSON-first (JSON add/edit/delete is applied to DB)." . PHP_EOL;
		echo "  --prefer-json          Force JSON as conflict winner." . PHP_EOL;
		echo "  --force-json   Alias for --prefer-json." . PHP_EOL;
		echo "  --prefer-db-timestamps Force DB as conflict winner (legacy behavior)." . PHP_EOL;
		echo "  --prefer-db            Alias for --prefer-db-timestamps." . PHP_EOL;
		exit(0);
	}

	$preferJsonFlag = in_array('--prefer-json', $args, true) || in_array('--force-json', $args, true);
	$preferDbTimestamps = in_array('--prefer-db-timestamps', $args, true) || in_array('--prefer-db', $args, true);
	if ($preferJsonFlag && $preferDbTimestamps) {
		throw new InvalidArgumentException('Use either --prefer-json or --prefer-db-timestamps, not both.');
	}

	$options = [
		'prefer_json' => true,
	];
	if ($preferJsonFlag) {
		$options['prefer_json'] = true;
	}
	if ($preferDbTimestamps) {
		$options['prefer_json'] = false;
		$options['prefer_db_timestamps'] = true;
	}

	$mirrorVehicleJsonFiles($legacyJsonDir, $trackedJsonDir);
	ridex_basemodel_ensure_vehicle_mileage_column(db());

	$result = sync_vehicles_json_bidirectional(
		db(),
		$trackedJsonDir,
		$options
	);
	$mirrorVehicleJsonFiles($trackedJsonDir, $legacyJsonDir);
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
	fwrite(STDERR, 'Vehicle JSON sync failed: ' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
