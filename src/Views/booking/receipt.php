<?php
/**
 * Purpose: Thank-you page after booking confirmation, with receipt modal trigger.
 */

$bookingReceiptModalData = isset($bookingReceiptModalData) && is_array($bookingReceiptModalData)
	? $bookingReceiptModalData
	: [];
$bookingReceiptRow = isset($bookingReceiptRow) && is_array($bookingReceiptRow)
	? $bookingReceiptRow
	: [];
$bookingFlowHomeUrl = trim((string) ($bookingFlowHomeUrl ?? 'index.php'));
if ($bookingFlowHomeUrl === '') {
	$bookingFlowHomeUrl = 'index.php';
}

$bookingNumber = trim((string) ($bookingReceiptModalData['booking_number'] ?? ''));
if ($bookingNumber === '') {
	$bookingNumber = trim((string) ($bookingReceiptRow['booking_number'] ?? '#RX-0000'));
}

$vehicleName = trim((string) ($bookingReceiptRow['vehicle_full_name'] ?? $bookingReceiptRow['vehicle_short_name'] ?? 'your selected vehicle'));

?>

<section class="booking-thanks" aria-labelledby="booking-thanks-title">
	<div class="booking-thanks__inner">
		<a class="booking-thanks__back" href="<?= htmlspecialchars($bookingFlowHomeUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Back to home">
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			<span>Back to Home</span>
		</a>
		<span class="material-symbols-rounded booking-thanks__icon" aria-hidden="true">volunteer_activism</span>
		<h1 class="booking-thanks__title" id="booking-thanks-title">Thank you for choosing us!</h1>
		<p class="booking-thanks__text">
			Your booking <?= htmlspecialchars($bookingNumber, ENT_QUOTES, 'UTF-8') ?> for
			<?= htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8') ?> has been confirmed.
		</p>
		<p class="booking-thanks__text">
			Download your receipt
			<button class="booking-thanks__link" type="button" data-modal-target="user-booking-bill-modal">here</button>.
		</p>
	</div>
</section>
