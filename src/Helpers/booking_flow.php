<?php
/**
 * Purpose: Shared booking flow helper functions extracted from the front controller.
 */

if (!function_exists('ridex_parse_booking_datetime_from_parts')) {
	function ridex_parse_booking_datetime_from_parts(string $datePart, string $timePart): ?DateTimeImmutable
	{
		$datePart = trim($datePart);
		$timePart = strtoupper(trim($timePart));
		if ($datePart === '' || $timePart === '') {
			return null;
		}

		$formats = [
			'd/m/Y h:i A',
			'd-m-Y h:i A',
			'Y-m-d H:i',
			'Y-m-d H:i:s',
			'd/m/Y H:i',
		];

		$rawDateTime = $datePart . ' ' . $timePart;
		foreach ($formats as $format) {
			$parsedDateTime = DateTimeImmutable::createFromFormat($format, $rawDateTime);
			$errors = DateTimeImmutable::getLastErrors();
			$hasParseErrors = is_array($errors)
				&& ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0);
			if ($parsedDateTime instanceof DateTimeImmutable && !$hasParseErrors) {
				return $parsedDateTime;
			}
		}

		try {
			return new DateTimeImmutable($rawDateTime);
		} catch (Throwable $exception) {
			return null;
		}
	}
}

if (!function_exists('ridex_sanitize_booking_search_input')) {
	function ridex_sanitize_booking_search_input(array $source): array
	{
		$pickupLocation = trim((string) ($source['pickup-location'] ?? ''));
		$returnLocation = trim((string) ($source['return-location'] ?? ''));
		$pickupDate = trim((string) ($source['pickup-date'] ?? ''));
		$returnDate = trim((string) ($source['return-date'] ?? ''));
		$pickupTime = trim((string) ($source['pickup-time'] ?? ''));
		$returnTime = trim((string) ($source['return-time'] ?? ''));
		$sameReturn = in_array(
			strtolower(trim((string) ($source['same-return'] ?? ''))),
			['1', 'true', 'on', 'yes'],
			true
		);

		if ($sameReturn && $pickupLocation !== '' && $returnLocation === '') {
			$returnLocation = $pickupLocation;
		}

		$pickupDateTime = ridex_parse_booking_datetime_from_parts($pickupDate, $pickupTime);
		$returnDateTime = ridex_parse_booking_datetime_from_parts($returnDate, $returnTime);

		$errors = [];
		if ($pickupLocation === '') {
			$errors[] = 'Pickup location is required.';
		}

		if ($returnLocation === '') {
			$errors[] = 'Return location is required.';
		}

		if (!($pickupDateTime instanceof DateTimeImmutable)) {
			$errors[] = 'Pickup date and time are required.';
		}

		if (!($returnDateTime instanceof DateTimeImmutable)) {
			$errors[] = 'Return date and time are required.';
		}

		if (
			$pickupDateTime instanceof DateTimeImmutable
			&& $returnDateTime instanceof DateTimeImmutable
			&& $returnDateTime <= $pickupDateTime
		) {
			$errors[] = 'Return date/time must be after pickup date/time.';
		}

		if ($pickupDateTime instanceof DateTimeImmutable) {
			$nowDateTime = new DateTimeImmutable('now');
			if ($pickupDateTime <= $nowDateTime) {
				$errors[] = 'Pickup date/time must be in the future.';
			}
		}

		if ($returnDateTime instanceof DateTimeImmutable) {
			$nowDateTime = isset($nowDateTime) && $nowDateTime instanceof DateTimeImmutable
				? $nowDateTime
				: new DateTimeImmutable('now');
			if ($returnDateTime <= $nowDateTime) {
				$errors[] = 'Return date/time must be in the future.';
			}
		}

		if (
			$pickupDateTime instanceof DateTimeImmutable
			&& $returnDateTime instanceof DateTimeImmutable
		) {
			$maxReturnDateTime = $pickupDateTime->modify('+1 month');
			if ($maxReturnDateTime instanceof DateTimeImmutable && $returnDateTime > $maxReturnDateTime) {
				$errors[] = 'Booking duration cannot exceed 1 month.';
			}
		}

		return [
			'pickup_location' => $pickupLocation,
			'return_location' => $returnLocation,
			'pickup_date' => $pickupDate,
			'return_date' => $returnDate,
			'pickup_time' => $pickupTime,
			'return_time' => $returnTime,
			'same_return' => $sameReturn,
			'pickup_datetime' => $pickupDateTime,
			'return_datetime' => $returnDateTime,
			'is_valid' => empty($errors),
			'errors' => $errors,
		];
	}
}

