<?php
/**
 * Purpose: Provide PDO/MySQL connection helper using environment credentials.
*/

require_once __DIR__ . '/config.php';

if (!function_exists('db')) {
	function db(): PDO
	{
		static $pdo = null;

		if ($pdo instanceof PDO) {
			return $pdo;
		}

		$dsn = sprintf(
			'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
			DB_HOST,
			DB_PORT,
			DB_NAME
		);

		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];

		try {
			$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
		} catch (PDOException $exception) {
			throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
		}

		return $pdo;
	}
}

if (!function_exists('get_db_connection')) {
	function get_db_connection(): PDO
	{
		return db();
	}
}
