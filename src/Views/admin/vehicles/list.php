<?php
/**
 * Purpose: Admin fleet list view with filters and bulk actions.
 * Website Section: Admin Fleet Management.
 * Developer Notes: Display table of vehicles with status, category, price, actions to edit/detail, and filters for status/category.
 */

// manage fleet: expected page state from route-level DB query.

$fleetMode = isset($fleetMode) ? trim((string) $fleetMode) : 'type';
$selectedFleetType = isset($selectedFleetType) ? trim((string) $selectedFleetType) : 'cars';
$selectedFleetStatus = isset($selectedFleetStatus) ? trim((string) $selectedFleetStatus) : 'reserved';
$fleetVehicles = isset($fleetVehicles) && is_array($fleetVehicles) ? $fleetVehicles : [];
$openReadVehicleId = isset($openReadVehicleId) ? (int) $openReadVehicleId : 0;

$typeTabs = [
	'cars' => 'Cars',
	'bikes' => 'Bikes',
	'luxury' => 'Luxury',
];

$statusTabs = [
	'reserved' => 'Reserved',
	'on_trip' => 'On Trip',
	'overdue' => 'Overdue',
	'maintenance' => 'Maintenance',
];

$buildFleetUrl = static function (string $mode, string $selected): string {
	$query = ['page' => 'admin-manage-fleet', 'fleet_mode' => $mode];
	if ($mode === 'status') {
		$query['fleet_status'] = $selected;
	} else {
		$query['fleet_type'] = $selected;
	}

	return 'index.php?' . http_build_query($query);
};

$resolveVehicleName = static function (array $vehicle): string {
	$shortName = trim((string) ($vehicle['short_name'] ?? ''));
	if ($shortName !== '') {
		return $shortName;
	}

	$fullName = trim((string) ($vehicle['full_name'] ?? ''));
	return $fullName !== '' ? $fullName : 'Vehicle';
};

$resolveVehicleImage = static function (array $vehicle): string {
	$imagePath = trim((string) ($vehicle['image_path'] ?? ''));
	if ($imagePath === '') {
		return 'images/vehicle-feature.png';
	}

	return $imagePath;
};

$resolveVehicleValue = static function (array $vehicle, string $key): string {
	return trim((string) ($vehicle[$key] ?? ''));
};

$fleetEmptyMessage = 'No available vehicles.';
if ($fleetMode === 'status') {
	$statusLabel = $statusTabs[$selectedFleetStatus] ?? 'Selected';
	$fleetEmptyMessage = 'No ' . strtolower($statusLabel) . ' vehicles.';
}

?>


