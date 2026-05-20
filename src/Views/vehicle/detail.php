<?php
/**
 * Purpose: Vehicle detail page presenting full specs, gallery, pricing, and booking CTA.
 * Website Section: Vehicle Catalog.
 * Developer Notes: Display vehicle attributes (seats, transmission, fuel), current status badge, pricing per day, and a booking call-to-action.
 */

$vehicle = isset($vehicle) && is_array($vehicle) ? $vehicle : null;
$vehicleImages = isset($vehicleImages) && is_array($vehicleImages) ? array_values($vehicleImages) : [];
$backUrl = (string) ($backUrl ?? 'index.php?page=vehicles');

if ($vehicle !== null) {
	$fullNameRaw = trim((string) ($vehicle['full_name'] ?? ''));
	$shortNameRaw = trim((string) ($vehicle['short_name'] ?? ''));
	$vehicleName = $shortNameRaw !== '' ? $shortNameRaw : ($fullNameRaw !== '' ? $fullNameRaw : 'Vehicle');
	$descriptionRaw = trim((string) ($vehicle['description'] ?? ''));
	$vehicleDescription = $descriptionRaw !== ''
		? $descriptionRaw
		: 'This vehicle is ready to deliver a smooth and safe ride with dependable performance and comfort.';
	$imagePathRaw = trim((string) ($vehicle['image_path'] ?? ''));
	$imagePath = $imagePathRaw !== '' ? $imagePathRaw : 'images/vehicle-feature.png';
	$pricePerDay = number_format((float) ($vehicle['price_per_day'] ?? 0), 2);
	$seatCount = (int) ($vehicle['number_of_seats'] ?? 0);
	$seatLabel = $seatCount > 0 ? $seatCount . ' Seats' : 'Seats N/A';
	$transmissionRaw = trim((string) ($vehicle['transmission_type'] ?? 'N/A'));
	$transmissionLabel = $transmissionRaw !== '' ? strtoupper($transmissionRaw) : 'N/A';
	$ageRequirement = (int) ($vehicle['driver_age_requirement'] ?? 0);
	$ageLabel = $ageRequirement > 0 ? $ageRequirement . '+ Years' : 'Age N/A';
	$fuelRaw = trim((string) ($vehicle['fuel_type'] ?? 'N/A'));
	$fuelLabel = $fuelRaw !== '' ? ucfirst($fuelRaw) : 'N/A';
	$mileageRaw = trim((string) ($vehicle['mileage_km_per_l'] ?? ''));
	$mileageValue = is_numeric($mileageRaw) ? (float) $mileageRaw : 0.0;
	$mileageLabel = $mileageValue > 0
		? number_format($mileageValue, 2, '.', '') . ' km/L'
		: 'Mileage N/A';
	$plateRaw = trim((string) ($vehicle['license_plate'] ?? ''));
	$licensePlateLabel = $plateRaw !== '' ? strtoupper($plateRaw) : 'Plate N/A';
	if (empty($vehicleImages)) {
		$vehicleImages = [$imagePath];
	}
}

?>


