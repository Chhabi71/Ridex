<?php
/**
 * Purpose: Input validation and sanitization helpers.
 * Website Section: Shared Validation (forms across auth, booking, admin CRUD).
 * Developer Notes: Provide reusable rules (email, phone, dates, enums), trim/sanitize inputs, and return error messages for controllers/views.
 */

if (!function_exists('ridex_normalize_date_for_storage')) {
	function ridex_normalize_date_for_storage(string $rawDate): string
	{
		$rawDate = trim($rawDate);
		if ($rawDate === '') {
			return '';
		}

		$formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
		foreach ($formats as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $rawDate);
			if ($date instanceof DateTimeImmutable) {
				return $date->format('Y-m-d');
			}
		}

		try {
			return (new DateTimeImmutable($rawDate))->format('Y-m-d');
		} catch (Throwable $exception) {
			return '';
		}
	}
}

if (!function_exists('ridex_normalize_phone_for_storage')) {
	function ridex_normalize_phone_for_storage(string $rawPhone): array
	{
		$phoneDigits = preg_replace('/\D+/', '', $rawPhone);
		$phoneDigits = is_string($phoneDigits) ? $phoneDigits : '';

		if (str_starts_with($phoneDigits, '977')) {
			$phoneDigits = substr($phoneDigits, 3);
		}

		return [
			'local_digits' => $phoneDigits,
			'formatted' => '+977 ' . $phoneDigits,
		];
	}
}

if (!function_exists('ridex_is_valid_nepal_phone_local_digits')) {
	function ridex_is_valid_nepal_phone_local_digits(string $phoneLocalDigits): bool
	{
		return preg_match('/^9\d{9}$/', trim($phoneLocalDigits)) === 1;
	}
}

if (!function_exists('ridex_is_valid_password_strength')) {
	function ridex_is_valid_password_strength(string $password): bool
	{
		$hasLowercase = preg_match('/[a-z]/', $password) === 1;
		$hasUppercase = preg_match('/[A-Z]/', $password) === 1;
		$hasDigit = preg_match('/\d/', $password) === 1;
		$hasSymbol = preg_match('/[^a-zA-Z\d]/', $password) === 1;
		$hasMinLength = strlen($password) >= 8;

		return $hasLowercase && $hasUppercase && $hasDigit && $hasSymbol && $hasMinLength;
	}
}
