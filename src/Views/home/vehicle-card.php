<?php
/**
 * Purpose: Partial for displaying a vehicle card with key info.
*/

$vehicle = isset($vehicle) && is_array($vehicle) ? $vehicle : null;

if ($vehicle === null) {
	return;
}

$fullName = (string) ($vehicle['full_name'] ?? 'Vehicle');
$shortNameRaw = trim((string) ($vehicle['short_name'] ?? ''));
$shortName = $shortNameRaw !== '' ? $shortNameRaw : $fullName;
$imagePathRaw = trim((string) ($vehicle['image_path'] ?? ''));
$imagePath = $imagePathRaw !== '' ? $imagePathRaw : 'images/vehicle-feature.png';
$pricePerDay = number_format((float) ($vehicle['price_per_day'] ?? 0), 2);
$vehicleTypeRaw = strtolower(trim((string) ($vehicle['vehicle_type'] ?? 'cars')));
$allowedVehicleTypes = ['cars', 'bikes', 'luxury'];
$vehicleType = in_array($vehicleTypeRaw, $allowedVehicleTypes, true) ? $vehicleTypeRaw : 'cars';
$vehicleId = (int) ($vehicle['id'] ?? 0);
$seatCount = (int) ($vehicle['number_of_seats'] ?? 0);
$seatLabel = $seatCount > 0 ? $seatCount . ' Seats' : 'Seats N/A';
$fuelRaw = trim((string) ($vehicle['fuel_type'] ?? 'N/A'));
$fuelLabel = $fuelRaw !== '' ? ucfirst($fuelRaw) : 'N/A';
$mileageRaw = trim((string) ($vehicle['mileage_km_per_l'] ?? ''));
$mileageValue = is_numeric($mileageRaw) ? (float) $mileageRaw : 0.0;
$mileageLabel = $mileageValue > 0
	? number_format($mileageValue, 1, '.', '') . ' km/L'
	: 'Mileage N/A';
$pickupLocationContext = trim((string) ($pickupLocation ?? ''));
$returnLocationContext = trim((string) ($returnLocation ?? ''));
$pickupDateContext = trim((string) ($pickupDate ?? ''));
$returnDateContext = trim((string) ($returnDate ?? ''));
$pickupTimeContext = trim((string) ($pickupTime ?? ''));
$returnTimeContext = trim((string) ($returnTime ?? ''));

$detailQuery = [
	'page' => 'vehicle-detail',
	'vehicle_type' => $vehicleType,
];

if ($vehicleId > 0) {
	$detailQuery['id'] = $vehicleId;
}

$detailUrl = 'index.php?' . http_build_query($detailQuery);
?>

<article class="vehicle-card">
	<a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="vehicle-image-link" aria-label="View details for <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">
		<img
			src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
			alt="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
			class="vehicle-image"
			onerror="this.onerror=null;this.src='images/vehicle-feature.png';"
		/>
	</a>
	<div class="vehicle-card__separator" aria-hidden="true"></div>
	<div class="vehicle-card__headline-row">
		<h3 class="vehicle-name"><?= htmlspecialchars($shortName, ENT_QUOTES, 'UTF-8') ?></h3>
	</div>
	<div class="vehicle-card__specs" aria-label="Vehicle specifications">
		<span class="vehicle-card__spec-pill"><?= htmlspecialchars($seatLabel, ENT_QUOTES, 'UTF-8') ?></span>
		<span class="vehicle-card__spec-pill"><?= htmlspecialchars($fuelLabel, ENT_QUOTES, 'UTF-8') ?></span>
		<span class="vehicle-card__spec-pill"><?= htmlspecialchars($mileageLabel, ENT_QUOTES, 'UTF-8') ?></span>
	</div>
	<div class="vehicle-footer">
		<div class="vehicle-price">$<?= htmlspecialchars($pricePerDay, ENT_QUOTES, 'UTF-8') ?> <span class="day-text">/ day</span></div>
		<button
			class="book-button"
			type="button"
			data-book-now-trigger
			data-modal-target="user-booking-confirm-modal"
			data-book-vehicle-id="<?= htmlspecialchars((string) $vehicleId, ENT_QUOTES, 'UTF-8') ?>"
			data-book-vehicle-type="<?= htmlspecialchars($vehicleType, ENT_QUOTES, 'UTF-8') ?>"
			data-book-pickup-location="<?= htmlspecialchars($pickupLocationContext, ENT_QUOTES, 'UTF-8') ?>"
			data-book-return-location="<?= htmlspecialchars($returnLocationContext, ENT_QUOTES, 'UTF-8') ?>"
			data-book-pickup-date="<?= htmlspecialchars($pickupDateContext, ENT_QUOTES, 'UTF-8') ?>"
			data-book-return-date="<?= htmlspecialchars($returnDateContext, ENT_QUOTES, 'UTF-8') ?>"
			data-book-pickup-time="<?= htmlspecialchars($pickupTimeContext, ENT_QUOTES, 'UTF-8') ?>"
			data-book-return-time="<?= htmlspecialchars($returnTimeContext, ENT_QUOTES, 'UTF-8') ?>"
		>
			Book Now
		</button>
	</div>
</article>
