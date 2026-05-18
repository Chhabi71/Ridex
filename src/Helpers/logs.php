<?php
/**
 * Purpose: Logging utilities to standardize app/cron log writes.
 * Website Section: Cross-cutting Observability.
 * Developer Notes: Wrap file or PSR logger calls for structured entries, include correlation IDs, and handle error/cron log destinations.
 */

if (!function_exists('ridex_log_error')) {
	function ridex_log_error(string $message): void
	{
		error_log(trim($message));
	}
}

if (!function_exists('ridex_log_exception')) {
	function ridex_log_exception(string $context, Throwable $exception): void
	{
		$normalizedContext = trim($context);
		if ($normalizedContext === '') {
			ridex_log_error($exception->getMessage());
			return;
		}

		ridex_log_error(rtrim($normalizedContext, '. ') . ': ' . $exception->getMessage());
	}
}
