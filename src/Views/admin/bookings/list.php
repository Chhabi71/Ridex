<?php
/**
 * Purpose: Admin bookings list with filters and bulk actions.
 * Website Section: Admin Booking Management.
 * Developer Notes: Render table of bookings with strict date-aware status logic so UI stays consistent with booking lifecycle rules.
 */

$adminBookings = isset($adminBookings) && is_array($adminBookings) ? $adminBookings : [];
$openBookingId = isset($openBookingId) ? (int) $openBookingId : 0;
$bookingSearchEndpoint = 'ajax/admin-bookings-search.php';
$currentDateTime = new DateTimeImmutable('now');

$parseDateTime = static function (?string $rawDate): ?DateTimeImmutable {
	$rawDate = trim((string) $rawDate);
	if ($rawDate === '') {
		return null;
	}

	try {
		return new DateTimeImmutable($rawDate);
	} catch (Throwable $exception) {
		return null;
	}
};

$formatBookingId = static function (array $booking): string {
	$bookingNumber = strtoupper(trim((string) ($booking['booking_number'] ?? '')));
	if ($bookingNumber !== '') {
		$normalized = ltrim($bookingNumber, '#');

		if (preg_match('/^([A-Z]{2})[-_\s]?(\d{4})$/', $normalized, $exactMatch) === 1) {
			return '#' . $exactMatch[1] . '-' . $exactMatch[2];
		}

		preg_match_all('/[A-Z]/', $normalized, $letters);
		$letterPool = implode('', $letters[0] ?? []);
		$prefix = substr(str_pad($letterPool, 2, 'X'), 0, 2);

		preg_match_all('/\d/', $normalized, $digits);
		$digitPool = implode('', $digits[0] ?? []);
		$serial = $digitPool !== ''
			? substr(str_pad($digitPool, 4, '0', STR_PAD_LEFT), -4)
			: str_pad((string) ((int) ($booking['id'] ?? 0)), 4, '0', STR_PAD_LEFT);

		return '#' . $prefix . '-' . $serial;
	}

	$bookingId = (int) ($booking['id'] ?? 0);
	if ($bookingId > 0) {
		return '#BK-' . str_pad((string) $bookingId, 4, '0', STR_PAD_LEFT);
	}

	return '#N/A';
};

$formatBookingDate = static function (?string $rawDate): string {
	$rawDate = trim((string) $rawDate);
	if ($rawDate === '') {
		return 'N/A';
	}

	try {
		return (new DateTimeImmutable($rawDate))->format('M d, Y');
	} catch (Throwable $exception) {
		return 'N/A';
	}
};

$formatDateTimeInputValue = static function (?string $rawDate): string {
	$rawDate = trim((string) $rawDate);
	if ($rawDate === '') {
		return '';
	}

	try {
		return (new DateTimeImmutable($rawDate))->format('Y-m-d H:i');
	} catch (Throwable $exception) {
		return '';
	}
};

$resolveVehicleName = static function (array $booking): string {
	$isVehicleUnavailable = (int) ($booking['vehicle_is_unavailable'] ?? 0) === 1;

	$fullName = trim((string) ($booking['vehicle_full_name'] ?? ''));
	if ($fullName !== '') {
		return $fullName;
	}

	$shortName = trim((string) ($booking['vehicle_short_name'] ?? ''));
	if ($shortName !== '') {
		return $shortName;
	}

	if ($isVehicleUnavailable) {
		return 'Deleted Vehicle';
	}

	$vehicleType = trim((string) ($booking['vehicle_type'] ?? ''));
	return $vehicleType !== '' ? ucfirst($vehicleType) : 'Vehicle';
};

$resolveCustomerName = static function (array $booking): string {
	$customerName = trim((string) ($booking['customer_name'] ?? ''));
	if ($customerName === '') {
		return 'Unknown';
	}

	$normalizedName = preg_replace('/\s+/', ' ', $customerName);
	$normalizedName = is_string($normalizedName) ? trim($normalizedName) : '';
	if ($normalizedName === '') {
		return 'Unknown';
	}

	return $normalizedName;
};

$formatCustomerPhone = static function (?string $phoneNumber): string {
	$phoneNumber = trim((string) $phoneNumber);
	if ($phoneNumber === '') {
		return 'N/A';
	}

	if (str_starts_with($phoneNumber, '+')) {
		return $phoneNumber;
	}

	$digitsOnly = preg_replace('/\D+/', '', $phoneNumber);
	$digitsOnly = is_string($digitsOnly) ? $digitsOnly : '';

	if (strlen($digitsOnly) === 13 && str_starts_with($digitsOnly, '977')) {
		return '+' . $digitsOnly;
	}

	if (strlen($digitsOnly) === 10 && str_starts_with($digitsOnly, '9')) {
		return '+977 ' . $digitsOnly;
	}

	if ($digitsOnly !== '') {
		return $digitsOnly;
	}

	return $phoneNumber;
};

