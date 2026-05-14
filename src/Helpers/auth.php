<?php
/**
 * Purpose: Authentication helper functions (session retrieval, role checks, login/logout utilities).
 * Website Section: Cross-cutting Auth (guards for controllers/views).
 * Developer Notes: Provide helpers to check logged-in user, enforce roles, persist sessions, and fetch current user model.
 */

if (!function_exists('ridex_bool_from_form_value')) {
	function ridex_bool_from_form_value($rawValue): bool
	{
		$normalizedValue = strtolower(trim((string) $rawValue));
		return in_array($normalizedValue, ['1', 'true', 'on', 'yes'], true);
	}
}

if (!function_exists('ridex_normalize_post_auth_redirect')) {
	function ridex_normalize_post_auth_redirect($rawRedirect): string
	{
		$defaultRedirect = 'index.php';
		$rawRedirect = trim((string) $rawRedirect);
		if ($rawRedirect === '') {
			return $defaultRedirect;
		}

		$parsedUrl = parse_url($rawRedirect);
		if (!is_array($parsedUrl)) {
			return $defaultRedirect;
		}

		$path = trim((string) ($parsedUrl['path'] ?? 'index.php'));
		if ($path === '' || $path === '/') {
			$path = 'index.php';
		}
		$path = str_replace('\\', '/', $path);
		$pathBaseName = strtolower(trim((string) basename($path)));
		if ($pathBaseName !== 'index.php') {
			return $defaultRedirect;
		}

		$queryValues = [];
		if (isset($parsedUrl['query'])) {
			parse_str((string) $parsedUrl['query'], $queryValues);
		}
		if (!is_array($queryValues)) {
			$queryValues = [];
		}

		$targetPage = strtolower(trim((string) ($queryValues['page'] ?? '')));
		$allowedPages = ['booking-select', 'booking-engine', 'booking-checkout', 'user-booking-history'];
		if (!in_array($targetPage, $allowedPages, true)) {
			return $defaultRedirect;
		}

		$safeQuery = [
			'page' => $targetPage,
		];

		if ($targetPage === 'user-booking-history') {
			$historyTab = strtolower(trim((string) ($queryValues['tab'] ?? 'active')));
			if (!in_array($historyTab, ['active', 'pending', 'completed', 'cancelled'], true)) {
				$historyTab = 'active';
			}

			$safeQuery['tab'] = $historyTab;
			return 'index.php?' . http_build_query($safeQuery);
		}

		$vehicleType = strtolower(trim((string) ($queryValues['vehicle_type'] ?? 'cars')));
		if (!in_array($vehicleType, ['cars', 'bikes', 'luxury'], true)) {
			$vehicleType = 'cars';
		}
		$safeQuery['vehicle_type'] = $vehicleType;

		if ($targetPage !== 'booking-select') {
			$vehicleId = (int) ($queryValues['vehicle_id'] ?? 0);
			if ($vehicleId > 0) {
				$safeQuery['vehicle_id'] = $vehicleId;
			}
		}

		$bookingTextKeys = [
			'pickup-location',
			'return-location',
			'pickup-date',
			'return-date',
			'pickup-time',
			'return-time',
		];
		foreach ($bookingTextKeys as $bookingTextKey) {
			$bookingTextValue = trim((string) ($queryValues[$bookingTextKey] ?? ''));
			if ($bookingTextValue !== '') {
				$safeQuery[$bookingTextKey] = $bookingTextValue;
			}
		}

		$sameReturnRaw = strtolower(trim((string) ($queryValues['same-return'] ?? '')));
		if (in_array($sameReturnRaw, ['1', 'true', 'on', 'yes'], true)) {
			$safeQuery['same-return'] = '1';
		}

		if ($targetPage === 'booking-engine') {
			$attemptRaw = trim((string) ($queryValues['attempt'] ?? ''));
			if ($attemptRaw === '1') {
				$safeQuery['attempt'] = '1';
			}
		}

		$flowStartRaw = trim((string) ($queryValues['flow_start'] ?? ''));
		if ($flowStartRaw === '1') {
			$safeQuery['flow_start'] = '1';
		}

		return 'index.php?' . http_build_query($safeQuery);
	}
}

if (!function_exists('ridex_build_user_display_name')) {
	function ridex_build_user_display_name(array $userRow, string $defaultName = 'Ridex User'): string
	{
		$displayName = trim((string) ($userRow['name'] ?? ''));
		if ($displayName === '') {
			$displayName = trim((string) ($userRow['first_name'] ?? '') . ' ' . (string) ($userRow['last_name'] ?? ''));
		}

		if ($displayName === '') {
			$displayName = $defaultName;
		}

		return $displayName;
	}
}

if (!function_exists('ridex_build_user_session_payload')) {
	function ridex_build_user_session_payload(array $userRow, string $role = 'user'): array
	{
		return [
			'id' => (int) ($userRow['id'] ?? 0),
			'name' => ridex_build_user_display_name($userRow),
			'email' => trim((string) ($userRow['email'] ?? '')),
			'phone' => trim((string) ($userRow['phone'] ?? '')),
			'drivers_id' => trim((string) ($userRow['drivers_id'] ?? '')),
			'drivers_id_image_path' => trim((string) ($userRow['drivers_id_image_path'] ?? '')),
			'date_of_birth' => trim((string) ($userRow['date_of_birth'] ?? '')),
			'role' => strtolower(trim($role)) === 'admin' ? 'admin' : 'user',
		];
	}
}
