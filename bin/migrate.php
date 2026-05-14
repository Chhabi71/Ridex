<?php
/**
 * Purpose: CLI runner to apply database migrations in order.
 */

require_once __DIR__ . '/../config/database.php';

$defaultMigrations = [
	'migrations/20260331_create_users_table.sql',
	'migrations/20260331_create_categories_table.sql',
	'migrations/20260331_create_vehicles_table.sql',
	'migrations/20260331_create_bookings_table.sql',
	'migrations/20260331_create_payments_table.sql',
	'migrations/20260331_create_gps_logs_table.sql',
	'migrations/20260404_create_vehicle_maintenance_records_table.sql',
];

$args = array_slice($_SERVER['argv'] ?? [], 1);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
	echo 'Usage: php bin/migrate.php [sql-file ...]' . PHP_EOL;
	echo 'No arguments: applies default migration files in dependency order.' . PHP_EOL;
	echo 'With arguments: applies the provided SQL files in the same order.' . PHP_EOL;
	exit(0);
}

$isAbsolutePath = static function (string $path): bool {
	if (str_starts_with($path, DIRECTORY_SEPARATOR) || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
		return true;
	}

	return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
};

$resolvePath = static function (string $path) use ($isAbsolutePath): string {
	if ($isAbsolutePath($path)) {
		return $path;
	}

	return APP_ROOT . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
};

$migrationFiles = [];
if (empty($args)) {
	foreach ($defaultMigrations as $defaultMigration) {
		$migrationFiles[] = $resolvePath($defaultMigration);
	}
} else {
	foreach ($args as $arg) {
		$migrationFiles[] = $resolvePath($arg);
	}
}

$pdo = db();

try {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

	foreach ($migrationFiles as $migrationFile) {
		if (!is_file($migrationFile)) {
			throw new RuntimeException('Migration file not found: ' . $migrationFile);
		}

		$sql = file_get_contents($migrationFile);
		if ($sql === false) {
			throw new RuntimeException('Unable to read migration file: ' . $migrationFile);
		}

		if (trim($sql) === '') {
			echo 'Skipping empty migration: ' . basename($migrationFile) . PHP_EOL;
			continue;
		}

		$pdo->exec($sql);
		echo 'Applied migration: ' . basename($migrationFile) . PHP_EOL;
	}

	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
	echo 'Migrations completed successfully.' . PHP_EOL;
} catch (Throwable $exception) {
	try {
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
	} catch (Throwable $ignored) {
		// Ignore reset failures and report the original error below.
	}

	fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