$resolveEffectiveBookingStatus = static function (array $booking): string {
	$bookingStatus = strtolower(trim((string) ($booking['booking_status'] ?? '')));
	$hasReturnTime = trim((string) ($booking['return_time'] ?? '')) !== '';

	if ($hasReturnTime && $bookingStatus !== 'cancelled') {
		return 'completed';
	}

	if (in_array($bookingStatus, ['reserved', 'on_trip', 'overdue', 'completed', 'cancelled'], true)) {
		return $bookingStatus;
	}

	return 'reserved';
};

$vehicleStatusMap = [
	'available' => ['label' => 'Available', 'class' => 'admin-bookings__vehicle-badge--available'],
	'unavailable' => ['label' => 'Unavailable', 'class' => 'admin-bookings__vehicle-badge--unavailable'],
	'reserved' => ['label' => 'Reserved', 'class' => 'admin-bookings__vehicle-badge--reserved'],
	'on_trip' => ['label' => 'On Trip', 'class' => 'admin-bookings__vehicle-badge--on-trip'],
	'overdue' => ['label' => 'Over Due', 'class' => 'admin-bookings__vehicle-badge--overdue'],
	'ready' => ['label' => 'Ready', 'class' => 'admin-bookings__vehicle-badge--ready'],
	'maintenance' => ['label' => 'Maintenance', 'class' => 'admin-bookings__vehicle-badge--maintenance'],
];

$paymentStatusMap = [
	'paid' => [
		'label' => 'Paid',
		'class' => 'admin-bookings__payment--paid',
		'icon' => 'check_circle',
	],
	'pending' => [
		'label' => 'Pending',
		'class' => 'admin-bookings__payment--pending',
		'icon' => 'hourglass_top',
	],
	'cancelled' => [
		'label' => 'Cancelled',
		'class' => 'admin-bookings__payment--cancelled',
		'icon' => 'schedule',
	],
	'unpaid' => [
		'label' => 'Unpaid',
		'class' => 'admin-bookings__payment--unpaid',
		'icon' => 'do_not_disturb_on',
	],
	'refunded' => [
		'label' => 'Refunded',
		'class' => 'admin-bookings__payment--refunded',
		'icon' => 'sync',
	],
];

$bookingStatusMap = [
	'reserved' => [
		'label' => 'Reserved',
		'class' => 'admin-booking-read-modal__status-pill--reserved',
	],
	'on_trip' => [
		'label' => 'On Trip',
		'class' => 'admin-booking-read-modal__status-pill--on-trip',
	],
	'overdue' => [
		'label' => 'Overdue',
		'class' => 'admin-booking-read-modal__status-pill--overdue',
	],
	'completed' => [
		'label' => 'Completed',
		'class' => 'admin-booking-read-modal__status-pill--completed',
	],
	'cancelled' => [
		'label' => 'Cancelled',
		'class' => 'admin-booking-read-modal__status-pill--cancelled',
	],
];

$resolveLogicalStatuses = static function (array $booking): array {
	$bookingStatus = strtolower(trim((string) ($booking['booking_status'] ?? '')));
	$vehicleStatusRaw = strtolower(trim((string) ($booking['vehicle_current_status'] ?? '')));
	$isVehicleUnavailable = (int) ($booking['vehicle_is_unavailable'] ?? 0) === 1;
	$paymentStatusRaw = strtolower(trim((string) ($booking['payment_status'] ?? '')));
	$paymentMethod = strtolower(trim((string) ($booking['payment_method'] ?? '')));
	$isCancelledBooking = $bookingStatus === 'cancelled';

	if ($isCancelledBooking) {
		if ($paymentMethod === 'khalti') {
			$paymentStatus = $paymentStatusRaw === 'refunded' ? 'refunded' : 'cancelled';
		} elseif ($paymentMethod === 'pay_on_arrival') {
			$paymentStatus = in_array($paymentStatusRaw, ['pending', 'cancelled', 'unpaid', 'paid'], true)
				? $paymentStatusRaw
				: 'unpaid';
		} else {
			$paymentStatus = in_array($paymentStatusRaw, ['paid', 'pending', 'cancelled', 'refunded', 'unpaid'], true)
				? $paymentStatusRaw
				: 'unpaid';
		}
	} else {
		if (in_array($paymentStatusRaw, ['paid', 'pending', 'cancelled', 'unpaid', 'refunded'], true)) {
			$paymentStatus = $paymentStatusRaw;
		} elseif ($paymentMethod === 'khalti') {
			$paymentStatus = 'paid';
		} elseif ($paymentMethod === 'pay_on_arrival') {
			$paymentStatus = 'pending';
		} else {
			$paymentStatus = 'unpaid';
		}
	}

	$vehicleStatus = $isVehicleUnavailable
		? 'unavailable'
		: ($vehicleStatusRaw !== '' ? $vehicleStatusRaw : 'available');
	if (!in_array($vehicleStatus, ['available', 'unavailable', 'reserved', 'on_trip', 'overdue', 'maintenance', 'ready'], true)) {
		$vehicleStatus = 'available';
	}

	return [
		'vehicle_status' => $vehicleStatus,
		'payment_status' => $paymentStatus,
	];
};

