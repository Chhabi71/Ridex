<?php
/**
 * Purpose: Base model for database access helpers (PDO connection, common CRUD utilities).
 * Website Section: Data Layer (shared across modules).
 * Developer Notes: Provide connection handling, query helpers, transaction wrappers, and safe parameter binding.
 */

if (!function_exists('ridex_basemodel_column_exists')) {
	function ridex_basemodel_column_exists(PDO $pdo, string $tableName, string $columnName): bool
	{
		$tableName = trim($tableName);
		$columnName = trim($columnName);
		if ($tableName === '' || $columnName === '') {
			return false;
		}

		$statement = $pdo->prepare(
			'SELECT COUNT(*)
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :table_name
				AND COLUMN_NAME = :column_name'
		);
		$statement->execute([
			'table_name' => $tableName,
			'column_name' => $columnName,
		]);

		return (int) $statement->fetchColumn() > 0;
	}
}

if (!function_exists('ridex_basemodel_ensure_vehicle_soft_delete_column')) {
	function ridex_basemodel_ensure_vehicle_soft_delete_column(PDO $pdo): void
	{
		static $checked = false;
		if ($checked) {
			return;
		}
		$checked = true;

		try {
			$hasDeletedAtColumn = ridex_basemodel_column_exists($pdo, 'vehicles', 'deleted_at');
			if (!$hasDeletedAtColumn) {
				$pdo->exec('ALTER TABLE vehicles ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at');
			}
		} catch (Throwable $exception) {
			error_log('Ensure vehicles.deleted_at column failed: ' . $exception->getMessage());
		}
	}
}

if (!function_exists('ridex_basemodel_ensure_vehicle_mileage_column')) {
	function ridex_basemodel_ensure_vehicle_mileage_column(PDO $pdo): void
	{
		static $checked = false;
		if ($checked) {
			return;
		}
		$checked = true;

		try {
			$hasMileageColumn = ridex_basemodel_column_exists($pdo, 'vehicles', 'mileage_km_per_l');
			if (!$hasMileageColumn) {
				$pdo->exec('ALTER TABLE vehicles ADD COLUMN mileage_km_per_l DECIMAL(6,2) NULL AFTER fuel_type');
			}
		} catch (Throwable $exception) {
			error_log('Ensure vehicles.mileage_km_per_l column failed: ' . $exception->getMessage());
		}
	}
}