<section class="vehicle-detail-page" aria-label="Vehicle detail">
	<div class="vehicle-detail-top">
		<a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="vehicle-detail-back" aria-label="Back to vehicles">
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</a>
	</div>

	<?php if ($vehicle === null): ?>
		<p class="vehicle-results-empty">Vehicle not found.</p>
	<?php else: ?>
		<div class="vehicle-detail-hero vehicle-detail-hero--carousel" data-vehicle-gallery>
			<button class="vehicle-detail-gallery-nav vehicle-detail-gallery-nav--prev" type="button" aria-label="Previous photo" data-vehicle-gallery-prev>
				<span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>
			</button>
			<div class="vehicle-detail-gallery-viewport">
				<img
					src="<?= htmlspecialchars($vehicleImages[0] ?? $imagePath, ENT_QUOTES, 'UTF-8') ?>"
					alt="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
					class="vehicle-detail-image"
					data-vehicle-gallery-image
					onerror="this.onerror=null;this.src='images/vehicle-feature.png';"
				/>
			</div>
			<button class="vehicle-detail-gallery-nav vehicle-detail-gallery-nav--next" type="button" aria-label="Next photo" data-vehicle-gallery-next>
				<span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>
			</button>
		</div>
		<div class="vehicle-detail-gallery-dots" aria-label="Vehicle photos" data-vehicle-gallery-dots>
			<?php foreach ($vehicleImages as $index => $galleryImage): ?>
				<button
					type="button"
					class="vehicle-detail-gallery-dot<?= $index === 0 ? ' is-active' : '' ?>"
					aria-label="Show photo <?= $index + 1 ?>"
					data-vehicle-gallery-dot
					data-vehicle-gallery-index="<?= (int) $index ?>"
				></button>
			<?php endforeach; ?>
		</div>

		<div class="vehicle-detail-header-row">
			<div class="vehicle-detail-title-block">
				<h1 class="vehicle-detail-title"><?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?></h1>
				<ul class="vehicle-detail-meta" aria-label="Vehicle specifications">
					<li>
						<span class="material-symbols-rounded" aria-hidden="true">airline_seat_recline_normal</span>
						<span><?= htmlspecialchars($seatLabel, ENT_QUOTES, 'UTF-8') ?></span>
					</li>
					<li>
						<span class="material-symbols-rounded" aria-hidden="true">settings</span>
						<span><?= htmlspecialchars($transmissionLabel, ENT_QUOTES, 'UTF-8') ?></span>
					</li>
					<li>
						<span class="material-symbols-rounded" aria-hidden="true">person</span>
						<span><?= htmlspecialchars($ageLabel, ENT_QUOTES, 'UTF-8') ?></span>
					</li>
					<li>
						<span class="material-symbols-rounded" aria-hidden="true">local_gas_station</span>
						<span><?= htmlspecialchars($fuelLabel, ENT_QUOTES, 'UTF-8') ?></span>
					</li>
					<li>
						<span class="material-symbols-rounded" aria-hidden="true">speed</span>
						<span><?= htmlspecialchars($mileageLabel, ENT_QUOTES, 'UTF-8') ?></span>
					</li>
					<li>
						<span class="material-symbols-rounded" aria-hidden="true">badge</span>
						<span><?= htmlspecialchars($licensePlateLabel, ENT_QUOTES, 'UTF-8') ?></span>
					</li>
				</ul>
			</div>

			<div class="vehicle-detail-cta" aria-label="Pricing and booking">
				<div class="vehicle-price">NRs <?= htmlspecialchars($pricePerDay, ENT_QUOTES, 'UTF-8') ?> <span class="day-text">/ day</span></div>
				<button
					class="book-button"
					type="button"
					data-book-now-trigger
					data-modal-target="user-booking-confirm-modal"
					data-book-vehicle-id="<?= htmlspecialchars((string) ((int) ($vehicle['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
					data-book-vehicle-type="<?= htmlspecialchars($backVehicleType, ENT_QUOTES, 'UTF-8') ?>"
					data-book-pickup-location="<?= htmlspecialchars($pickupLocation, ENT_QUOTES, 'UTF-8') ?>"
					data-book-return-location="<?= htmlspecialchars($returnLocation, ENT_QUOTES, 'UTF-8') ?>"
					data-book-pickup-date="<?= htmlspecialchars($pickupDate, ENT_QUOTES, 'UTF-8') ?>"
					data-book-return-date="<?= htmlspecialchars($returnDate, ENT_QUOTES, 'UTF-8') ?>"
					data-book-pickup-time="<?= htmlspecialchars($pickupTime, ENT_QUOTES, 'UTF-8') ?>"
					data-book-return-time="<?= htmlspecialchars($returnTime, ENT_QUOTES, 'UTF-8') ?>"
				>
					Book Now
				</button>
			</div>
		</div>

		<div class="vehicle-detail-content-row">
			<ul class="vehicle-detail-benefits" aria-label="Vehicle benefits">
				<li>
					<span class="material-symbols-rounded" aria-hidden="true">check</span>
					<span>GPS Tracking</span>
				</li>
				<li>
					<span class="material-symbols-rounded" aria-hidden="true">check</span>
					<span>Verified Handover</span>
				</li>
				<li>
					<span class="material-symbols-rounded" aria-hidden="true">check</span>
					<span>Free Cancellation</span>
				</li>
			</ul>

			<p class="vehicle-detail-description"><?= nl2br(htmlspecialchars($vehicleDescription, ENT_QUOTES, 'UTF-8')) ?></p>
		</div>
	<?php endif; ?>
</section>

<?php if (is_array($vehicle) && count($vehicleImages) > 1): ?>
	<script>
		(() => {
			const gallery = document.querySelector('[data-vehicle-gallery]');
			if (!gallery) return;
			const image = gallery.querySelector('[data-vehicle-gallery-image]');
			const prevButton = gallery.querySelector('[data-vehicle-gallery-prev]');
			const nextButton = gallery.querySelector('[data-vehicle-gallery-next]');
			const dots = Array.from(document.querySelectorAll('[data-vehicle-gallery-dot]'));
			const images = <?= json_encode(array_values($vehicleImages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
			let currentIndex = 0;

			const render = () => {
				if (!image || images.length === 0) return;
				image.src = images[currentIndex];
				dots.forEach((dot, index) => {
					dot.classList.toggle('is-active', index === currentIndex);
				});
			};

			prevButton?.addEventListener('click', () => {
				currentIndex = (currentIndex - 1 + images.length) % images.length;
				render();
			});

			nextButton?.addEventListener('click', () => {
				currentIndex = (currentIndex + 1) % images.length;
				render();
			});

			dots.forEach((dot) => {
				dot.addEventListener('click', () => {
					const index = Number(dot.getAttribute('data-vehicle-gallery-index') || '0');
					if (Number.isFinite(index)) {
						currentIndex = index;
						render();
					}
				});
			});

			render();
		})();
	</script>
<?php endif; ?>
