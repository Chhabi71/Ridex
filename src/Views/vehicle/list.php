<?php
/**
 * Purpose: Vehicle listing results page with filters and pagination.
 * Website Section: Vehicle Catalog.
 * Developer Notes: Render search filters (category/price/seats/transmission), list vehicle-card partials, and show pagination controls.
 */

$vehicles = $vehicles ?? [];
$selectedVehicleType = $selectedVehicleType ?? 'cars';
$pickupLocation = $pickupLocation ?? '';
$returnLocation = $returnLocation ?? '';
$pickupDate = $pickupDate ?? '';
$returnDate = $returnDate ?? '';
$pickupTime = $pickupTime ?? '';
$returnTime = $returnTime ?? '';

$vehicleTypeLabels = [
	'cars' => 'Cars',
	'bikes' => 'Bikes',
	'luxury' => 'Luxury',
];

$selectedVehicleTypeLabel = $vehicleTypeLabels[$selectedVehicleType] ?? 'Cars';

$buildVehicleTypeUrl = static function (string $vehicleType) use (
	$pickupLocation,
	$returnLocation,
	$pickupDate,
	$returnDate,
	$pickupTime,
	$returnTime
): string {
	$query = [
		'page' => 'vehicles',
		'vehicle_type' => $vehicleType,
	];

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

	return 'index.php?' . http_build_query($query);
};

$carsUrl = $buildVehicleTypeUrl('cars');
$bikesUrl = $buildVehicleTypeUrl('bikes');
$luxuryUrl = $buildVehicleTypeUrl('luxury');

?>


<section class="category-section vehicle-catalog-section" aria-label="Vehicle search results">
	<div class="vehicle-catalog-header">
		<h1 class="vehicle-catalog-title"><?= htmlspecialchars($selectedVehicleTypeLabel, ENT_QUOTES, 'UTF-8') ?></h1>

		<div class="vehicle-catalog-controls">
			<a href="index.php?featured_type=<?= urlencode($selectedVehicleType) ?>#home-vehicle-category" class="vehicle-catalog-back" aria-label="Back to home categories">
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</a>

			<div class="category-tabs" role="tablist" aria-label="Vehicle categories">
				<a href="<?= htmlspecialchars($carsUrl, ENT_QUOTES, 'UTF-8') ?>" class="category-tab<?= $selectedVehicleType === 'cars' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $selectedVehicleType === 'cars' ? 'true' : 'false' ?>">Cars</a>
				<a href="<?= htmlspecialchars($bikesUrl, ENT_QUOTES, 'UTF-8') ?>" class="category-tab<?= $selectedVehicleType === 'bikes' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $selectedVehicleType === 'bikes' ? 'true' : 'false' ?>">Bikes</a>
				<a href="<?= htmlspecialchars($luxuryUrl, ENT_QUOTES, 'UTF-8') ?>" class="category-tab<?= $selectedVehicleType === 'luxury' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $selectedVehicleType === 'luxury' ? 'true' : 'false' ?>">Luxury</a>
			</div>
		</div>
	</div>

	<?php if (empty($vehicles)): ?>
		<p class="vehicle-results-empty">
			No "<?= htmlspecialchars(strtolower($selectedVehicleTypeLabel), ENT_QUOTES, 'UTF-8') ?>" vehicle available.
		</p>
	<?php else: ?>
		<div class="vehicle-grid">
			<?php foreach ($vehicles as $vehicle): ?>
				<?php include __DIR__ . '/../home/vehicle-card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