if (!function_exists('ridex_build_booking_search_query')) {
	function ridex_build_booking_search_query(array $bookingSearch): array
	{
		$query = [];

		$pickupLocation = trim((string) ($bookingSearch['pickup_location'] ?? ''));
		$returnLocation = trim((string) ($bookingSearch['return_location'] ?? ''));
		$pickupDate = trim((string) ($bookingSearch['pickup_date'] ?? ''));
		$returnDate = trim((string) ($bookingSearch['return_date'] ?? ''));
		$pickupTime = trim((string) ($bookingSearch['pickup_time'] ?? ''));
		$returnTime = trim((string) ($bookingSearch['return_time'] ?? ''));
		$sameReturn = !empty($bookingSearch['same_return']);

		if ($pickupLocation !== '') {
			$query['pickup-location'] = $pickupLocation;
		}

		if ($returnLocation !== '') {
			$query['return-location'] = $returnLocation;
		}

		if ($pickupDate !== '') {
			$query['pickup-date'] = $pickupDate;
		}

		if ($returnDate !== '') {
			$query['return-date'] = $returnDate;
		}

		if ($pickupTime !== '') {
			$query['pickup-time'] = $pickupTime;
		}

		if ($returnTime !== '') {
			$query['return-time'] = $returnTime;
		}

		if ($sameReturn) {
			$query['same-return'] = '1';
		}

		return $query;
	}
}

if (!function_exists('ridex_calculate_booking_price_breakdown')) {
	function ridex_calculate_booking_price_breakdown(
		int $pricePerDay,
		DateTimeImmutable $pickupDateTime,
		DateTimeImmutable $returnDateTime
	): array {
		$pricePerDay = max(0, $pricePerDay);
		$totalMinutes = max(1, (int) ceil(($returnDateTime->getTimestamp() - $pickupDateTime->getTimestamp()) / 60));
		$totalDays = max(1, (int) ceil($totalMinutes / 1440));
		$priceForDays = $pricePerDay * $totalDays;
		$dropCharge = 20;
		$subtotal = $priceForDays + $dropCharge;
		$totalAmount = (int) round($subtotal * 1.13, 0);
		$taxesAndFees = max(0, $totalAmount - $subtotal);

		return [
			'total_days' => $totalDays,
			'price_per_day' => $pricePerDay,
			'price_for_days' => $priceForDays,
			'drop_charge' => $dropCharge,
			'taxes_and_fees' => $taxesAndFees,
			'total_amount' => $totalAmount,
		];
	}
}

if (!function_exists('ridex_is_vehicle_blocked_by_overdue_status')) {
	function ridex_is_vehicle_blocked_by_overdue_status(PDO $pdo, int $vehicleId): bool
	{
		if ($vehicleId <= 0) {
			return true;
		}

		$vehicleStatusStmt = $pdo->prepare(
			'SELECT LOWER(status)
			 FROM vehicles
			 WHERE id = :vehicle_id
				AND deleted_at IS NULL
			 LIMIT 1'
		);
		$vehicleStatusStmt->execute([
			'vehicle_id' => $vehicleId,
		]);
		$vehicleStatus = strtolower(trim((string) $vehicleStatusStmt->fetchColumn()));
		if ($vehicleStatus === '' || $vehicleStatus === 'overdue') {
			return true;
		}

		$overdueBookingStmt = $pdo->prepare(
			'SELECT COUNT(*)
			 FROM bookings
			 WHERE vehicle_id = :vehicle_id
				AND status = :status'
		);
		$overdueBookingStmt->execute([
			'vehicle_id' => $vehicleId,
			'status' => 'overdue',
		]);

		return (int) $overdueBookingStmt->fetchColumn() > 0;
	}
}

if (!function_exists('ridex_is_vehicle_available_for_booking_window')) {
	function ridex_is_vehicle_available_for_booking_window(
		PDO $pdo,
		int $vehicleId,
		DateTimeImmutable $pickupDateTime,
		DateTimeImmutable $returnDateTime
	): bool {
		if ($vehicleId <= 0 || $returnDateTime <= $pickupDateTime) {
			return false;
		}

		if (ridex_is_vehicle_blocked_by_overdue_status($pdo, $vehicleId)) {
			return false;
		}

		$vehicleAvailabilityStmt = $pdo->prepare(
			'SELECT id
			 FROM vehicles
			 WHERE id = :vehicle_id
				AND deleted_at IS NULL
				AND status NOT IN (:maintenance_status, :overdue_status)
			 LIMIT 1'
		);
		$vehicleAvailabilityStmt->execute([
			'vehicle_id' => $vehicleId,
			'maintenance_status' => 'maintenance',
			'overdue_status' => 'overdue',
		]);

		if ((int) $vehicleAvailabilityStmt->fetchColumn() <= 0) {
			return false;
		}

		$overlapStmt = $pdo->prepare(
			'SELECT COUNT(*)
			 FROM bookings b
			 WHERE b.vehicle_id = :booking_vehicle_id
				AND b.status IN ("reserved", "on_trip", "overdue")
				AND DATE(:requested_pickup) <= DATE(COALESCE(b.return_time, b.return_datetime))
				AND DATE(:requested_return) >= DATE(b.pickup_datetime)'
		);
		$overlapStmt->execute([
			'booking_vehicle_id' => $vehicleId,
			'requested_pickup' => $pickupDateTime->format('Y-m-d H:i:s'),
			'requested_return' => $returnDateTime->format('Y-m-d H:i:s'),
		]);

		return (int) $overlapStmt->fetchColumn() === 0;
	}
}

