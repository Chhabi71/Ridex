<?php
/**
 * Purpose: Payment helper functions wrapping Khalti API calls and local payment logic.
 * Website Section: Booking & Payment.
 * Developer Notes: Build initiation payloads, verify callbacks, handle status mapping, and compute payable amounts/fees.
 */

require_once __DIR__ . '/../../config/khalti.php';

if (!function_exists('ridex_json_encode_safe')) {
	function ridex_json_encode_safe(array $payload): string
	{
		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return is_string($json) ? $json : '{}';
	}
}

if (!function_exists('ridex_khalti_request')) {
	function ridex_khalti_request(string $url, array $payload): array
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: key ' . KHALTI_SECRET_KEY,
			'Content-Type: application/json',
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, ridex_json_encode_safe($payload));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);

		$response = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		$data = is_string($response) && $response !== '' ? json_decode($response, true) : null;
		if (!is_array($data)) {
			$data = [];
		}

		return [
			'ok' => $response !== false && $curlError === '' && $httpCode >= 200 && $httpCode < 300,
			'http_code' => $httpCode,
			'error' => $curlError,
			'raw' => $response === false ? '' : (string) $response,
			'data' => $data,
		];
	}
}

if (!function_exists('ridex_khalti_callback_url')) {
	function ridex_khalti_callback_url(int $bookingId, string $flowToken = ''): string
	{
		$query = [
			'page' => 'khalti-callback',
			'booking_id' => $bookingId,
		];
		if ($flowToken !== '') {
			$query['flow_token'] = $flowToken;
		}

		return KHALTI_WEBSITE_URL . '/index.php?' . http_build_query($query);
	}
}

if (!function_exists('ridex_khalti_initiate_payment')) {
	function ridex_khalti_initiate_payment(int $bookingId, string $bookingNumber, int $amountRupees, string $flowToken = ''): array
	{
		$websiteUrl = KHALTI_WEBSITE_URL;
		$payload = [
			'return_url' => ridex_khalti_callback_url($bookingId, $flowToken),
			'website_url' => $websiteUrl . '/',
			'amount' => max(0, $amountRupees) * 100,
			'purchase_order_id' => 'ORDER_' . $bookingId,
			'purchase_order_name' => 'Vehicle Booking ' . $bookingNumber,
		];

		return ridex_khalti_request(KHALTI_INITIATE_URL, $payload);
	}
}

if (!function_exists('ridex_khalti_lookup_payment')) {
	function ridex_khalti_lookup_payment(string $pidx): array
	{
		return ridex_khalti_request(KHALTI_LOOKUP_URL, ['pidx' => $pidx]);
	}
}

if (!function_exists('ridex_khalti_extract_booking_id')) {
	function ridex_khalti_extract_booking_id(array $request): int
	{
		$bookingId = (int) ($request['booking_id'] ?? 0);
		if ($bookingId > 0) {
			return $bookingId;
		}

		$purchaseOrderId = trim((string) (
			$request['purchase_order_id']
			?? $request['purchase_order']
			?? $request['product_identity']
			?? $request['order_id']
			?? ''
		));
		if (preg_match('/ORDER[_-]?(\d+)/i', $purchaseOrderId, $matches) === 1) {
			return (int) $matches[1];
		}

		return 0;
	}
}

if (!function_exists('ridex_khalti_latest_pidx_for_booking')) {
	function ridex_khalti_latest_pidx_for_booking(PDO $pdo, int $bookingId): string
	{
		if ($bookingId <= 0) {
			return '';
		}

		$stmt = $pdo->prepare(
			'SELECT pidx
			 FROM payments
			 WHERE booking_id = :booking_id
				AND method = "khalti"
				AND pidx IS NOT NULL
				AND pidx <> ""
			 ORDER BY id DESC
			 LIMIT 1'
		);
		$stmt->execute(['booking_id' => $bookingId]);
		return trim((string) $stmt->fetchColumn());
	}
}

