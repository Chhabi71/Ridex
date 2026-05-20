<?php
/**
 * Purpose: Booking checkout page showing trip summary and payment options.
 */

$checkoutVehicle = isset($checkoutVehicle) && is_array($checkoutVehicle) ? $checkoutVehicle : null;
$selectedVehicleType = isset($selectedVehicleType)
	? strtolower(trim((string) $selectedVehicleType))
	: 'cars';
$bookingSearch = isset($bookingSearch) && is_array($bookingSearch) ? $bookingSearch : [];
$bookingSearchQuery = isset($bookingSearchQuery) && is_array($bookingSearchQuery) ? $bookingSearchQuery : [];
$bookingPriceBreakdown = isset($bookingPriceBreakdown) && is_array($bookingPriceBreakdown) ? $bookingPriceBreakdown : [];
$bookingNotice = trim((string) ($bookingNotice ?? ''));
$bookingCheckoutPayNowDisabled = !empty($bookingCheckoutPayNowDisabled);
$checkoutBookingNumberPreview = trim((string) ($checkoutBookingNumberPreview ?? ''));

$vehicleId = (int) ($checkoutVehicle['id'] ?? 0);
$vehicleName = trim((string) ($checkoutVehicle['full_name'] ?? 'Vehicle'));
$vehicleShortName = trim((string) ($checkoutVehicle['short_name'] ?? $vehicleName));
$vehicleImagePath = trim((string) ($checkoutVehicle['image_path'] ?? 'images/vehicle-feature.png'));
$vehicleSeats = (int) ($checkoutVehicle['number_of_seats'] ?? 0);
$vehicleTransmission = trim((string) ($checkoutVehicle['transmission_type'] ?? 'N/A'));
$vehicleAge = (int) ($checkoutVehicle['driver_age_requirement'] ?? 0);
$vehicleFuel = trim((string) ($checkoutVehicle['fuel_type'] ?? 'N/A'));
$vehicleMileageRaw = trim((string) ($checkoutVehicle['mileage_km_per_l'] ?? ''));
$vehicleMileageValue = is_numeric($vehicleMileageRaw) ? (float) $vehicleMileageRaw : 0.0;
$vehicleMileageLabel = $vehicleMileageValue > 0
	? number_format($vehicleMileageValue, 2, '.', '') . ' km/L'
	: 'Mileage N/A';
$vehiclePlate = trim((string) ($checkoutVehicle['license_plate'] ?? 'N/A'));

$pickupLocation = trim((string) ($bookingSearch['pickup_location'] ?? 'N/A'));
$returnLocation = trim((string) ($bookingSearch['return_location'] ?? 'N/A'));
$pickupDate = trim((string) ($bookingSearch['pickup_date'] ?? ''));
$returnDate = trim((string) ($bookingSearch['return_date'] ?? ''));
$pickupTime = trim((string) ($bookingSearch['pickup_time'] ?? ''));
$returnTime = trim((string) ($bookingSearch['return_time'] ?? ''));
$pickupTimelineLocation = $pickupLocation;
$returnTimelineLocation = $returnLocation;
$pickupTimelineDateTime = trim($pickupDate . ' ' . $pickupTime);
$returnTimelineDateTime = trim($returnDate . ' ' . $returnTime);

$vehicleCategoryLabel = ucfirst(rtrim($selectedVehicleType, 's'));
if ($vehicleCategoryLabel === '') {
	$vehicleCategoryLabel = 'Car';
}

if ($checkoutBookingNumberPreview === '') {
	$checkoutBookingNumberPreview = '#RX-' . str_pad((string) max(1, $vehicleId), 4, '0', STR_PAD_LEFT);
}

$totalDays = (int) ($bookingPriceBreakdown['total_days'] ?? 1);
$pricePerDay = (int) ($bookingPriceBreakdown['price_per_day'] ?? 0);
$priceForDays = (int) ($bookingPriceBreakdown['price_for_days'] ?? 0);
$dropCharge = (int) ($bookingPriceBreakdown['drop_charge'] ?? 20);
$taxesAndFees = (int) ($bookingPriceBreakdown['taxes_and_fees'] ?? 0);
$totalAmount = (int) ($bookingPriceBreakdown['total_amount'] ?? 0);

