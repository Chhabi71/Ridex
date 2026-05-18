<?php
/**
 * Purpose: Landing page view showing hero search, featured vehicles, and marketing sections.
*/

$featuredVehicles = $featuredVehicles ?? [];
$selectedHomeVehicleType = $selectedHomeVehicleType ?? 'cars';
$homeVehicleTypeLabels = [
	'cars' => 'Cars',
	'bikes' => 'Bikes',
	'luxury' => 'Luxury',
];
$selectedHomeVehicleTypeLabel = strtolower($homeVehicleTypeLabels[$selectedHomeVehicleType] ?? 'cars');
?>

<section class="hero-banner">
	<img src="images/hero-banner.jpg" alt="Ridex performance car" class="hero-banner__image" />
	<div class="hero-banner__content">
		<h1 class="hero-banner__title">Smooth rides<br />every time!</h1>
		<p class="hero-banner__subtitle">Drive Your Way</p>
		<button class="hero-banner__cta" type="button">Book Now</button>
	</div>
</section>

<section class="booking-engine" role="search" aria-labelledby="booking-engine-heading" aria-describedby="booking-engine-helper">
	<form class="booking-engine__form" action="index.php" method="get" data-booking-flow-form="home">
		<input type="hidden" name="page" value="booking-select" />
		<input type="hidden" id="vehicle-type-input" name="vehicle_type" value="cars" />
		<input type="hidden" name="flow_start" value="1" />

		<div class="booking-engine__tabs" role="tablist" aria-label="Vehicle type">
			<button class="booking-tab is-active" type="button" role="tab" aria-selected="true" tabindex="0" data-vehicle-type="cars">
				<span class="booking-tab__icon material-symbols-rounded" aria-hidden="true">directions_car</span>
				<span>Cars</span>
			</button>
			<button class="booking-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-vehicle-type="bikes">
				<span class="booking-tab__icon material-symbols-rounded" aria-hidden="true">two_wheeler</span>
				<span>Bikes</span>
			</button>
		</div>

		<div class="booking-engine__fields" aria-live="polite">
			<div class="booking-field">
				<div class="booking-field__label-row">
					<label class="booking-field__label" id="booking-engine-heading" for="pickup-location">Pickup &amp; Return</label>
				</div>
				<div class="booking-input" data-state="default">
					<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">location_on</span>
					<input id="pickup-location" name="pickup-location" type="text" placeholder="Pickup Location" aria-invalid="false" required />
				</div>
				<p class="booking-field__help" id="booking-engine-helper"></p>
			</div>

			<div class="booking-field">
				<div class="booking-field__label-row">
					<span class="booking-field__label">&nbsp;</span>
					<label class="booking-same" for="same-return">
						<input type="checkbox" id="same-return" name="same-return" />
						<span>Same return location</span>
					</label>
				</div>
				<div class="booking-input" data-state="default">
					<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">location_on</span>
					<input id="return-location" name="return-location" type="text" placeholder="Return Location" aria-invalid="false" aria-describedby="return-location-help" required />
				</div>
				<p class="booking-field__help" id="return-location-help"></p>
			</div>

			<div class="booking-field">
				<div class="booking-field__label-row">
					<label class="booking-field__label" for="pickup-date">Pickup Date</label>
				</div>
				<div class="booking-input" data-state="default">
					<button class="booking-input__icon-button" type="button" data-open-picker-for="pickup-date" aria-label="Choose pickup date">
						<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">calendar_month</span>
					</button>
					<input id="pickup-date" name="pickup-date" type="text" placeholder="dd/mm/yyyy" autocomplete="off" aria-invalid="false" required />
					<span class="booking-input__divider" aria-hidden="true"></span>
					<input id="pickup-time" name="pickup-time" type="text" placeholder="--:-- --" autocomplete="off" aria-label="Pickup time" required />
					<button class="booking-input__icon-button booking-input__icon-button--time" type="button" data-open-picker-for="pickup-time" aria-label="Choose pickup time">
						<span class="booking-input__time-icon material-symbols-rounded" aria-hidden="true">schedule</span>
					</button>
				</div>
				<p class="booking-field__help"></p>
			</div>

			<div class="booking-field">
				<div class="booking-field__label-row">
					<label class="booking-field__label" for="return-date">Return Date</label>
				</div>
				<div class="booking-input" data-state="default">
					<button class="booking-input__icon-button" type="button" data-open-picker-for="return-date" aria-label="Choose return date">
						<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">calendar_month</span>
					</button>
					<input id="return-date" name="return-date" type="text" placeholder="dd/mm/yyyy" autocomplete="off" aria-invalid="false" aria-describedby="return-date-help" required />
					<span class="booking-input__divider" aria-hidden="true"></span>
					<input id="return-time" name="return-time" type="text" placeholder="--:-- --" autocomplete="off" aria-label="Return time" required />
					<button class="booking-input__icon-button booking-input__icon-button--time" type="button" data-open-picker-for="return-time" aria-label="Choose return time">
						<span class="booking-input__time-icon material-symbols-rounded" aria-hidden="true">schedule</span>
					</button>
				</div>
				<p class="booking-field__help" id="return-date-help"></p>
			</div>

			<div class="booking-actions">
				<button class="booking-search" type="button" data-booking-search-trigger>Search</button>
			</div>
		</div>
	</form>
</section>

<section class="category-section" id="home-vehicle-category" aria-label="Featured vehicles by category">
	<div class="category-tabs" role="tablist" aria-label="Vehicle categories">
		<a href="index.php?featured_type=cars#home-vehicle-category" class="category-tab<?= $selectedHomeVehicleType === 'cars' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $selectedHomeVehicleType === 'cars' ? 'true' : 'false' ?>">Cars</a>
		<a href="index.php?featured_type=bikes#home-vehicle-category" class="category-tab<?= $selectedHomeVehicleType === 'bikes' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $selectedHomeVehicleType === 'bikes' ? 'true' : 'false' ?>">Bikes</a>
		<a href="index.php?featured_type=luxury#home-vehicle-category" class="category-tab<?= $selectedHomeVehicleType === 'luxury' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $selectedHomeVehicleType === 'luxury' ? 'true' : 'false' ?>">Luxury</a>
	</div>

	<?php if (empty($featuredVehicles)): ?>
		<p class="vehicle-results-empty">No <?= htmlspecialchars($selectedHomeVehicleTypeLabel, ENT_QUOTES, 'UTF-8') ?> vehicle available.</p>
	<?php else: ?>
		<div class="vehicle-grid">
			<?php foreach ($featuredVehicles as $vehicle): ?>
				<?php include __DIR__ . '/vehicle-card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="view-more">
		<a href="index.php?page=vehicles&amp;vehicle_type=<?= urlencode($selectedHomeVehicleType) ?>" class="view-more-link">View More
			<svg class="arrow-icon" viewBox="0 0 20 16" fill="none" aria-hidden="true">
				<path d="M1 8H18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
				<path d="M12 15L19 8L12 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
		</a>
	</div>
</section>
