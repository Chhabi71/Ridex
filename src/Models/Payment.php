<?php
/**
 * Purpose: Payment model for persisting and retrieving payment transactions.
 * Website Section: Booking & Payment.
 * Developer Notes: Insert gateway results, fetch by booking, update statuses, and store provider_response metadata.
 */

if (!function_exists('ridex_payment_ensure_table')) {
	function ridex_payment_ensure_table(PDO $pdo): void
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS payments (
				id INT AUTO_INCREMENT PRIMARY KEY,
				booking_id INT NOT NULL,
				amount INT NOT NULL,
				method ENUM("khalti","cash") NOT NULL,
				status ENUM("initiated","pending","success","failed","cancelled","refunded") NOT NULL,
				transaction_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				pidx VARCHAR(100),
				provider_response LONGTEXT,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
				CHECK (provider_response IS NULL OR JSON_VALID(provider_response))
			)'
		);
	}
}