$formatAmount = static function ($amount): string {
	return 'NRs ' . number_format((float) $amount, 2);
};

$payOnArrivalPostData = array_merge(
	[
		'action' => 'user-booking-create',
		'vehicle_id' => $vehicleId,
		'vehicle_type' => $selectedVehicleType,
		'payment_method' => 'pay_on_arrival',
	],
	$bookingSearchQuery
);

// Khalti post data — same as pay on arrival but payment_method = khalti
$khaltiPostData = array_merge(
	[
		'action' => 'user-booking-create',
		'vehicle_id' => $vehicleId,
		'vehicle_type' => $selectedVehicleType,
		'payment_method' => 'khalti',
	],
	$bookingSearchQuery
);

?>

<section class="booking-checkout" aria-label="Booking checkout">
	<?php if ($bookingNotice !== ''): ?>
		<div class="booking-checkout__alert" role="alert">
			<?= htmlspecialchars($bookingNotice, ENT_QUOTES, 'UTF-8') ?>
			<?php if ($bookingCheckoutPayNowDisabled): ?>
				<br><strong>Booking is locked until both email verification and admin ID-photo verification are complete.</strong>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if (!is_array($checkoutVehicle)): ?>
		<p class="booking-checkout__empty">Selected vehicle is unavailable.</p>
	<?php else: ?>
		<div class="booking-checkout__shell">
			<div class="booking-checkout__grid">
				<article class="booking-checkout-card" aria-label="Booking details">
					<header class="booking-checkout-card__header">
						<p class="booking-checkout-card__booking-number">Booking Number: <?= htmlspecialchars($checkoutBookingNumberPreview, ENT_QUOTES, 'UTF-8') ?></p>
					</header>

					<div class="booking-checkout-card__vehicle-row">
						<div class="booking-checkout-card__image-wrap">
							<img
								src="<?= htmlspecialchars($vehicleImagePath, ENT_QUOTES, 'UTF-8') ?>"
								alt="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
								class="booking-checkout-card__image"
								onerror="this.onerror=null;this.src='images/vehicle-feature.png';"
							/>
						</div>
						<div class="booking-checkout-card__vehicle-info">
							<p class="booking-checkout-card__vehicle-category"><?= htmlspecialchars($vehicleCategoryLabel, ENT_QUOTES, 'UTF-8') ?></p>
							<h2 class="booking-checkout-card__vehicle-name"><?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?></h2>
							<ul class="booking-checkout-card__meta" aria-label="Vehicle attributes">
								<li><span class="material-symbols-rounded" aria-hidden="true">person</span><span><?= htmlspecialchars(($vehicleSeats > 0 ? $vehicleSeats : 0) . ' Seats', ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">settings</span><span><?= htmlspecialchars(ucfirst(strtolower($vehicleTransmission)), ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">badge</span><span><?= htmlspecialchars(($vehicleAge > 0 ? $vehicleAge : 0) . '+ Years', ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">local_gas_station</span><span><?= htmlspecialchars(ucfirst(strtolower($vehicleFuel)), ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">speed</span><span><?= htmlspecialchars($vehicleMileageLabel, ENT_QUOTES, 'UTF-8') ?></span></li>
								<li><span class="material-symbols-rounded" aria-hidden="true">id_card</span><span><?= htmlspecialchars($vehiclePlate, ENT_QUOTES, 'UTF-8') ?></span></li>
							</ul>
						</div>
					</div>

					<div class="booking-checkout-card__timeline">
						<div class="booking-checkout-card__timeline-column">
							<p class="booking-checkout-card__timeline-label">Pickup</p>
							<p class="booking-checkout-card__timeline-line">
								<strong class="booking-checkout-card__timeline-spot"><?= htmlspecialchars($pickupTimelineLocation, ENT_QUOTES, 'UTF-8') ?></strong>
								<span class="booking-checkout-card__timeline-datetime"><?= htmlspecialchars($pickupTimelineDateTime, ENT_QUOTES, 'UTF-8') ?></span>
							</p>
						</div>
						<div class="booking-checkout-card__timeline-column">
							<p class="booking-checkout-card__timeline-label">Return</p>
							<p class="booking-checkout-card__timeline-line">
								<strong class="booking-checkout-card__timeline-spot"><?= htmlspecialchars($returnTimelineLocation, ENT_QUOTES, 'UTF-8') ?></strong>
								<span class="booking-checkout-card__timeline-datetime"><?= htmlspecialchars($returnTimelineDateTime, ENT_QUOTES, 'UTF-8') ?></span>
							</p>
						</div>
					</div>
				</article>

				<aside class="booking-payment-card" aria-label="Payment options">
					<h2 class="booking-payment-card__title"><span>Total</span> <?= htmlspecialchars($formatAmount($totalAmount), ENT_QUOTES, 'UTF-8') ?></h2>

					<div class="booking-payment-card__details">
						<h3 class="booking-payment-card__details-title">Price details</h3>
						<div class="booking-payment-card__line"><span>Price per day</span><span><?= htmlspecialchars($formatAmount($pricePerDay), ENT_QUOTES, 'UTF-8') ?></span></div>
						<div class="booking-payment-card__line"><span>Price for <?= htmlspecialchars((string) $totalDays, ENT_QUOTES, 'UTF-8') ?> day<?= $totalDays === 1 ? '' : 's' ?></span><span><?= htmlspecialchars($formatAmount($priceForDays), ENT_QUOTES, 'UTF-8') ?></span></div>
						<div class="booking-payment-card__line"><span>Drop charge</span><span><?= htmlspecialchars($formatAmount($dropCharge), ENT_QUOTES, 'UTF-8') ?></span></div>
						<div class="booking-payment-card__line"><span>Taxes &amp; Fees</span><span><?= htmlspecialchars($formatAmount($taxesAndFees), ENT_QUOTES, 'UTF-8') ?></span></div>
						<div class="booking-payment-card__total"><span><?= htmlspecialchars($formatAmount($totalAmount), ENT_QUOTES, 'UTF-8') ?></span></div>
					</div>

					<div class="booking-payment-card__actions">
						<!-- Pay on Arrival -->
						<form method="post" action="index.php" class="booking-payment-card__pay-arrival-form">
							<?php foreach ($payOnArrivalPostData as $postKey => $postValue): ?>
								<input
									type="hidden"
									name="<?= htmlspecialchars((string) $postKey, ENT_QUOTES, 'UTF-8') ?>"
									value="<?= htmlspecialchars((string) $postValue, ENT_QUOTES, 'UTF-8') ?>"
								/>
							<?php endforeach; ?>
							<button class="booking-payment-card__pay-arrival" type="submit" <?= $bookingCheckoutPayNowDisabled ? 'disabled aria-disabled="true"' : '' ?>>Pay on Arrival</button>
						</form>

						<!-- Pay Now with Khalti -->
						<form method="post" action="index.php" class="booking-payment-card__pay-now-form">
							<?php foreach ($khaltiPostData as $postKey => $postValue): ?>
								<input
									type="hidden"
									name="<?= htmlspecialchars((string) $postKey, ENT_QUOTES, 'UTF-8') ?>"
									value="<?= htmlspecialchars((string) $postValue, ENT_QUOTES, 'UTF-8') ?>"
								/>
							<?php endforeach; ?>
							<button class="booking-payment-card__pay-now" type="submit" <?= $bookingCheckoutPayNowDisabled ? 'disabled aria-disabled="true"' : '' ?>>Pay Now</button>
						</form>
					</div>
				</aside>
			</div>
		</div>

		<div class="booking-checkout__fee-notice">
			<p class="booking-checkout__fee-text">
				Late fee: <strong>NRs 10/h</strong> applicable after scheduled return.*
			</p>
		</div>
	<?php endif; ?>
</section>