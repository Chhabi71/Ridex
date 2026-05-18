<?php
/**
 * Purpose: Bootstrap app configuration (environment loading, base URL, timezone, error settings).
*/

$rootPath = dirname(__DIR__);
$envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';

if (is_file($envPath)) {
	$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
			continue;
		}

		[$name, $value] = array_map('trim', explode('=', $line, 2));
		$value = trim($value, "\"'");
		if ($name !== '') {
			$_ENV[$name] = $value;
			$_SERVER[$name] = $value;
			putenv($name . '=' . $value);
		}
	}
}

if (!function_exists('env')) {
	function env(string $key, mixed $default = null): mixed
	{
		$value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

		return $value === false || $value === null || $value === '' ? $default : $value;
	}
}

if (!function_exists('env_first')) {
	function env_first(array $keys, mixed $default = null): mixed
	{
		foreach ($keys as $key) {
			$value = env((string) $key, null);
			if (is_string($value)) {
				$value = trim($value);
			}

			if ($value !== null && $value !== '' && $value !== false) {
				return $value;
			}
		}

		return $default;
	}
}

$dbUrlValue = (string) env_first([
	'DATABASE_URL',
	'MYSQL_URL',
	'MYSQL_INTERNAL_URL',
	'DB_URL',
], '');

$dbUrlParts = [
	'host' => null,
	'port' => null,
	'name' => null,
	'user' => null,
	'pass' => null,
];

if ($dbUrlValue !== '') {
	$parsedDbUrl = parse_url($dbUrlValue);
	if (is_array($parsedDbUrl)) {
		$dbUrlParts['host'] = isset($parsedDbUrl['host']) ? (string) $parsedDbUrl['host'] : null;
		$dbUrlParts['port'] = isset($parsedDbUrl['port']) ? (int) $parsedDbUrl['port'] : null;
		$dbUrlParts['user'] = isset($parsedDbUrl['user']) ? (string) $parsedDbUrl['user'] : null;
		$dbUrlParts['pass'] = isset($parsedDbUrl['pass']) ? (string) $parsedDbUrl['pass'] : null;

		$dbNameFromPath = isset($parsedDbUrl['path']) ? ltrim((string) $parsedDbUrl['path'], '/') : '';
		if ($dbNameFromPath !== '') {
			$dbUrlParts['name'] = $dbNameFromPath;
		} else {
			$queryParams = [];
			parse_str((string) ($parsedDbUrl['query'] ?? ''), $queryParams);
			$dbNameFromQuery = trim((string) ($queryParams['dbname'] ?? $queryParams['database'] ?? ''));
			if ($dbNameFromQuery !== '') {
				$dbUrlParts['name'] = $dbNameFromQuery;
			}
		}
	}
}

if (!defined('APP_ROOT')) {
	define('APP_ROOT', $rootPath);
}

if (!defined('BASE_URL')) {
	define('BASE_URL', rtrim((string) env('BASE_URL', '/rentals-app/public'), '/'));
}

if (!defined('DB_HOST')) {
	define('DB_HOST', (string) env_first([
		'DB_HOST',
		'DATABASE_HOST',
		'MYSQL_HOST',
		'MYSQLHOST',
	], $dbUrlParts['host'] ?? '127.0.0.1'));
}

if (!defined('DB_PORT')) {
	define('DB_PORT', (int) env_first([
		'DB_PORT',
		'DATABASE_PORT',
		'MYSQL_PORT',
		'MYSQLPORT',
	], $dbUrlParts['port'] ?? 3307));
}

if (!defined('DB_NAME')) {
	define('DB_NAME', (string) env_first([
		'DB_NAME',
		'DB_DATABASE',
		'DATABASE_NAME',
		'MYSQL_DATABASE',
	], $dbUrlParts['name'] ?? 'ridex_db'));
}

if (!defined('DB_USER')) {
	define('DB_USER', (string) env_first([
		'DB_USER',
		'DB_USERNAME',
		'DATABASE_USER',
		'MYSQL_USER',
		'MYSQLUSERNAME',
	], $dbUrlParts['user'] ?? 'root'));
}

if (!defined('DB_PASS')) {
	define('DB_PASS', (string) env_first([
		'DB_PASS',
		'DB_PASSWORD',
		'DATABASE_PASSWORD',
		'MYSQL_PASSWORD',
		'MYSQLPASSWORD',
	], $dbUrlParts['pass'] ?? ''));
}

if (!defined('APP_TIMEZONE')) {
	define('APP_TIMEZONE', (string) env('APP_TIMEZONE', 'Asia/Kathmandu'));
}

date_default_timezone_set(APP_TIMEZONE);
