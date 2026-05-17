<?php
/**
 * Purpose: Booking model encapsulating reservation data and status/payment updates.
 * Website Section: Booking & Payment.
 * Developer Notes: Create bookings with booking_number, fetch by user/admin filters, update statuses, and compute totals/late fees.
 */

if (!function_exists('ridex_booking_fetch_user_history_rows')) {
	function ridex_booking_fetch_user_history_rows(PDO $pdo, int $userId, int $limit = 150): array
	{
		$userId = max(0, $userId);
		$limit = max(1, min(500, $limit));
		if ($userId <= 0) {
			return [];
		}

		$statement = $pdo->prepare(
			'SELECT
				b.id,
				b.booking_number,
				b.status,
				b.payment_status,
				b.payment_method,
				b.pickup_location,
				b.return_location,
				b.pickup_datetime,
				b.return_datetime,
				b.return_time,
				b.total_amount,
				b.paid_amount,
				b.updated_at,
				v.full_name AS vehicle_full_name,
				v.short_name AS vehicle_short_name,
				v.vehicle_type,
				v.image_path AS vehicle_image,
				v.number_of_seats,
				v.transmission_type,
				v.driver_age_requirement,
				v.fuel_type,
				v.mileage_km_per_l,
				v.license_plate,
				v.price_per_day,
				p.transaction_time AS payment_transaction_time
			 FROM bookings b
			 LEFT JOIN vehicles v ON v.id = b.vehicle_id
			 LEFT JOIN payments p ON p.id = (
				SELECT p2.id
				FROM payments p2
				WHERE p2.booking_id = b.id
				ORDER BY p2.transaction_time DESC, p2.id DESC
				LIMIT 1
			 )
			 WHERE b.user_id = :user_id
			 ORDER BY COALESCE(b.updated_at, b.created_at) DESC, b.id DESC
			 LIMIT ' . $limit
		);
		$statement->execute([
			'user_id' => $userId,
		]);

		return $statement->fetchAll() ?: [];
	}
}