if (!function_exists('ridex_khalti_upsert_payment_success')) {
	function ridex_khalti_upsert_payment_success(PDO $pdo, int $bookingId, int $amountRupees, string $pidx, array $providerResponse): void
	{
		$providerJson = ridex_json_encode_safe($providerResponse);
		$updatePayment = $pdo->prepare(
			'UPDATE payments
			 SET method = "khalti",
				 status = "success",
				 amount = :amount,
				 pidx = :pidx,
				 provider_response = :provider_response,
				 transaction_time = CURRENT_TIMESTAMP,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE booking_id = :booking_id
			 ORDER BY id DESC
			 LIMIT 1'
		);
		$updatePayment->execute([
			'booking_id' => $bookingId,
			'amount' => $amountRupees,
			'pidx' => $pidx,
			'provider_response' => $providerJson,
		]);

		if ($updatePayment->rowCount() <= 0) {
			$insertPayment = $pdo->prepare(
				'INSERT INTO payments (
					booking_id,
					amount,
					method,
					status,
					transaction_time,
					pidx,
					provider_response,
					created_at,
					updated_at
				) VALUES (
					:booking_id,
					:amount,
					"khalti",
					"success",
					CURRENT_TIMESTAMP,
					:pidx,
					:provider_response,
					CURRENT_TIMESTAMP,
					CURRENT_TIMESTAMP
				)'
			);
			$insertPayment->execute([
				'booking_id' => $bookingId,
				'amount' => $amountRupees,
				'pidx' => $pidx,
				'provider_response' => $providerJson,
			]);
		}
	}
}

if (!function_exists('ridex_finalize_khalti_payment')) {
	function ridex_finalize_khalti_payment(PDO $pdo, int $bookingId, string $pidx): array
	{
		if ($bookingId <= 0) {
			return ['ok' => false, 'message' => 'Invalid booking reference returned by Khalti.'];
		}

		$pidx = trim($pidx);
		if ($pidx === '') {
			$pidx = ridex_khalti_latest_pidx_for_booking($pdo, $bookingId);
		}
		if ($pidx === '') {
			return ['ok' => false, 'message' => 'Khalti did not return a payment reference. Please contact support.'];
		}

		$lookup = ridex_khalti_lookup_payment($pidx);
		$verified = $lookup['data'] ?? [];
		$status = trim((string) ($verified['status'] ?? ''));

		if (!($lookup['ok'] ?? false)) {
			return [
				'ok' => false,
				'pidx' => $pidx,
				'message' => 'Khalti payment could not be verified right now. Please refresh this page or contact admin.',
				'lookup' => $lookup,
			];
		}

		if (strcasecmp($status, 'Completed') !== 0) {
			return [
				'ok' => false,
				'pidx' => $pidx,
				'message' => 'Khalti payment is not completed yet. Current status: ' . ($status !== '' ? $status : 'Unknown') . '.',
				'lookup' => $lookup,
			];
		}

		$bookingStmt = $pdo->prepare(
			'SELECT id, total_amount, status
			 FROM bookings
			 WHERE id = :booking_id
			 LIMIT 1'
		);
		$bookingStmt->execute(['booking_id' => $bookingId]);
		$booking = $bookingStmt->fetch();
		if (!is_array($booking)) {
			return ['ok' => false, 'pidx' => $pidx, 'message' => 'Booking not found for this Khalti payment.'];
		}

		$verifiedAmount = (int) round(((int) ($verified['total_amount'] ?? 0)) / 100);
		$totalAmount = (int) ($booking['total_amount'] ?? 0);
		$paidAmount = $verifiedAmount > 0 ? $verifiedAmount : $totalAmount;

		$pdo->beginTransaction();
		try {
			$updateBooking = $pdo->prepare(
				'UPDATE bookings
				 SET payment_status = "paid",
					 payment_method = "khalti",
					 paid_amount = :paid_amount,
					 status = CASE
						 WHEN status IN ("cancelled", "completed", "on_trip", "overdue") THEN status
						 ELSE "reserved"
					 END,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :booking_id
				 LIMIT 1'
			);
			$updateBooking->execute([
				'paid_amount' => $paidAmount,
				'booking_id' => $bookingId,
			]);

			ridex_khalti_upsert_payment_success($pdo, $bookingId, $paidAmount, $pidx, [
				'source' => 'khalti-callback',
				'pidx' => $pidx,
				'lookup' => $verified,
			]);

			$pdo->commit();
		} catch (Throwable $exception) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $exception;
		}

		return [
			'ok' => true,
			'pidx' => $pidx,
			'paid_amount' => $paidAmount,
			'message' => 'Khalti payment verified successfully.',
			'lookup' => $lookup,
		];
	}
}
