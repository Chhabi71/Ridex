<?php
/**
 * Purpose: Booking selection page with availability-based vehicle listing, sidebar filters, and price sorting.
 */

$bookingSearch = isset($bookingSearch) && is_array($bookingSearch) ? $bookingSearch : [];
$bookingSearchQuery = isset($bookingSearchQuery) && is_array($bookingSearchQuery) ? $bookingSearchQuery : [];
$selectedVehicleType = isset($selectedVehicleType) ? strtolower(trim((string) $selectedVehicleType)) : 'cars';
$selectedFilterTypes = isset($selectedFilterTypes) && is_array($selectedFilterTypes) ? $selectedFilterTypes : [$selectedVehicleType];
$selectedTransmissions = isset($selectedTransmissions) && is_array($selectedTransmissions) ? $selectedTransmissions : [];
$selectedSeatsMin = max(0, (int) ($selectedSeatsMin ?? 0));
$selectedPriceMin = max(0, (int) ($selectedPriceMin ?? 0));
$selectedPriceMax = max(0, (int) ($selectedPriceMax ?? 0));
$selectedSortPrice = strtolower(trim((string) ($selectedSortPrice ?? 'recommended')));
$bookingSelectVehicles = isset($bookingSelectVehicles) && is_array($bookingSelectVehicles) ? $bookingSelectVehicles : [];
$bookingNotice = trim((string) ($bookingNotice ?? ''));
$bookingNoAvailabilityMessage = trim((string) ($bookingNoAvailabilityMessage ?? ''));

$typeLabels = [
	'cars' => 'Car',
	'bikes' => 'Bike',
	'luxury' => 'Luxury',
];

$isTypeChecked = static function (string $type, array $selectedTypes): bool {
	return in_array($type, $selectedTypes, true);
};

$isTransmissionChecked = static function (string $transmission, array $selectedTransmissions): bool {
	return in_array($transmission, $selectedTransmissions, true);
};

$formatAmount = static function ($amount): string {
	return 'NRs ' . number_format((float) $amount, 2);
};

$resetQuery = array_merge(
	[
		'page' => 'booking-select',
		'vehicle_type' => $selectedVehicleType,
	],
	$bookingSearchQuery
);
$resetUrl = 'index.php?' . http_build_query($resetQuery);
?>