$resolveVehicleStatus = static function (string $statusKey) use ($vehicleStatusMap): array {
	$statusKey = strtolower(trim($statusKey));
	if (isset($vehicleStatusMap[$statusKey])) {
		return $vehicleStatusMap[$statusKey];
	}

	$label = $statusKey !== '' ? ucwords(str_replace('_', ' ', $statusKey)) : 'Unknown';
	return [
		'label' => $label,
		'class' => 'admin-bookings__vehicle-badge--unknown',
	];
};

$resolvePaymentStatus = static function (string $statusKey) use ($paymentStatusMap): array {
	$statusKey = strtolower(trim($statusKey));
	if (isset($paymentStatusMap[$statusKey])) {
		return $paymentStatusMap[$statusKey];
	}

	$label = $statusKey !== '' ? ucwords(str_replace('_', ' ', $statusKey)) : 'Unknown';
	return [
		'label' => $label,
		'class' => 'admin-bookings__payment--unknown',
		'icon' => 'help',
	];
};

$resolveBookingStatus = static function (string $statusKey) use ($bookingStatusMap): array {
	$statusKey = strtolower(trim($statusKey));
	if (isset($bookingStatusMap[$statusKey])) {
		return $bookingStatusMap[$statusKey];
	}

	$label = $statusKey !== '' ? ucwords(str_replace('_', ' ', $statusKey)) : 'Unknown';
	return [
		'label' => $label,
		'class' => 'admin-booking-read-modal__status-pill--unknown',
	];
};

$formatTotal = static function ($amount): string {
	if (!is_numeric($amount)) {
		return 'NRs 0.00';
	}

	return 'NRs ' . number_format((float) $amount, 2);
};

?>