<?php // manage fleet: primary page layout. ?>
<section class="admin-fleet" aria-labelledby="admin-fleet-title">
	<div class="admin-dashboard__shell">
		<aside class="admin-sidebar" aria-label="Admin panel navigation">
			<nav class="admin-sidebar__nav" aria-label="Admin sections">
				<a class="admin-sidebar__link" href="index.php?page=admin-dashboard">Dashboard</a>
				<a class="admin-sidebar__link is-active" href="index.php?page=admin-manage-fleet" aria-current="page">Manage Fleet</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-all-bookings">All Bookings</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-live-tracking">Live Tracking</a>
			</nav>
		</aside>

		<div class="admin-dashboard__content admin-fleet__content">
			<h1 class="admin-fleet__title" id="admin-fleet-title">Manage Fleet</h1>

			<?php // manage fleet: top filter/action controls. ?>
			<div class="admin-fleet__toolbar">
				<div class="admin-fleet__filters" role="tablist" aria-label="Manage fleet filters">
					<?php if ($fleetMode === 'status'): ?>
						<?php foreach ($statusTabs as $statusValue => $statusLabel): ?>
							<?php
							$isActiveStatus = ($selectedFleetStatus === $statusValue);
							$statusClass = 'admin-fleet__pill admin-fleet__pill--status-' . str_replace('_', '-', $statusValue);
							if ($isActiveStatus) {
								$statusClass .= ' is-active';
							}
							?>
							<a class="<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($buildFleetUrl('status', $statusValue), ENT_QUOTES, 'UTF-8') ?>" role="tab" aria-selected="<?= $isActiveStatus ? 'true' : 'false' ?>">
								<?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
							</a>
						<?php endforeach; ?>
					<?php else: ?>
						<?php foreach ($typeTabs as $typeValue => $typeLabel): ?>
							<?php $isActiveType = ($selectedFleetType === $typeValue); ?>
							<a class="admin-fleet__pill admin-fleet__pill--type<?= $isActiveType ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildFleetUrl('type', $typeValue), ENT_QUOTES, 'UTF-8') ?>" role="tab" aria-selected="<?= $isActiveType ? 'true' : 'false' ?>">
								<?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if ($fleetMode !== 'status'): ?>
					<div class="admin-fleet__actions">
						<a class="admin-fleet__pill admin-fleet__pill--outline-red" href="<?= htmlspecialchars($buildFleetUrl('status', 'reserved'), ENT_QUOTES, 'UTF-8') ?>">Unavailable</a>
						<button
							class="admin-fleet__pill admin-fleet__pill--fill-green"
							type="button"
							data-modal-target="admin-create-vehicle-modal"
							data-create-fleet-mode="<?= htmlspecialchars($fleetMode, ENT_QUOTES, 'UTF-8') ?>"
							data-create-fleet-type="<?= htmlspecialchars($selectedFleetType, ENT_QUOTES, 'UTF-8') ?>"
							data-create-vehicle-type="<?= htmlspecialchars($selectedFleetType, ENT_QUOTES, 'UTF-8') ?>"
						>
							Create
						</button>
					</div>
				<?php endif; ?>
			</div>

			<?php // manage fleet: vehicle cards sourced from DB. ?>
			<div class="admin-fleet__grid">
				<?php foreach ($fleetVehicles as $vehicle): ?>
					<?php
					$vehicleName = $resolveVehicleName($vehicle);
					$vehicleImage = $resolveVehicleImage($vehicle);
					$vehicleStatus = strtolower($resolveVehicleValue($vehicle, 'status'));
					$vehicleType = strtolower($resolveVehicleValue($vehicle, 'vehicle_type'));
					$vehicleSeats = $resolveVehicleValue($vehicle, 'number_of_seats');
					$vehicleTransmission = $resolveVehicleValue($vehicle, 'transmission_type');
					$vehicleFuel = $resolveVehicleValue($vehicle, 'fuel_type');
					$vehicleMileage = $resolveVehicleValue($vehicle, 'mileage_km_per_l');
					$vehicleMileageAttr = '';
					if ($vehicleMileage !== '') {
						$vehicleMileageAttr = is_numeric($vehicleMileage) ? number_format((float) $vehicleMileage, 2, '.', '') : trim((string) $vehicleMileage);
					}
					$vehiclePricePerDay = $resolveVehicleValue($vehicle, 'price_per_day');
					$vehicleAgeRequirement = $resolveVehicleValue($vehicle, 'driver_age_requirement');
					$vehiclePlate = $resolveVehicleValue($vehicle, 'license_plate');
					$vehicleGpsId = $resolveVehicleValue($vehicle, 'gps_id');
					$vehicleLastService = $resolveVehicleValue($vehicle, 'last_service_date');
					$vehicleDescription = $resolveVehicleValue($vehicle, 'description');
					$activeBookingId = (int) ($vehicle['active_booking_id'] ?? 0);
					$activeBookingNumber = $resolveVehicleValue($vehicle, 'active_booking_number');
					if ($activeBookingNumber === '' && $activeBookingId > 0) {
						$activeBookingNumber = '#BK-' . str_pad((string) $activeBookingId, 4, '0', STR_PAD_LEFT);
					}
					$activeBookingUserName = $resolveVehicleValue($vehicle, 'active_booking_user_name');
					$activeBookingUserPhone = $resolveVehicleValue($vehicle, 'active_booking_user_phone');
					$activePickupDatetime = $resolveVehicleValue($vehicle, 'active_pickup_datetime');
					$activeReturnDatetime = $resolveVehicleValue($vehicle, 'active_return_datetime');
					$activePaymentStatus = $resolveVehicleValue($vehicle, 'active_payment_status');
					$activeLateFee = $resolveVehicleValue($vehicle, 'active_late_fee');
					$gpsLatitude = $resolveVehicleValue($vehicle, 'gps_latitude');
					$gpsLongitude = $resolveVehicleValue($vehicle, 'gps_longitude');
					$upcomingPickupDatetime = $resolveVehicleValue($vehicle, 'upcoming_pickup_datetime');
					$totalReservations = $resolveVehicleValue($vehicle, 'total_reservations');
					$totalEarnings = $resolveVehicleValue($vehicle, 'total_earnings');
					$maintenanceIssueDescription = $resolveVehicleValue($vehicle, 'maintenance_issue_description');
					$maintenanceWorkshopName = $resolveVehicleValue($vehicle, 'maintenance_workshop_name');
					$maintenanceEstimateCompletionDate = $resolveVehicleValue($vehicle, 'maintenance_estimate_completion_date');
					$maintenanceServiceCost = $resolveVehicleValue($vehicle, 'maintenance_service_cost');
					$vehicleId = (int) ($vehicle['id'] ?? 0);
					$autoOpenReadModal = $openReadVehicleId > 0 && $openReadVehicleId === $vehicleId;
					$isMaintenanceVehicle = $vehicleStatus === 'maintenance';
					?>
					<article class="admin-fleet-card">
						<div class="admin-fleet-card__image-wrap">
							<?php // read modal reference: C:\Users\User\Downloads\Ridex includes the 5-status read examples, and available status has an extra maintenance icon. ?>
							<button
								class="admin-fleet-card__image-button"
								type="button"
								data-modal-target="admin-vehicle-read-modal"
								data-read-vehicle-id="<?= $vehicleId ?>"
								data-read-vehicle-name="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-full-name="<?= htmlspecialchars($resolveVehicleValue($vehicle, 'full_name'), ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-type="<?= htmlspecialchars($vehicleType, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-status="<?= htmlspecialchars($vehicleStatus, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-image="<?= htmlspecialchars($vehicleImage, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-seats="<?= htmlspecialchars($vehicleSeats, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-transmission="<?= htmlspecialchars($vehicleTransmission, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-price="<?= htmlspecialchars($vehiclePricePerDay, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-age="<?= htmlspecialchars($vehicleAgeRequirement, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-fuel="<?= htmlspecialchars($vehicleFuel, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-mileage="<?= htmlspecialchars($vehicleMileageAttr, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-plate="<?= htmlspecialchars($vehiclePlate, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-gps-id="<?= htmlspecialchars($vehicleGpsId, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-last-service="<?= htmlspecialchars($vehicleLastService, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-description="<?= htmlspecialchars($vehicleDescription, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-number="<?= htmlspecialchars($activeBookingNumber, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-id="<?= htmlspecialchars((string) max(0, $activeBookingId), ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-user-name="<?= htmlspecialchars($activeBookingUserName, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-user-phone="<?= htmlspecialchars($activeBookingUserPhone, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-pickup="<?= htmlspecialchars($activePickupDatetime, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-return="<?= htmlspecialchars($activeReturnDatetime, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-payment-status="<?= htmlspecialchars($activePaymentStatus, ENT_QUOTES, 'UTF-8') ?>"
								data-read-booking-late-fee="<?= htmlspecialchars($activeLateFee, ENT_QUOTES, 'UTF-8') ?>"
								data-read-gps-latitude="<?= htmlspecialchars($gpsLatitude, ENT_QUOTES, 'UTF-8') ?>"
								data-read-gps-longitude="<?= htmlspecialchars($gpsLongitude, ENT_QUOTES, 'UTF-8') ?>"
								data-read-upcoming-pickup="<?= htmlspecialchars($upcomingPickupDatetime, ENT_QUOTES, 'UTF-8') ?>"
								data-read-total-reservations="<?= htmlspecialchars($totalReservations, ENT_QUOTES, 'UTF-8') ?>"
								data-read-total-earnings="<?= htmlspecialchars($totalEarnings, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-issue="<?= htmlspecialchars($maintenanceIssueDescription, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-workshop="<?= htmlspecialchars($maintenanceWorkshopName, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-estimate="<?= htmlspecialchars($maintenanceEstimateCompletionDate, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-cost="<?= htmlspecialchars($maintenanceServiceCost, ENT_QUOTES, 'UTF-8') ?>"
								data-delete-fleet-mode="<?= htmlspecialchars($fleetMode, ENT_QUOTES, 'UTF-8') ?>"
								data-delete-fleet-type="<?= htmlspecialchars($selectedFleetType, ENT_QUOTES, 'UTF-8') ?>"
								data-delete-fleet-status="<?= htmlspecialchars($selectedFleetStatus, ENT_QUOTES, 'UTF-8') ?>"
								data-auto-open-read="<?= $autoOpenReadModal ? 'true' : 'false' ?>"
							>
								<img
									src="<?= htmlspecialchars($vehicleImage, ENT_QUOTES, 'UTF-8') ?>"
									alt="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
									class="admin-fleet-card__image"
									onerror="this.onerror=null;this.src='images/vehicle-feature.png';"
								/>
							</button>
						</div>

						<h2 class="admin-fleet-card__name"><?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?></h2>

						<?php // manage fleet delete: opens delete confirmation modal with selected vehicle context. ?>
						<div class="admin-fleet-card__actions<?= $fleetMode === 'status' ? ' admin-fleet-card__actions--single' : '' ?>">
							<button
								class="admin-fleet-card__btn admin-fleet-card__btn--edit"
								type="button"
								<?= $isMaintenanceVehicle ? 'data-modal-target="admin-maintenance-edit-modal"' : 'data-modal-target="admin-edit-vehicle-modal"' ?>
								data-maintenance-edit-trigger
								data-edit-vehicle-id="<?= $vehicleId ?>"
								data-edit-vehicle-type="<?= htmlspecialchars($vehicleType, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-full-name="<?= htmlspecialchars($resolveVehicleValue($vehicle, 'full_name'), ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-short-name="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-price="<?= htmlspecialchars($vehiclePricePerDay, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-driver-age="<?= htmlspecialchars($vehicleAgeRequirement, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-seats="<?= htmlspecialchars($vehicleSeats, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-transmission="<?= htmlspecialchars($vehicleTransmission, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-fuel="<?= htmlspecialchars($vehicleFuel, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-mileage="<?= htmlspecialchars($vehicleMileageAttr, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-license-plate="<?= htmlspecialchars($vehiclePlate, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-gps-id="<?= htmlspecialchars($vehicleGpsId, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-status="<?= htmlspecialchars($vehicleStatus, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-last-service="<?= htmlspecialchars($vehicleLastService, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-description="<?= htmlspecialchars($vehicleDescription, ENT_QUOTES, 'UTF-8') ?>"
								data-edit-vehicle-image="<?= htmlspecialchars($vehicleImage, ENT_QUOTES, 'UTF-8') ?>"
								data-read-vehicle-id="<?= $vehicleId ?>"
								data-read-vehicle-status="<?= htmlspecialchars($vehicleStatus, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-issue="<?= htmlspecialchars($maintenanceIssueDescription, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-workshop="<?= htmlspecialchars($maintenanceWorkshopName, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-estimate="<?= htmlspecialchars($maintenanceEstimateCompletionDate, ENT_QUOTES, 'UTF-8') ?>"
								data-read-maintenance-cost="<?= htmlspecialchars($maintenanceServiceCost, ENT_QUOTES, 'UTF-8') ?>"
								data-delete-fleet-mode="<?= htmlspecialchars($fleetMode, ENT_QUOTES, 'UTF-8') ?>"
								data-delete-fleet-type="<?= htmlspecialchars($selectedFleetType, ENT_QUOTES, 'UTF-8') ?>"
								data-delete-fleet-status="<?= htmlspecialchars($selectedFleetStatus, ENT_QUOTES, 'UTF-8') ?>"
							>
								Edit
							</button>
							<?php if ($fleetMode !== 'status'): ?>
								<button
									class="admin-fleet-card__btn admin-fleet-card__btn--delete"
									type="button"
									data-modal-target="admin-delete-vehicle-modal"
									data-delete-vehicle-trigger
									data-delete-vehicle-id="<?= (int) ($vehicle['id'] ?? 0) ?>"
									data-delete-vehicle-label="<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?>"
									data-delete-fleet-mode="<?= htmlspecialchars($fleetMode, ENT_QUOTES, 'UTF-8') ?>"
									data-delete-fleet-type="<?= htmlspecialchars($selectedFleetType, ENT_QUOTES, 'UTF-8') ?>"
									data-delete-fleet-status="<?= htmlspecialchars($selectedFleetStatus, ENT_QUOTES, 'UTF-8') ?>"
								>
									Delete
								</button>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>

				<?php if (empty($fleetVehicles)): ?>
					<p class="admin-fleet__empty"><?= htmlspecialchars($fleetEmptyMessage, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
