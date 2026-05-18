<?php
/**
 * Purpose: Standalone booking engine used after confirming a vehicle from Book Now.
 */

$selectedVehicle = isset($selectedVehicle) && is_array($selectedVehicle) ? $selectedVehicle : null;
$selectedVehicleId = (int) ($selectedVehicleId ?? 0);
$selectedVehicleType = isset($selectedVehicleType)
	? strtolower(trim((string) $selectedVehicleType))
	: 'cars';
$bookingSearch = isset($bookingSearch) && is_array($bookingSearch) ? $bookingSearch : [];
$bookingNotice = trim((string) ($bookingNotice ?? ''));
$directUnavailable = !empty($directUnavailable);
$directSearchUrl = trim((string) ($directSearchUrl ?? 'index.php?page=booking-select'));
$flowStart = !empty($flowStart);

$vehicleName = trim((string) ($selectedVehicle['full_name'] ?? 'Selected Vehicle'));
$pickupLocationValue = trim((string) ($bookingSearch['pickup_location'] ?? ''));
$returnLocationValue = trim((string) ($bookingSearch['return_location'] ?? ''));
$pickupDateValue = trim((string) ($bookingSearch['pickup_date'] ?? ''));
$returnDateValue = trim((string) ($bookingSearch['return_date'] ?? ''));
$pickupTimeValue = trim((string) ($bookingSearch['pickup_time'] ?? ''));
$returnTimeValue = trim((string) ($bookingSearch['return_time'] ?? ''));
$sameReturnChecked = !empty($bookingSearch['same_return']);

?>

<section class="booking-standalone" aria-labelledby="booking-standalone-title">
	<header class="booking-standalone__header">
		<h1 class="booking-standalone__title" id="booking-standalone-title">Booking Engine</h1>
		<p class="booking-standalone__subtitle"><?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?></p>
	</header>

	<?php if ($bookingNotice !== ''): ?>
		<div class="booking-standalone__alert" role="alert"><?= htmlspecialchars($bookingNotice, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<section class="booking-engine booking-engine--standalone" role="search" aria-label="Direct booking engine">
		<form class="booking-engine__form" action="index.php" method="get" data-booking-flow-form="direct">
			<input type="hidden" name="page" value="booking-engine" />
			<input type="hidden" name="vehicle_id" value="<?= htmlspecialchars((string) $selectedVehicleId, ENT_QUOTES, 'UTF-8') ?>" />
			<input type="hidden" name="vehicle_type" value="<?= htmlspecialchars($selectedVehicleType, ENT_QUOTES, 'UTF-8') ?>" />
			<input type="hidden" name="attempt" value="1" />
			<input type="hidden" name="flow_start" value="<?= $flowStart ? '1' : '0' ?>" />

			<div class="booking-engine__fields booking-engine__fields--standalone" aria-live="polite">
				<div class="booking-field">
					<div class="booking-field__label-row">
						<label class="booking-field__label" for="pickup-location">Pickup &amp; Return</label>
					</div>
					<div class="booking-input" data-state="default">
						<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">location_on</span>
						<input id="pickup-location" name="pickup-location" type="text" placeholder="Pickup Location" value="<?= htmlspecialchars($pickupLocationValue, ENT_QUOTES, 'UTF-8') ?>" required />
					</div>
					<p class="booking-field__help"></p>
				</div>

				<div class="booking-field">
					<div class="booking-field__label-row">
						<span class="booking-field__label">&nbsp;</span>
						<label class="booking-same" for="same-return">
							<input type="checkbox" id="same-return" name="same-return" <?= $sameReturnChecked ? 'checked' : '' ?> />
							<span>Same return location</span>
						</label>
					</div>
					<div class="booking-input" data-state="default">
						<span class="booking-input__icon material-symbols-rounded" aria-hidden="true">location_on</span>
						<input id="return-location" name="return-location" type="text" placeholder="Return Location" value="<?= htmlspecialchars($returnLocationValue, ENT_QUOTES, 'UTF-8') ?>" required />
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
						<input id="pickup-date" name="pickup-date" type="text" placeholder="dd/mm/yyyy" autocomplete="off" value="<?= htmlspecialchars($pickupDateValue, ENT_QUOTES, 'UTF-8') ?>" required />
						<span class="booking-input__divider" aria-hidden="true"></span>
						<input id="pickup-time" name="pickup-time" type="text" placeholder="--:-- --" autocomplete="off" value="<?= htmlspecialchars($pickupTimeValue, ENT_QUOTES, 'UTF-8') ?>" required />
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
						<input id="return-date" name="return-date" type="text" placeholder="dd/mm/yyyy" autocomplete="off" value="<?= htmlspecialchars($returnDateValue, ENT_QUOTES, 'UTF-8') ?>" required />
						<span class="booking-input__divider" aria-hidden="true"></span>
						<input id="return-time" name="return-time" type="text" placeholder="--:-- --" autocomplete="off" value="<?= htmlspecialchars($returnTimeValue, ENT_QUOTES, 'UTF-8') ?>" required />
						<button class="booking-input__icon-button booking-input__icon-button--time" type="button" data-open-picker-for="return-time" aria-label="Choose return time">
							<span class="booking-input__time-icon material-symbols-rounded" aria-hidden="true">schedule</span>
						</button>
					</div>
					<p class="booking-field__help" id="return-date-help"></p>
				</div>

				<div class="booking-actions booking-actions--standalone">
					<?php if ($directUnavailable): ?>
						<a class="booking-search booking-search--link" href="<?= htmlspecialchars($directSearchUrl, ENT_QUOTES, 'UTF-8') ?>">Search</a>
					<?php else: ?>
						<button class="booking-search" type="button" data-booking-search-trigger>Book</button>
					<?php endif; ?>
				</div>
			</div>
		</form>
	</section>
</section>