if (!function_exists('ridex_does_user_have_overlapping_booking_window')) {
	function ridex_does_user_have_overlapping_booking_window(
		PDO $pdo,
		int $userId,
		DateTimeImmutable $pickupDateTime,
		DateTimeImmutable $returnDateTime
	): bool {
		if ($userId <= 0 || $returnDateTime <= $pickupDateTime) {
			return false;
		}

		$userOverlapStmt = $pdo->prepare(
			'SELECT COUNT(*)
			 FROM bookings b
			 WHERE b.user_id = :booking_user_id
				AND b.status IN ("reserved", "on_trip", "overdue")
				AND DATE(:requested_pickup) <= DATE(COALESCE(b.return_time, b.return_datetime))
				AND DATE(:requested_return) >= DATE(b.pickup_datetime)'
		);
		$userOverlapStmt->execute([
			'booking_user_id' => $userId,
			'requested_pickup' => $pickupDateTime->format('Y-m-d H:i:s'),
			'requested_return' => $returnDateTime->format('Y-m-d H:i:s'),
		]);

		return (int) $userOverlapStmt->fetchColumn() > 0;
	}
}

if (!function_exists('ridex_user_has_overdue_booking')) {
	function ridex_user_has_overdue_booking(PDO $pdo, int $userId): bool
	{
		if ($userId <= 0) {
			return false;
		}

		$statement = $pdo->prepare(
			'SELECT COUNT(*)
			 FROM bookings
			 WHERE user_id = :user_id
				AND status = :status'
		);
		$statement->execute([
			'user_id' => $userId,
			'status' => 'overdue',
		]);

		return (int) $statement->fetchColumn() > 0;
	}
}

if (!function_exists('ridex_fetch_bookable_vehicle_by_id')) {
	function ridex_fetch_bookable_vehicle_by_id(PDO $pdo, int $vehicleId): ?array
	{
		if ($vehicleId <= 0) {
			return null;
		}

		$vehicleStmt = $pdo->prepare(
			'SELECT v.*, c.name AS category_name
			 FROM vehicles v
			 INNER JOIN categories c ON c.id = v.category_id
			 WHERE v.id = :vehicle_id AND v.deleted_at IS NULL
			 LIMIT 1'
		);
		$vehicleStmt->execute([
			'vehicle_id' => $vehicleId,
		]);

		$vehicleRow = $vehicleStmt->fetch();
		return is_array($vehicleRow) ? $vehicleRow : null;
	}
}

if (!function_exists('ridex_fetch_booking_receipt_data')) {
	function ridex_fetch_booking_receipt_data(PDO $pdo, int $bookingId): ?array
	{
		if ($bookingId <= 0) {
			return null;
		}

		$receiptStmt = $pdo->prepare(
			'SELECT
				b.*,
				v.full_name AS vehicle_full_name,
				v.short_name AS vehicle_short_name,
				v.vehicle_type,
				v.price_per_day,
				p.transaction_time AS payment_transaction_time
			 FROM bookings b
			 INNER JOIN vehicles v ON v.id = b.vehicle_id
			 LEFT JOIN payments p ON p.id = (
				SELECT p2.id
				FROM payments p2
				WHERE p2.booking_id = b.id
				ORDER BY p2.transaction_time DESC, p2.id DESC
				LIMIT 1
			 )
			 WHERE b.id = :booking_id
			 LIMIT 1'
		);
		$receiptStmt->execute([
			'booking_id' => $bookingId,
		]);

		$receiptRow = $receiptStmt->fetch();
		return is_array($receiptRow) ? $receiptRow : null;
	}
}

if (!function_exists('ridex_format_booking_timeline')) {
	function ridex_format_booking_timeline(?DateTimeImmutable $dateTime): string
	{
		if (!($dateTime instanceof DateTimeImmutable)) {
			return 'N/A';
		}

		return $dateTime->format('M d, Y H:i A');
	}
}
