<?php
/**
 * Purpose: Category model for vehicle grouping/filtering.
 * Website Section: Vehicle Catalog.
 * Developer Notes: CRUD categories, fetch for filters/badges, ensure unique names, and relate to vehicles.
 */

if (!function_exists('ridex_category_allowed_vehicle_types')) {
	function ridex_category_allowed_vehicle_types(): array
	{
		return ['cars', 'bikes', 'luxury'];
	}
}

if (!function_exists('ridex_category_sanitize_vehicle_type')) {
	function ridex_category_sanitize_vehicle_type($rawType, string $defaultType = 'cars'): string
	{
		$allowedTypes = ridex_category_allowed_vehicle_types();
		$defaultType = strtolower(trim($defaultType));
		if (!in_array($defaultType, $allowedTypes, true)) {
			$defaultType = 'cars';
		}

		$normalizedType = strtolower(trim((string) $rawType));
		return in_array($normalizedType, $allowedTypes, true)
			? $normalizedType
			: $defaultType;
	}
}
