<?php
/**
 * Purpose: My Bookings page with tabbed booking history cards.
 */

$bookingHistoryRows = isset($bookingHistoryRows) && is_array($bookingHistoryRows)
	? $bookingHistoryRows
	: [];
$bookingHistoryBuckets = isset($bookingHistoryBuckets) && is_array($bookingHistoryBuckets)
	? $bookingHistoryBuckets
	: [
		'active' => [],
		'pending' => [],
		'completed' => [],
		'cancelled' => [],
	];
$bookingHistorySelectedTab = strtolower(trim((string) ($bookingHistorySelectedTab ?? 'active')));
$bookingHistoryNotice = trim((string) ($bookingHistoryNotice ?? ''));

$allowedTabs = ['active', 'pending', 'completed', 'cancelled'];
if (!in_array($bookingHistorySelectedTab, $allowedTabs, true)) {
	$bookingHistorySelectedTab = 'active';
}

$formatCurrency = static function ($amount): string {
	$numeric = (float) $amount;
	return '$' . number_format($numeric, 2);
};

$tabLabels = [
	'active' => 'Active',
	'pending' => 'Pending',
	'completed' => 'Completed',
	'cancelled' => 'Cancelled',
];

$buildHistoryTabUrl = static function (string $tab): string {
	return 'index.php?' . http_build_query([
		'page' => 'user-booking-history',
		'tab' => $tab,
	]);
};

$currentTabRows = $bookingHistoryRows;
$currentTabLabel = strtolower(trim((string) ($tabLabels[$bookingHistorySelectedTab] ?? ucfirst($bookingHistorySelectedTab))));

$hasCancelableBooking = false;
foreach ($currentTabRows as $bookingHistoryRow) {
	if (!empty($bookingHistoryRow['can_cancel'])) {
		$hasCancelableBooking = true;
		break;
	}
}
?>

