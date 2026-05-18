<?php
/**
 * Purpose: Shared bootstrap and utility helpers for CLI cron scripts.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Helpers/runtime_sync.php';

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, 'This script can only be run from CLI.' . PHP_EOL);
	exit(1);
}

if (!function_exists('ridex_cron_env_int')) {
	function ridex_cron_env_int(string $key, int $default): int
	{
		$rawValue = env($key, null);
		if ($rawValue === null || $rawValue === '') {
			return $default;
		}

		$value = filter_var((string) $rawValue, FILTER_VALIDATE_INT);
		if ($value === false) {
			return $default;
		}

		return (int) $value;
	}
}

if (!function_exists('ridex_cron_log_file_path')) {
	function ridex_cron_log_file_path(string $jobName): string
	{
		$safeJobName = preg_replace('/[^a-z0-9\-_]+/i', '-', strtolower(trim($jobName)));
		$safeJobName = is_string($safeJobName) && $safeJobName !== '' ? $safeJobName : 'cron-job';

		$logDir = APP_ROOT . '/var/logs/cron';
		if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
			throw new RuntimeException('Unable to create cron log directory: ' . $logDir);
		}

		return $logDir . '/' . $safeJobName . '.log';
	}
}

if (!function_exists('ridex_cron_write_log')) {
	function ridex_cron_write_log(string $jobName, string $message, array $context = []): void
	{
		$logPath = ridex_cron_log_file_path($jobName);
		$line = '[' . (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') . '] '
			. strtoupper($jobName)
			. ' '
			. trim($message);

		if (!empty($context)) {
			$encodedContext = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if (is_string($encodedContext) && $encodedContext !== '') {
				$line .= ' ' . $encodedContext;
			}
		}

		file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}

if (!function_exists('ridex_cron_print_json')) {
	function ridex_cron_print_json(array $payload): void
	{
		$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if (!is_string($json) || $json === '') {
			$json = '{"ok":false,"message":"Unable to encode payload."}';
		}

		echo $json . PHP_EOL;
	}
}

if (!function_exists('ridex_cron_ensure_cache_dir')) {
	function ridex_cron_ensure_cache_dir(string $relativePath = ''): string
	{
		$baseDir = APP_ROOT . '/var/cache';
		$path = $baseDir;
		if ($relativePath !== '') {
			$path = $baseDir . '/' . trim(str_replace('\\', '/', $relativePath), '/');
		}

		if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
			throw new RuntimeException('Unable to create cache directory: ' . $path);
		}

		return $path;
	}
}

if (!function_exists('ridex_cron_ensure_payments_table')) {
	function ridex_cron_ensure_payments_table(PDO $pdo): void
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS payments (
				id INT AUTO_INCREMENT PRIMARY KEY,
				booking_id INT NOT NULL,
				amount INT NOT NULL,
				method ENUM("khalti","cash") NOT NULL,
				status ENUM("initiated","pending","success","failed","cancelled","refunded") NOT NULL,
				transaction_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				pidx VARCHAR(100),
				provider_response LONGTEXT,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
				CHECK (provider_response IS NULL OR JSON_VALID(provider_response))
			)'
		);
	}
}
