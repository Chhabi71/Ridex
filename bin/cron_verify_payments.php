<?php
/**
 * Purpose: Reconcile payment rows against bookings without external gateway API calls.
 */

require_once __DIR__ . '/cron_bootstrap.php';

$jobName = 'cron_verify_payments';
$args = array_slice($_SERVER['argv'] ?? [], 1);
$dryRun = in_array('--dry-run', $args, true);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
	echo 'Usage: php bin/cron_verify_payments.php [--dry-run]' . PHP_EOL;
	echo 'Reconciles latest payment rows to booking payment status and amount.' . PHP_EOL;
	echo 'No external payment API verification is performed.' . PHP_EOL;
	exit(0);
}

$mapExpectedPaymentMethod = static function (string $bookingPaymentMethod): string {
	$normalizedMethod = strtolower(trim($bookingPaymentMethod));
	return $normalizedMethod === 'khalti' ? 'khalti' : 'cash';
};

$mapExpectedPaymentStatus = static function (string $bookingPaymentStatus, string $expectedPaymentMethod): string {
	$normalizedStatus = strtolower(trim($bookingPaymentStatus));

	if ($normalizedStatus === 'paid') {
		return 'success';
	}

	if ($normalizedStatus === 'pending') {
		return 'pending';
	}

	if ($normalizedStatus === 'cancelled') {
		return 'cancelled';
	}

	if ($normalizedStatus === 'refunded') {
		return 'refunded';
	}

	if ($normalizedStatus === 'unpaid') {
		return $expectedPaymentMethod === 'khalti' ? 'pending' : 'initiated';
	}

	return 'failed';
};

try {
	$pdo = db();
	ridex_cron_ensure_payments_table($pdo);

	$latestPaymentStmt = $pdo->query(
		'SELECT
			p.id,
			p.booking_id,
			p.amount,
			p.method,
			p.status,
			p.pidx,
			b.payment_method AS booking_payment_method,
			b.payment_status AS booking_payment_status,
			b.total_amount,
			b.paid_amount
		 FROM payments p
		 INNER JOIN (
			SELECT booking_id, MAX(id) AS latest_id
			FROM payments
			GROUP BY booking_id
		 ) latest_payment ON latest_payment.latest_id = p.id
		 INNER JOIN bookings b ON b.id = p.booking_id
		 ORDER BY p.id ASC'
	);
	$latestPayments = $latestPaymentStmt->fetchAll() ?: [];

	$updatePaymentStmt = $pdo->prepare(
		'UPDATE payments
		 SET amount = :amount,
			 method = :method,
			 status = :status,
			 updated_at = CURRENT_TIMESTAMP
		 WHERE id = :id
		 LIMIT 1'
	);

	$rowsAligned = 0;
	$rowsUpdated = 0;

	foreach ($latestPayments as $paymentRow) {
		$paymentId = (int) ($paymentRow['id'] ?? 0);
		$bookingId = (int) ($paymentRow['booking_id'] ?? 0);
		$currentAmount = (int) ($paymentRow['amount'] ?? 0);
		$currentMethod = strtolower(trim((string) ($paymentRow['method'] ?? 'cash')));
		$currentStatus = strtolower(trim((string) ($paymentRow['status'] ?? 'initiated')));

		$expectedMethod = $mapExpectedPaymentMethod((string) ($paymentRow['booking_payment_method'] ?? ''));
		$expectedStatus = $mapExpectedPaymentStatus((string) ($paymentRow['booking_payment_status'] ?? ''), $expectedMethod);

		$bookingPaidAmount = (int) ($paymentRow['paid_amount'] ?? 0);
		$bookingTotalAmount = (int) ($paymentRow['total_amount'] ?? 0);
		$expectedAmount = $bookingPaidAmount > 0 ? $bookingPaidAmount : $bookingTotalAmount;
		$expectedAmount = max(0, $expectedAmount);

		$needsUpdate = $currentAmount !== $expectedAmount
			|| $currentMethod !== $expectedMethod
			|| $currentStatus !== $expectedStatus;

		if ($needsUpdate) {
			$rowsAligned += 1;
			if (!$dryRun) {
				$updatePaymentStmt->execute([
					'id' => $paymentId,
					'amount' => $expectedAmount,
					'method' => $expectedMethod,
					'status' => $expectedStatus,
				]);
				$rowsUpdated += (int) $updatePaymentStmt->rowCount();
			}
		}

	}

	$payload = [
		'ok' => true,
		'job' => $jobName,
		'dry_run' => $dryRun,
		'latest_payments_checked' => count($latestPayments),
		'rows_needing_alignment' => $rowsAligned,
		'rows_updated' => $rowsUpdated,
		'external_verification' => 'disabled',
	];

	ridex_cron_write_log($jobName, 'Payment verification completed.', $payload);
	ridex_cron_print_json($payload);
	exit(0);
} catch (Throwable $exception) {
	$payload = [
		'ok' => false,
		'job' => $jobName,
		'error' => $exception->getMessage(),
	];

	ridex_cron_write_log($jobName, 'Payment verification failed.', $payload);
	ridex_cron_print_json($payload);
	exit(1);
}