<section class="user-bookings-page" aria-labelledby="user-bookings-title">
	<header class="user-bookings-page__header">
		<h1 class="user-bookings-page__title" id="user-bookings-title">My Bookings</h1>
	</header>

	<nav class="user-bookings-page__tabs" aria-label="My bookings sections">
		<?php foreach ($allowedTabs as $bookingTab): ?>
			<?php
			$bookingTabLabel = $tabLabels[$bookingTab] ?? ucfirst($bookingTab);
			$bookingTabClass = 'user-bookings-page__tab';
			if ($bookingHistorySelectedTab === $bookingTab) {
				$bookingTabClass .= ' is-active';
			}
			?>
			<a
				class="<?= htmlspecialchars($bookingTabClass, ENT_QUOTES, 'UTF-8') ?>"
				href="<?= htmlspecialchars($buildHistoryTabUrl($bookingTab), ENT_QUOTES, 'UTF-8') ?>"
				aria-current="<?= $bookingHistorySelectedTab === $bookingTab ? 'page' : 'false' ?>"
			>
				<?= htmlspecialchars($bookingTabLabel, ENT_QUOTES, 'UTF-8') ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ($bookingHistoryNotice !== ''): ?>
		<p class="user-bookings-page__notice" role="status"><?= htmlspecialchars($bookingHistoryNotice, ENT_QUOTES, 'UTF-8') ?></p>
	<?php endif; ?>

	<?php if (empty($currentTabRows)): ?>
		<p class="user-bookings-page__empty">
			You have no
			<span class="user-bookings-page__empty-category"><?= htmlspecialchars($currentTabLabel, ENT_QUOTES, 'UTF-8') ?></span>
			bookings
		</p>
	<?php else: ?>
		<div class="user-bookings-page__list">
			<?php foreach ($currentTabRows as $bookingHistoryRow): ?>
				<?php
				$paymentStatusClass = strtolower(trim((string) ($bookingHistoryRow['payment_status_class'] ?? 'unknown')));
				$paymentStatusClass = in_array($paymentStatusClass, ['paid', 'pending', 'cancelled', 'unpaid', 'refunded', 'unknown'], true)
					? $paymentStatusClass
					: 'unknown';
				$paymentStatusIcon = trim((string) ($bookingHistoryRow['payment_status_icon'] ?? 'help'));
				if ($paymentStatusIcon === '') {
					$paymentStatusIcon = 'help';
				}
				$paymentStatusLabel = trim((string) ($bookingHistoryRow['payment_status_label'] ?? 'Unknown'));
				if ($paymentStatusLabel === '') {
					$paymentStatusLabel = 'Unknown';
				}
				$canCancelBooking = !empty($bookingHistoryRow['can_cancel']);
				?>
				<article class="user-bookings-card" aria-label="Booking <?= htmlspecialchars((string) ($bookingHistoryRow['booking_number'] ?? '#RX-0000'), ENT_QUOTES, 'UTF-8') ?>">
					<div class="booking-checkout__shell user-bookings-card__shell">
						<div class="booking-checkout__grid user-bookings-card__grid">
							<div class="booking-checkout-card user-bookings-card__main">
								<div class="user-bookings-card__top">
									<p class="user-bookings-card__payment-state user-bookings-card__payment-state--<?= htmlspecialchars($paymentStatusClass, ENT_QUOTES, 'UTF-8') ?>">
										<span class="material-symbols-rounded" aria-hidden="true"><?= htmlspecialchars($paymentStatusIcon, ENT_QUOTES, 'UTF-8') ?></span>
										<span><?= htmlspecialchars($paymentStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
									</p>

									<?php if ($canCancelBooking): ?>
										<button
											class="user-bookings-card__cancel user-bookings-card__cancel--danger"
											type="button"
											data-modal-target="user-booking-cancel-modal"
											data-user-booking-cancel-trigger="true"
											data-user-booking-cancel-id="<?= htmlspecialchars((string) ($bookingHistoryRow['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
											data-user-booking-cancel-tab="<?= htmlspecialchars($bookingHistorySelectedTab, ENT_QUOTES, 'UTF-8') ?>"
										>
											Cancel
										</button>
									<?php else: ?>
										<button class="user-bookings-card__cancel user-bookings-card__cancel--disabled" type="button" disabled aria-disabled="true">Cancel</button>
									<?php endif; ?>
								</div>

								<p class="booking-checkout-card__booking-number">Booking Number: <?= htmlspecialchars((string) ($bookingHistoryRow['booking_number'] ?? '#RX-0000'), ENT_QUOTES, 'UTF-8') ?></p>

								<div class="booking-checkout-card__vehicle-row">
									<div class="booking-checkout-card__image-wrap">
										<img
											src="<?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_image'] ?? 'images/vehicle-feature.png'), ENT_QUOTES, 'UTF-8') ?>"
											alt="<?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_name'] ?? 'Vehicle'), ENT_QUOTES, 'UTF-8') ?>"
											class="booking-checkout-card__image"
											onerror="this.onerror=null;this.src='images/vehicle-feature.png';"
										/>
									</div>

									<div class="booking-checkout-card__vehicle-info">
										<p class="booking-checkout-card__vehicle-category"><?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_category'] ?? 'Car'), ENT_QUOTES, 'UTF-8') ?></p>
										<h2 class="booking-checkout-card__vehicle-name"><?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_name'] ?? 'Vehicle'), ENT_QUOTES, 'UTF-8') ?></h2>

										<ul class="booking-checkout-card__meta" aria-label="Vehicle attributes">
											<li><span class="material-symbols-rounded" aria-hidden="true">person</span><span><?= htmlspecialchars(((int) ($bookingHistoryRow['vehicle_seats'] ?? 0)) > 0 ? ((int) ($bookingHistoryRow['vehicle_seats'] ?? 0)) . ' Seats' : 'N/A', ENT_QUOTES, 'UTF-8') ?></span></li>
											<li><span class="material-symbols-rounded" aria-hidden="true">settings</span><span><?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_transmission'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span></li>
											<li><span class="material-symbols-rounded" aria-hidden="true">badge</span><span><?= htmlspecialchars(((int) ($bookingHistoryRow['vehicle_age'] ?? 0)) > 0 ? ((int) ($bookingHistoryRow['vehicle_age'] ?? 0)) . '+ Years' : 'N/A', ENT_QUOTES, 'UTF-8') ?></span></li>
											<li><span class="material-symbols-rounded" aria-hidden="true">local_gas_station</span><span><?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_fuel'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span></li>
											<li><span class="material-symbols-rounded" aria-hidden="true">speed</span><span><?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_mileage'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span></li>
											<li><span class="material-symbols-rounded" aria-hidden="true">id_card</span><span><?= htmlspecialchars((string) ($bookingHistoryRow['vehicle_plate'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span></li>
										</ul>
									</div>
								</div>

								<div class="booking-checkout-card__timeline">
									<div class="booking-checkout-card__timeline-column">
										<p class="booking-checkout-card__timeline-label">Pickup</p>
										<p class="booking-checkout-card__timeline-line">
											<strong class="booking-checkout-card__timeline-spot"><?= htmlspecialchars((string) ($bookingHistoryRow['pickup_location'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></strong>
											<span class="booking-checkout-card__timeline-datetime"><?= htmlspecialchars((string) ($bookingHistoryRow['pickup_datetime_label'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span>
										</p>
									</div>
									<div class="booking-checkout-card__timeline-column">
										<p class="booking-checkout-card__timeline-label">Return</p>
										<p class="booking-checkout-card__timeline-line">
											<strong class="booking-checkout-card__timeline-spot"><?= htmlspecialchars((string) ($bookingHistoryRow['return_location'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></strong>
											<span class="booking-checkout-card__timeline-datetime"><?= htmlspecialchars((string) ($bookingHistoryRow['return_datetime_label'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span>
										</p>
									</div>
								</div>
							</div>

							<aside class="booking-payment-card user-bookings-card__payment" aria-label="Booking payment details">
								<h2 class="booking-payment-card__title"><span>Total</span> <?= htmlspecialchars($formatCurrency($bookingHistoryRow['total_amount'] ?? 0), ENT_QUOTES, 'UTF-8') ?></h2>
								<p class="user-bookings-card__status-line user-bookings-card__status-line--<?= htmlspecialchars($paymentStatusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($bookingHistoryRow['status_line'] ?? 'Status N/A'), ENT_QUOTES, 'UTF-8') ?></p>

								<div class="booking-payment-card__details">
									<h3 class="booking-payment-card__details-title">Price details</h3>
									<div class="booking-payment-card__line"><span>Price per day</span><span><?= htmlspecialchars($formatCurrency($bookingHistoryRow['price_per_day'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span></div>
									<div class="booking-payment-card__line"><span>Price for <?= htmlspecialchars((string) ($bookingHistoryRow['total_days'] ?? 1), ENT_QUOTES, 'UTF-8') ?> day<?= ((int) ($bookingHistoryRow['total_days'] ?? 1)) === 1 ? '' : 's' ?></span><span><?= htmlspecialchars($formatCurrency($bookingHistoryRow['price_for_days'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span></div>
									<div class="booking-payment-card__line"><span>Drop charge</span><span><?= htmlspecialchars($formatCurrency($bookingHistoryRow['drop_charge'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span></div>
									<div class="booking-payment-card__line"><span>Taxes &amp; Fees</span><span><?= htmlspecialchars($formatCurrency($bookingHistoryRow['taxes_and_fees'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span></div>
									<div class="booking-payment-card__total"><span><?= htmlspecialchars($formatCurrency($bookingHistoryRow['total_amount'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span></div>
								</div>

								<div class="booking-payment-card__actions user-bookings-card__actions">
									<a class="user-bookings-card__download" href="<?= htmlspecialchars((string) ($bookingHistoryRow['download_url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Download</a>
								</div>
							</aside>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php if ($hasCancelableBooking): ?>
	<div class="menu-modal user-booking-cancel-modal" id="user-booking-cancel-modal" hidden aria-hidden="true" data-modal-id="user-booking-cancel-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog user-booking-cancel-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-booking-cancel-title">
			<header class="menu-modal__header user-booking-cancel-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex cancel booking">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close cancel booking modal" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="user-booking-cancel-modal__content">
				<span class="material-symbols-rounded user-booking-cancel-modal__icon" aria-hidden="true">delete</span>
				<p class="user-booking-cancel-modal__text" id="user-booking-cancel-title">Are you sure you want to cancel your booking? This action cannot be undone.</p>
				<p class="user-booking-cancel-modal__notice">Upon cancelling notice will be sent to Ridex. Wait for approval!</p>

				<div class="user-booking-cancel-modal__actions">
					<button class="user-booking-cancel-modal__keep" type="button" data-modal-back>Keep Booking</button>

					<form method="post" action="index.php" class="user-booking-cancel-modal__form">
						<input type="hidden" name="action" value="user-request-booking-cancellation" />
						<input type="hidden" name="booking_id" value="0" data-user-booking-cancel-id-input />
						<input type="hidden" name="history_tab" value="<?= htmlspecialchars($bookingHistorySelectedTab, ENT_QUOTES, 'UTF-8') ?>" data-user-booking-cancel-tab-input />
						<button class="user-booking-cancel-modal__confirm" type="submit">Cancel Booking</button>
					</form>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>
<?php endif; ?>
