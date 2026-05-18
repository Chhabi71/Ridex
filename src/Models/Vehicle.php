<?php
/**
 * Purpose: Vehicle model for fleet records and availability calculations.
 * Website Section: Vehicle Catalog & Admin Fleet Management.
 * Developer Notes: Provide CRUD operations, filter queries, status transitions, and joins to categories/bookings for availability and status badges.
 */

if (!function_exists('ridex_vehicle_parse_datetime_safe')) {
	function ridex_vehicle_parse_datetime_safe($rawDate): ?DateTimeImmutable
	{
		$rawDate = trim((string) $rawDate);
		if ($rawDate === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($rawDate);
		} catch (Throwable $exception) {
			return null;
		}
	}
}

if (!function_exists('ridex_vehicle_resolve_effective_status')) {
	function ridex_vehicle_resolve_effective_status(array $vehicleRow, ?DateTimeImmutable $referenceDateTime = null): string
	{
		$referenceDateTime = $referenceDateTime ?? new DateTimeImmutable('now');
		$deletedAt = trim((string) ($vehicleRow['deleted_at'] ?? $vehicleRow['vehicle_deleted_at'] ?? ''));
		if ($deletedAt !== '') {
			return 'unavailable';
		}

		$activeBookingStatus = strtolower(trim((string) ($vehicleRow['active_booking_status'] ?? $vehicleRow['vehicle_active_booking_status'] ?? '')));
		if (in_array($activeBookingStatus, ['reserved', 'on_trip', 'overdue'], true)) {
			return $activeBookingStatus;
		}

		$rawStatus = strtolower(trim((string) ($vehicleRow['status'] ?? $vehicleRow['vehicle_current_status'] ?? '')));
		if ($rawStatus === '') {
			$rawStatus = 'available';
		}

		$activePickupDateTime = ridex_vehicle_parse_datetime_safe($vehicleRow['active_pickup_datetime'] ?? $vehicleRow['vehicle_active_pickup_datetime'] ?? null);
		$activeReturnDateTime = ridex_vehicle_parse_datetime_safe($vehicleRow['active_return_datetime'] ?? $vehicleRow['vehicle_active_return_datetime'] ?? null);
		$upcomingPickupDateTime = ridex_vehicle_parse_datetime_safe($vehicleRow['upcoming_pickup_datetime'] ?? $vehicleRow['vehicle_upcoming_pickup_datetime'] ?? null);

		if ($activePickupDateTime instanceof DateTimeImmutable && $activeReturnDateTime instanceof DateTimeImmutable) {
			if ($referenceDateTime > $activeReturnDateTime) {
				return 'overdue';
			}

			if ($referenceDateTime >= $activePickupDateTime) {
				return 'on_trip';
			}

			$reservedWindowStart = $activePickupDateTime->sub(new DateInterval('P2D'));
			if ($referenceDateTime >= $reservedWindowStart) {
				return 'reserved';
			}
		}

		if ($upcomingPickupDateTime instanceof DateTimeImmutable) {
			$reservedWindowStart = $upcomingPickupDateTime->sub(new DateInterval('P2D'));
			if ($referenceDateTime >= $reservedWindowStart && $referenceDateTime < $upcomingPickupDateTime) {
				return 'reserved';
			}
		}

		if ($rawStatus === 'maintenance') {
			return 'maintenance';
		}

		if (!in_array($rawStatus, ['available', 'reserved', 'on_trip', 'overdue', 'maintenance'], true)) {
			return 'available';
		}

		return $rawStatus;
	}
}