<section class="admin-bookings" aria-labelledby="admin-bookings-title">
	<div class="admin-dashboard__shell">
		<aside class="admin-sidebar" aria-label="Admin panel navigation">
			<nav class="admin-sidebar__nav" aria-label="Admin sections">
				<a class="admin-sidebar__link" href="index.php?page=admin-dashboard">Dashboard</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-manage-fleet">Manage Fleet</a>
				<a class="admin-sidebar__link is-active" href="index.php?page=admin-all-bookings" aria-current="page">All Bookings</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-live-tracking">Live Tracking</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-user-verifications">User Verification</a>
			</nav>
		</aside>

		<div class="admin-dashboard__content admin-bookings__content">
			<div class="admin-bookings__toolbar">
				<h1 class="admin-bookings__title" id="admin-bookings-title">All Bookings</h1>
				<label class="admin-bookings__search" for="admin-bookings-search-input">
					<span class="material-symbols-rounded admin-bookings__search-icon" aria-hidden="true">search</span>
					<input
						class="admin-bookings__search-input"
						type="search"
						id="admin-bookings-search-input"
						name="admin-bookings-search"
						placeholder="Search"
						autocomplete="off"
						data-admin-bookings-search-input
					/>
				</label>
			</div>

			<div class="admin-bookings__table-wrap">
				<div class="admin-bookings__table-scroll">
					<table class="admin-bookings__table" aria-label="All bookings table">
						<thead>
							<tr>
								<th scope="col">Booking ID</th>
								<th scope="col">Customer</th>
								<th scope="col">Vehicle</th>
								<th scope="col">Vehicle Status</th>
								<th scope="col">Payment Status</th>
								<th scope="col">Dates</th>
								<th scope="col">Total</th>
								<th scope="col">Action</th>
							</tr>
						</thead>
						<tbody data-admin-bookings-table-body data-bookings-search-endpoint="<?= htmlspecialchars($bookingSearchEndpoint, ENT_QUOTES, 'UTF-8') ?>">
							<?php if (!empty($adminBookings)): ?>
								<?php foreach ($adminBookings as $booking): ?>
									<?php
									$bookingDisplayId = $formatBookingId($booking);
									$bookingId = (int) ($booking['id'] ?? 0);
									$customerName = $resolveCustomerName($booking);
									$customerPhone = $formatCustomerPhone($booking['customer_phone'] ?? '');
									$customerEmail = trim((string) ($booking['customer_email'] ?? ''));
									$vehicleName = $resolveVehicleName($booking);
									$vehicleType = strtolower(trim((string) ($booking['vehicle_type'] ?? 'car')));
									$vehicleImage = trim((string) ($booking['vehicle_image'] ?? ''));
									if ($vehicleImage === '') {
										$vehicleImage = 'images/vehicle-feature.png';
									}
									$logicalStatuses = $resolveLogicalStatuses($booking);
									$effectiveBookingStatus = $resolveEffectiveBookingStatus($booking);
									$bookingStatusMeta = $resolveBookingStatus($effectiveBookingStatus);
									$vehicleStatusKey = strtolower(trim((string) ($logicalStatuses['vehicle_status'] ?? 'unknown')));
									if (!in_array($vehicleStatusKey, ['available', 'unavailable', 'reserved', 'on_trip', 'overdue', 'maintenance', 'ready'], true)) {
										$vehicleStatusKey = 'unknown';
									}
									$vehicleStatus = $resolveVehicleStatus($logicalStatuses['vehicle_status']);
									$paymentStatus = $resolvePaymentStatus($logicalStatuses['payment_status']);
									$bookingStatusRaw = strtolower(trim((string) ($booking['booking_status'] ?? '')));
									$paymentStatusKey = strtolower(trim((string) ($logicalStatuses['payment_status'] ?? 'unpaid')));
									$paymentStatusRaw = strtolower(trim((string) ($booking['payment_status'] ?? '')));
									$pickupDate = $formatBookingDate($booking['pickup_datetime'] ?? '');
									$returnDate = $formatBookingDate($booking['return_datetime'] ?? '');
									$pickupDateTime = $parseDateTime($booking['pickup_datetime'] ?? null);
									$returnDateTime = $parseDateTime($booking['return_datetime'] ?? null);
									$returnTimeDateTime = $parseDateTime($booking['return_time'] ?? null);
									$returnTimeInputValue = $formatDateTimeInputValue($booking['return_time'] ?? ($booking['return_datetime'] ?? null));
									if ($returnTimeDateTime instanceof DateTimeImmutable && $effectiveBookingStatus !== 'cancelled') {
										$effectiveBookingStatus = 'completed';
										$bookingStatusMeta = $resolveBookingStatus($effectiveBookingStatus);
									}
									$driversId = trim((string) ($booking['drivers_id'] ?? ''));
									if ($driversId === '') {
										$driversId = 'N/A';
									}

									if ($customerEmail === '') {
										$customerEmail = 'N/A';
									}

									$pricePerDay = is_numeric($booking['price_per_day'] ?? null) ? (float) $booking['price_per_day'] : 0.0;
									$totalAmountValue = is_numeric($booking['total_amount'] ?? null) ? (float) $booking['total_amount'] : 0.0;
									$lateFeeValue = is_numeric($booking['late_fee'] ?? null) ? max(0.0, (float) $booking['late_fee']) : 0.0;
									$durationDays = 1;
									if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
										$durationSeconds = max(0, $returnDateTime->getTimestamp() - $pickupDateTime->getTimestamp());
										$durationDays = max(1, (int) ceil($durationSeconds / 86400));
									}

									$durationPriceAmount = $pricePerDay > 0
										? $pricePerDay * $durationDays
										: $totalAmountValue;
									$durationPriceLabel = 'Price for ' . $durationDays . ($durationDays === 1 ? ' day' : ' days');
									$dropChargeAmount = 20.0;
									$hasActualReturnTime = $returnTimeDateTime instanceof DateTimeImmutable;
									$isReturnedBooking = $hasActualReturnTime || $bookingStatusRaw === 'completed';
									$canCompleteReturn = in_array($effectiveBookingStatus, ['on_trip', 'overdue'], true)
										&& !$isReturnedBooking;
									$lateFeeNotApplicable = in_array($effectiveBookingStatus, ['reserved', 'cancelled'], true)
										|| $paymentStatusKey === 'unpaid';
									$effectiveLateFeeAmount = $lateFeeNotApplicable ? 0.0 : $lateFeeValue;
									$taxesFeesAmount = max(0.0, ($durationPriceAmount + $dropChargeAmount + $effectiveLateFeeAmount) * 0.13);
									$billingTotalAmount = $durationPriceAmount + $dropChargeAmount + $effectiveLateFeeAmount + $taxesFeesAmount;

									$returnTimeReadOnlyDisplay = 'N/A';
									if ($returnTimeDateTime instanceof DateTimeImmutable) {
										$returnTimeReadOnlyDisplay = $returnTimeDateTime->format('h:i A');
									}

									$canDeleteBooking = $isReturnedBooking
										|| ($bookingStatusRaw === 'cancelled' && in_array($paymentStatusRaw, ['refunded', 'unpaid'], true));
									$canApproveCancellation = $bookingStatusRaw === 'cancelled'
										&& in_array($paymentStatusRaw, ['cancelled', 'pending', 'paid'], true);
									$vehicleId = (int) ($booking['vehicle_id'] ?? 0);
									$vehicleGpsId = trim((string) ($booking['vehicle_gps_id'] ?? ''));
									$pickupLocation = trim((string) ($booking['pickup_location'] ?? ''));
									if ($pickupLocation === '') {
										$pickupLocation = 'Unavailable';
									}
									$returnLocation = trim((string) ($booking['return_location'] ?? ''));
									if ($returnLocation === '') {
										$returnLocation = 'Unavailable';
									}
									$gpsLatitude = is_numeric($booking['gps_latitude'] ?? null) ? (float) $booking['gps_latitude'] : null;
									$gpsLongitude = is_numeric($booking['gps_longitude'] ?? null) ? (float) $booking['gps_longitude'] : null;
									$gpsLatitudeValue = $gpsLatitude !== null ? number_format($gpsLatitude, 6, '.', '') : '';
									$gpsLongitudeValue = $gpsLongitude !== null ? number_format($gpsLongitude, 6, '.', '') : '';
									$hasGpsSignal = $gpsLatitude !== null
										&& $gpsLongitude !== null
										&& (abs($gpsLatitude) > 0.00001 || abs($gpsLongitude) > 0.00001);
									$trackLocationLabel = 'No GPS signal';
									if ($hasGpsSignal) {
										$trackLocationLabel = '' . number_format((float) $gpsLatitude, 5, '.', '') . ', ' . number_format((float) $gpsLongitude, 5, '.', '');
									}

									$trackMapQuery = '';
									if ($hasGpsSignal) {
										$trackMapQuery = number_format((float) $gpsLatitude, 6, '.', '') . ',' . number_format((float) $gpsLongitude, 6, '.', '');
									} elseif ($trackLocationLabel !== 'Unavailable') {
										$trackMapQuery = $trackLocationLabel;
									}
									$trackMapUrl = $trackMapQuery !== ''
										? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($trackMapQuery)
										: '';
									// Admin tracking should only be available for vehicles that are currently on rent.
									$canTrackBooking = in_array($effectiveBookingStatus, ['on_trip', 'overdue'], true)
										&& $vehicleStatusKey !== 'unavailable'
										&& $vehicleId > 0
										&& !$isReturnedBooking;
									$isTrackDisabled = !$canTrackBooking;
									$autoOpenBookingModal = $openBookingId > 0 && $openBookingId === $bookingId;
									$totalAmount = $formatTotal($booking['total_amount'] ?? 0);
									?>
									<tr data-booking-row-id="<?= $bookingId ?>">
										<td class="admin-bookings__booking-id"><?= htmlspecialchars($bookingDisplayId, ENT_QUOTES, 'UTF-8') ?></td>
										<td><?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?></td>
										<td><?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?></td>
										<td>
											<span class="admin-bookings__vehicle-badge <?= htmlspecialchars($vehicleStatus['class'], ENT_QUOTES, 'UTF-8') ?>">
												<?= htmlspecialchars($vehicleStatus['label'], ENT_QUOTES, 'UTF-8') ?>
											</span>
										</td>
										<td>
											<span class="admin-bookings__payment <?= htmlspecialchars($paymentStatus['class'], ENT_QUOTES, 'UTF-8') ?>">
												<span class="material-symbols-rounded admin-bookings__payment-icon" aria-hidden="true"><?= htmlspecialchars($paymentStatus['icon'], ENT_QUOTES, 'UTF-8') ?></span>
												<?= htmlspecialchars($paymentStatus['label'], ENT_QUOTES, 'UTF-8') ?>
											</span>
										</td>
										<td class="admin-bookings__dates">
											<div class="admin-bookings__dates-wrap">
												<span><?= htmlspecialchars($pickupDate, ENT_QUOTES, 'UTF-8') ?></span>
												<span><?= htmlspecialchars($returnDate, ENT_QUOTES, 'UTF-8') ?></span>
											</div>
										</td>
										<td class="admin-bookings__total"><?= htmlspecialchars($totalAmount, ENT_QUOTES, 'UTF-8') ?></td>
										<td>
											<button
												class="admin-bookings__view-btn"
												type="button"
												data-modal-target="admin-booking-read-modal"
												data-booking-id="<?= $bookingId ?>"
												data-booking-display-id="<?= htmlspecialchars($bookingDisplayId, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-status="<?= htmlspecialchars($effectiveBookingStatus, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-status-label="<?= htmlspecialchars($bookingStatusMeta['label'], ENT_QUOTES, 'UTF-8') ?>"
												data-booking-customer-name="<?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-customer-phone="<?= htmlspecialchars($customerPhone, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-customer-email="<?= htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-driver-id="<?= htmlspecialchars($driversId, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-vehicle-name="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-vehicle-type="<?= htmlspecialchars($vehicleType, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-vehicle-status="<?= htmlspecialchars($vehicleStatusKey, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-vehicle-status-label="<?= htmlspecialchars($vehicleStatus['label'], ENT_QUOTES, 'UTF-8') ?>"
												data-booking-vehicle-image="<?= htmlspecialchars($vehicleImage, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-vehicle-id="<?= $vehicleId ?>"
												data-booking-vehicle-gps-id="<?= htmlspecialchars($vehicleGpsId, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-pickup-location="<?= htmlspecialchars($pickupLocation, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-return-location="<?= htmlspecialchars($returnLocation, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-gps-latitude="<?= htmlspecialchars($gpsLatitudeValue, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-gps-longitude="<?= htmlspecialchars($gpsLongitudeValue, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-track-location-label="<?= htmlspecialchars($trackLocationLabel, ENT_QUOTES, 'UTF-8') ?>"
                                                
												data-booking-track-map-url="<?= htmlspecialchars($trackMapUrl, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-track-has-signal="<?= $hasGpsSignal ? 'true' : 'false' ?>"
												data-booking-pickup-date="<?= htmlspecialchars($pickupDate, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-pickup-datetime="<?= htmlspecialchars($booking['pickup_datetime'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
												data-booking-return-date="<?= htmlspecialchars($returnDate, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-return-datetime="<?= htmlspecialchars($booking['return_datetime'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
												data-booking-return-time-display="<?= htmlspecialchars($returnTimeReadOnlyDisplay, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-return-time-input="<?= htmlspecialchars($returnTimeInputValue, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-payment-status="<?= htmlspecialchars($paymentStatusKey, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-payment-label="<?= htmlspecialchars($paymentStatus['label'], ENT_QUOTES, 'UTF-8') ?>"
												data-booking-payment-icon="<?= htmlspecialchars($paymentStatus['icon'], ENT_QUOTES, 'UTF-8') ?>"
												data-booking-payment-class="<?= htmlspecialchars($paymentStatus['class'], ENT_QUOTES, 'UTF-8') ?>"
												data-booking-price-per-day="<?= htmlspecialchars(number_format($pricePerDay, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
												data-booking-duration-days="<?= $durationDays ?>"
												data-booking-duration-label="<?= htmlspecialchars($durationPriceLabel, ENT_QUOTES, 'UTF-8') ?>"
												data-booking-duration-price="<?= htmlspecialchars(number_format($durationPriceAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
												data-booking-drop-charge="<?= htmlspecialchars(number_format($dropChargeAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
												data-booking-late-fee="<?= htmlspecialchars(number_format($lateFeeValue, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
												data-booking-late-fee-na="<?= $lateFeeNotApplicable ? 'true' : 'false' ?>"
												data-booking-taxes-fees="<?= htmlspecialchars(number_format($taxesFeesAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
												data-booking-billing-total="<?= htmlspecialchars(number_format($billingTotalAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
												data-booking-can-track="<?= $canTrackBooking ? 'true' : 'false' ?>"
												data-booking-track-disabled="<?= $isTrackDisabled ? 'true' : 'false' ?>"
												data-booking-can-complete="<?= $canCompleteReturn ? 'true' : 'false' ?>"
												data-booking-can-approve-cancellation="<?= $canApproveCancellation ? 'true' : 'false' ?>"
												data-booking-can-delete="<?= $canDeleteBooking ? 'true' : 'false' ?>"
												data-booking-delete-label="<?= htmlspecialchars($bookingDisplayId, ENT_QUOTES, 'UTF-8') ?>"
												data-auto-open-booking="<?= $autoOpenBookingModal ? 'true' : 'false' ?>"
											>
												View
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td class="admin-bookings__empty" colspan="8">No bookings found.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>

<div class="menu-modal admin-booking-read-modal" id="admin-booking-read-modal" hidden aria-hidden="true" data-modal-id="admin-booking-read-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog admin-booking-read-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-booking-read-title">
		<header class="menu-modal__header admin-booking-read-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex booking details">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close booking details" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="admin-booking-read-modal__content">
			<div class="admin-booking-read-modal__headline">
				<div class="admin-booking-read-modal__booking-block">
					<p class="admin-booking-read-modal__booking-number">Booking Number: <span data-booking-read-number>#N/A</span></p>
					<p class="admin-booking-read-modal__customer" data-booking-read-customer>Unknown</p>
				</div>
				<span class="admin-booking-read-modal__payment admin-bookings__payment admin-bookings__payment--paid" data-booking-read-payment-badge>
					<span class="material-symbols-rounded admin-bookings__payment-icon" aria-hidden="true" data-booking-read-payment-icon>check_circle</span>
					<span data-booking-read-payment-label>Paid</span>
				</span>
			</div>

			<div class="admin-booking-read-modal__hero">
				<div class="admin-booking-read-modal__image-wrap">
					<img src="images/vehicle-feature.png" alt="Vehicle" class="admin-booking-read-modal__image" data-booking-read-image onerror="this.onerror=null;this.src='images/vehicle-feature.png';" />
				</div>

				<div class="admin-booking-read-modal__meta">
					<div class="admin-booking-read-modal__vehicle-headline">
						<h2 class="admin-booking-read-modal__type" id="admin-booking-read-title" data-booking-read-vehicle-type>Car</h2>
						<span class="admin-booking-read-modal__status-pill admin-booking-read-modal__status-pill--unknown" data-booking-read-status-pill>Unknown</span>
					</div>
					<p class="admin-booking-read-modal__vehicle-name" data-booking-read-vehicle-name>Vehicle</p>

					<div class="admin-booking-read-modal__billing" aria-label="Booking billing details">
						<p class="admin-booking-read-modal__billing-heading">Price details</p>
						<div class="admin-booking-read-modal__billing-row">
							<span>Price per day</span>
							<span data-booking-read-price-per-day>NRs 0.00</span>
						</div>
						<div class="admin-booking-read-modal__billing-row">
							<span data-booking-read-duration-label>Price for 1 day</span>
							<span data-booking-read-duration-price>NRs 0.00</span>
						</div>
						<div class="admin-booking-read-modal__billing-row">
							<span>Drop charge</span>
							<span data-booking-read-drop-charge>NRs 0.00</span>
						</div>
						<div class="admin-booking-read-modal__billing-row">
							<span>Late Fee</span>
							<span data-booking-read-late-fee>N/A</span>
						</div>
						<div class="admin-booking-read-modal__billing-row">
							<span>Taxes &amp; Fees</span>
							<span data-booking-read-taxes-fees>NRs 0.00</span>
						</div>
						<div class="admin-booking-read-modal__billing-row admin-booking-read-modal__billing-row--total">
							<span>Total</span>
							<strong data-booking-read-billing-total>NRs 0.00</strong>
						</div>
					</div>
				</div>
			</div>

			<table class="admin-booking-read-modal__table" aria-label="Booking details">
				<tbody>
					<tr>
						<th scope="row">Phone Number</th>
						<td data-booking-read-customer-phone>N/A</td>
					</tr>
					<tr>
						<th scope="row">Email</th>
						<td data-booking-read-customer-email>N/A</td>
					</tr>
					<tr>
						<th scope="row">Driver's ID</th>
						<td data-booking-read-driver-id>N/A</td>
					</tr>
					<tr>
						<th scope="row">Pickup Date</th>
						<td data-booking-read-pickup-date>N/A</td>
					</tr>
					<tr>
						<th scope="row">Return Date</th>
						<td data-booking-read-return-date>N/A</td>
					</tr>
					<tr>
						<th scope="row">Return Time</th>
						<td data-booking-read-return-time>N/A</td>
					</tr>
				</tbody>
			</table>

			<div class="admin-booking-read-modal__return-block">
				<form class="admin-booking-read-modal__return-form" id="admin-booking-complete-form" method="post" action="index.php" data-booking-complete-form hidden>
					<input type="hidden" name="action" value="admin-complete-booking" />
					<input type="hidden" name="booking_id" value="" data-booking-complete-id-input />

					<div class="admin-booking-read-modal__return-editor">
						<div class="booking-input admin-booking-read-modal__return-input" data-state="default">
							<button class="booking-input__icon-button" type="button" aria-label="Open return time picker" data-open-picker-for="admin-booking-return-time-input">
								<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">calendar_month</span>
							</button>
							<input class="admin-booking-read-modal__input" type="text" id="admin-booking-return-time-input" name="return_time" value="" data-booking-return-time-input autocomplete="off" placeholder="dd/mm/yyyy  --:-- --" required />
							<span class="booking-input__divider" aria-hidden="true"></span>
							<button class="booking-input__icon-button booking-input__icon-button--time" type="button" aria-label="Open return time picker" data-open-picker-for="admin-booking-return-time-input">
								<span class="booking-input__time-icon material-symbols-rounded" aria-hidden="true">schedule</span>
							</button>
						</div>
					</div>
				</form>

				<p class="admin-booking-read-modal__late-fee-note" data-booking-late-fee-preview hidden>Late fee: NRs 0.00 (0h x $10)</p>
			</div>

			<div class="admin-booking-read-modal__actions">
				<button class="admin-booking-read-modal__complete" type="submit" form="admin-booking-complete-form" data-booking-complete-submit hidden>Complete Return</button>
				<button class="admin-booking-read-modal__track" type="button" data-booking-track-action data-modal-target="admin-booking-track-modal">Track</button>

				<form class="admin-booking-read-modal__approve-form" method="post" action="index.php" data-booking-approve-form>
					<input type="hidden" name="action" value="admin-approve-booking-cancellation" />
					<input type="hidden" name="booking_id" value="" data-booking-approve-id-input />
					<button class="admin-booking-read-modal__approve" type="submit" data-booking-approve-action>Approve Cancellation</button>
				</form>

				<button
					class="admin-booking-read-modal__delete"
					type="button"
					data-modal-target="admin-delete-booking-modal"
					data-booking-delete-action
				>
					Delete
				</button>
			</div>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal admin-booking-track-modal" id="admin-booking-track-modal" hidden aria-hidden="true" data-modal-id="admin-booking-track-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog admin-booking-track-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-booking-track-title">
		<header class="menu-modal__header admin-booking-track-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex booking tracking details">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close booking tracking details" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="admin-booking-track-modal__content">
			<div class="admin-booking-track-modal__headline">
				<div class="admin-booking-track-modal__booking-block">
					<p class="admin-booking-track-modal__booking-number">Booking Number: <span data-booking-track-number>#N/A</span></p>
					<p class="admin-booking-track-modal__customer" data-booking-track-customer>Unknown</p>
				</div>
				<p class="admin-booking-track-modal__location" data-booking-track-location>
					<span class="material-symbols-rounded" aria-hidden="true">location_on</span>
					<span data-booking-track-location-label>Unavailable</span>
				</p>
			</div>

			<div class="admin-booking-track-modal__map" data-booking-track-map data-single-booking-gps-map data-feed-url="ajax/gps-live-feed.php">
				<div class="admin-booking-track-modal__route" data-booking-track-route></div>
				<span class="admin-booking-track-modal__pin admin-booking-track-modal__pin--pickup" data-booking-track-pickup-pin></span>
				<span class="admin-booking-track-modal__pin admin-booking-track-modal__pin--return" data-booking-track-return-pin></span>
				<p class="admin-booking-track-modal__map-empty" data-booking-track-map-empty hidden>Unavailable</p>
				<a class="admin-booking-track-modal__open-maps" href="#" target="_blank" rel="noopener noreferrer" data-booking-track-open-maps>Open in Maps</a>
			</div>

			<table class="admin-booking-track-modal__table" aria-label="Booking tracking details">
				<tbody>
					<tr>
						<th scope="row">Pickup Location</th>
						<td data-booking-track-pickup-location>Unavailable</td>
					</tr>
					<tr>
						<th scope="row">Return Location</th>
						<td data-booking-track-return-location>Unavailable</td>
					</tr>
                    
				</tbody>
			</table>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to booking details" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal admin-logout-modal" id="admin-delete-booking-modal" hidden aria-hidden="true" data-modal-id="admin-delete-booking-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog admin-logout-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-delete-booking-title">
		<header class="menu-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex delete booking confirmation">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close delete booking prompt" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="admin-logout-modal__content">
			<span class="material-symbols-rounded admin-logout-modal__icon" aria-hidden="true">delete</span>
			<p class="admin-logout-modal__text" id="admin-delete-booking-title">
				Are you sure you want to delete booking <span data-delete-booking-name>#N/A</span>? This action can't be undone.
			</p>

			<div class="admin-logout-modal__actions">
				<button class="admin-logout-modal__cancel" type="button" data-modal-back>Cancel</button>
				<form class="admin-logout-modal__form" method="post" action="index.php">
					<input type="hidden" name="action" value="admin-delete-booking" />
					<input type="hidden" name="booking_id" value="" data-delete-booking-id-input />
					<button class="admin-logout-modal__confirm" type="submit">Delete</button>
				</form>
			</div>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>