<section class="booking-select-page" aria-label="Vehicle selection and filters">
	<div class="booking-select-layout">
		<aside class="booking-filter" aria-label="Vehicle filters">
			<form id="booking-select-filter-form" class="booking-filter__form" method="get" action="index.php">
				<input type="hidden" name="page" value="booking-select" />
				<input type="hidden" name="vehicle_type" value="<?= htmlspecialchars($selectedVehicleType, ENT_QUOTES, 'UTF-8') ?>" />
				<?php foreach ($bookingSearchQuery as $queryKey => $queryValue): ?>
					<input
						type="hidden"
						name="<?= htmlspecialchars($queryKey, ENT_QUOTES, 'UTF-8') ?>"
						value="<?= htmlspecialchars((string) $queryValue, ENT_QUOTES, 'UTF-8') ?>"
					/>
				<?php endforeach; ?>

				<header class="booking-filter__header">
					<h2 class="booking-filter__title">Filters</h2>
					<a class="booking-filter__reset" href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>">Reset All</a>
				</header>

				<div class="booking-filter__group">
					<p class="booking-filter__label">Vehicle Type</p>
					<label class="booking-filter__option">
						<input type="checkbox" name="types[]" value="cars" <?= $isTypeChecked('cars', $selectedFilterTypes) ? 'checked' : '' ?> />
						<span class="booking-filter__indicator" aria-hidden="true"></span>
						<span>Car</span>
					</label>
					<label class="booking-filter__option">
						<input type="checkbox" name="types[]" value="bikes" <?= $isTypeChecked('bikes', $selectedFilterTypes) ? 'checked' : '' ?> />
						<span class="booking-filter__indicator" aria-hidden="true"></span>
						<span>Bike</span>
					</label>
					<label class="booking-filter__option">
						<input type="checkbox" name="types[]" value="luxury" <?= $isTypeChecked('luxury', $selectedFilterTypes) ? 'checked' : '' ?> />
						<span class="booking-filter__indicator" aria-hidden="true"></span>
						<span>Luxury</span>
					</label>
				</div>

				<div class="booking-filter__group">
					<p class="booking-filter__label">Transmission</p>
					<label class="booking-filter__option">
						<input type="checkbox" name="transmissions[]" value="manual" <?= $isTransmissionChecked('manual', $selectedTransmissions) ? 'checked' : '' ?> />
						<span class="booking-filter__indicator" aria-hidden="true"></span>
						<span>Manual</span>
					</label>
					<label class="booking-filter__option">
						<input type="checkbox" name="transmissions[]" value="automatic" <?= $isTransmissionChecked('automatic', $selectedTransmissions) ? 'checked' : '' ?> />
						<span class="booking-filter__indicator" aria-hidden="true"></span>
						<span>Automatic</span>
					</label>
					<label class="booking-filter__option">
						<input type="checkbox" name="transmissions[]" value="hybrid" <?= $isTransmissionChecked('hybrid', $selectedTransmissions) ? 'checked' : '' ?> />
						<span class="booking-filter__indicator" aria-hidden="true"></span>
						<span>Hybrid</span>
					</label>
				</div>

				<div class="booking-filter__group">
					<div class="booking-filter__seats-head">
						<label class="booking-filter__label" for="booking-filter-seats">Seats</label>
						<strong class="booking-filter__seats-value" data-seats-slider-value><?= $selectedSeatsMin > 0 ? htmlspecialchars((string) $selectedSeatsMin, ENT_QUOTES, 'UTF-8') . '+' : 'Any' ?></strong>
					</div>
					<input
						id="booking-filter-seats"
						class="booking-filter__seats-slider"
						type="range"
						name="seats_min"
						min="0"
						max="8"
						step="1"
						value="<?= htmlspecialchars((string) $selectedSeatsMin, ENT_QUOTES, 'UTF-8') ?>"
						data-seats-slider
					/>
					<div class="booking-filter__seats-points" aria-hidden="true">
						<span class="booking-filter__seat-point"><i></i><em>2+</em></span>
						<span class="booking-filter__seat-point"><i></i><em>4+</em></span>
						<span class="booking-filter__seat-point"><i></i><em>5+</em></span>
						<span class="booking-filter__seat-point"><i></i><em>7+</em></span>
					</div>
				</div>

				<div class="booking-filter__group">
					<p class="booking-filter__label">Price Range</p>
					<div class="booking-filter__price-grid">
						<label class="booking-filter__price-field">
							<span>Min</span>
							<input class="booking-filter__price-input" type="number" min="0" name="price_min" value="<?= htmlspecialchars((string) $selectedPriceMin, ENT_QUOTES, 'UTF-8') ?>" placeholder="0" />
						</label>
						<label class="booking-filter__price-field">
							<span>Max</span>
							<input class="booking-filter__price-input" type="number" min="0" name="price_max" value="<?= htmlspecialchars((string) $selectedPriceMax, ENT_QUOTES, 'UTF-8') ?>" placeholder="0" />
						</label>
					</div>
					<button class="booking-filter__apply" type="submit">Apply</button>
				</div>
			</form>
		</aside>

		<div class="booking-select-results" aria-live="polite">
			<header class="booking-select-results__top">
				<div class="booking-select-results__summary">
					<?php $summaryType = $typeLabels[$selectedVehicleType] ?? 'Vehicle'; ?>
					<h1 class="booking-select-results__title"><?= htmlspecialchars($summaryType, ENT_QUOTES, 'UTF-8') ?> Selection</h1>
					<p class="booking-select-results__subtitle">Available vehicles for your selected dates.</p>
				</div>
				<div class="booking-select-sort" role="group" aria-label="Sort vehicles">
					<p class="booking-select-sort__label">Sort By</p>
					<label class="booking-select-sort__option">
						<input type="radio" name="sort_price" value="recommended" form="booking-select-filter-form" <?= $selectedSortPrice === 'recommended' ? 'checked' : '' ?> onchange="this.form.submit()" />
						<span>Recommended</span>
					</label>
					<label class="booking-select-sort__option">
						<input type="radio" name="sort_price" value="low" form="booking-select-filter-form" <?= $selectedSortPrice === 'low' ? 'checked' : '' ?> onchange="this.form.submit()" />
						<span>Low to High</span>
					</label>
					<label class="booking-select-sort__option">
						<input type="radio" name="sort_price" value="high" form="booking-select-filter-form" <?= $selectedSortPrice === 'high' ? 'checked' : '' ?> onchange="this.form.submit()" />
						<span>High to Low</span>
					</label>
				</div>
			</header>

			<?php if ($bookingNotice !== ''): ?>
				<div class="booking-select-alert" role="alert"><?= htmlspecialchars($bookingNotice, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>

			<?php if (!empty($bookingNoAvailabilityMessage)): ?>
				<div class="booking-select-empty" role="status"><?= htmlspecialchars($bookingNoAvailabilityMessage, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>

			<div class="booking-select-cards">
				<?php foreach ($bookingSelectVehicles as $vehicle): ?>
					<?php
					$vehicleId = (int) ($vehicle['id'] ?? 0);
					$vehicleType = strtolower(trim((string) ($vehicle['vehicle_type'] ?? $selectedVehicleType)));
					if (!in_array($vehicleType, ['cars', 'bikes', 'luxury'], true)) {
						$vehicleType = $selectedVehicleType;
					}
					$vehicleFullName = trim((string) ($vehicle['full_name'] ?? 'Vehicle'));
					$vehicleImagePath = trim((string) ($vehicle['image_path'] ?? 'images/vehicle-feature.png'));
					$vehicleSeats = (int) ($vehicle['number_of_seats'] ?? 0);
					$vehicleTransmission = trim((string) ($vehicle['transmission_type'] ?? 'N/A'));
					$vehicleAge = (int) ($vehicle['driver_age_requirement'] ?? 0);
					$vehicleFuel = trim((string) ($vehicle['fuel_type'] ?? 'N/A'));
					$vehicleMileageRaw = trim((string) ($vehicle['mileage_km_per_l'] ?? ''));
					$vehicleMileageValue = is_numeric($vehicleMileageRaw) ? (float) $vehicleMileageRaw : 0.0;
					$vehicleMileageLabel = $vehicleMileageValue > 0
						? number_format($vehicleMileageValue, 2, '.', '') . ' km/L'
						: 'Mileage N/A';
					$vehiclePlate = trim((string) ($vehicle['license_plate'] ?? 'N/A'));
					$vehiclePricePerDay = (int) ($vehicle['price_per_day'] ?? 0);
					$checkoutQuery = array_merge(
						[
							'page' => 'booking-checkout',
							'vehicle_id' => $vehicleId,
							'vehicle_type' => $vehicleType,
						],
						$bookingSearchQuery
					);
					$checkoutUrl = 'index.php?' . http_build_query($checkoutQuery);
					?>
					<article class="booking-select-card" aria-label="<?= htmlspecialchars($vehicleFullName, ENT_QUOTES, 'UTF-8') ?>">
						<div class="booking-select-card__media">
							<img
								src="<?= htmlspecialchars($vehicleImagePath, ENT_QUOTES, 'UTF-8') ?>"
								alt="<?= htmlspecialchars($vehicleFullName, ENT_QUOTES, 'UTF-8') ?>"
								class="booking-select-card__image"
								onerror="this.onerror=null;this.src='images/vehicle-feature.png';"
							/>
						</div>
						<div class="booking-select-card__body">
							<h2 class="booking-select-card__name"><?= htmlspecialchars($vehicleFullName, ENT_QUOTES, 'UTF-8') ?></h2>
							<?php if (isset($vehicle['recommendation_score'])): ?>
								<p class="recommendation-badge">
									<span class="material-symbols-rounded" aria-hidden="true">auto_awesome</span>
									Recommended Score: <?= htmlspecialchars((string) ((int) $vehicle['recommendation_score']), ENT_QUOTES, 'UTF-8') ?>
								</p>
								<?php if (!empty($vehicle['recommendation_reason'])): ?>
									<p class="recommendation-reason"><?= htmlspecialchars((string) $vehicle['recommendation_reason'], ENT_QUOTES, 'UTF-8') ?></p>
								<?php endif; ?>
							<?php endif; ?>
							<ul class="booking-select-card__meta" aria-label="Vehicle details">
								<li><span class="material-symbols-rounded" aria-hidden="true">person</span><span><?= htmlspecialchars(($vehicleSeats > 0 ? $vehicleSeats : 0) . ' Seats', ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">settings</span><span><?= htmlspecialchars(ucfirst(strtolower($vehicleTransmission)), ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">badge</span><span><?= htmlspecialchars(($vehicleAge > 0 ? $vehicleAge : 0) . '+ Years', ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">local_gas_station</span><span><?= htmlspecialchars(ucfirst(strtolower($vehicleFuel)), ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">speed</span><span><?= htmlspecialchars($vehicleMileageLabel, ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">id_card</span><span><?= htmlspecialchars($vehiclePlate, ENT_QUOTES, 'UTF-8') ?></span></li>
							</ul>
							<ul class="booking-select-card__benefits">
								<li><span class="material-symbols-rounded" aria-hidden="true">check</span><span>Unlimited mileage</span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">check</span><span>No deposit</span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">check</span><span>Free cancellation</span></li>
							</ul>
						</div>
						<div class="booking-select-card__cta">
							<p class="booking-select-card__price"><?= htmlspecialchars($formatAmount($vehiclePricePerDay), ENT_QUOTES, 'UTF-8') ?> <span>/ day</span></p>
							<a class="booking-select-card__select" href="<?= htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8') ?>">Select</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
