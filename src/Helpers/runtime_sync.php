<?php
/**
 * Purpose: Runtime booking/user/payment synchronization helpers extracted from public/index.php.
 * Website Section: Admin + booking lifecycle runtime maintenance.
 */

if (!function_exists('ridex_sync_booking_lifecycle_statuses')) {
	function ridex_sync_booking_lifecycle_statuses(PDO $pdo): void
	{
		try {
			$statusCaseSql = 'CASE
				WHEN b.status IN ("completed", "cancelled") THEN b.status
				WHEN b.return_time IS NOT NULL THEN "completed"
				WHEN CURRENT_TIMESTAMP > b.return_datetime THEN "overdue"
				WHEN CURRENT_TIMESTAMP >= b.pickup_datetime THEN "on_trip"
				ELSE "reserved"
			END';

			$syncStatusSql = 'UPDATE bookings b
				SET status = ' . $statusCaseSql . ',
					updated_at = CURRENT_TIMESTAMP
				WHERE b.status <> ' . $statusCaseSql;

			$pdo->exec($syncStatusSql);

			$calculatedTotalSql = 'GREATEST(
				0,
				ROUND(
					(
						(GREATEST(1, CEIL(TIMESTAMPDIFF(MINUTE, b.pickup_datetime, b.return_datetime) / 1440)) * v.price_per_day)
						+ 20
						+ GREATEST(0, b.late_fee)
					) * 1.13,
					0
				)
			)';

			$syncTotalsSql = 'UPDATE bookings b
				INNER JOIN vehicles v ON v.id = b.vehicle_id
				SET b.total_amount = ' . $calculatedTotalSql . ',
					b.updated_at = CURRENT_TIMESTAMP
				WHERE b.total_amount <> ' . $calculatedTotalSql;

			$pdo->exec($syncTotalsSql);

			$paymentCaseSql = 'CASE
				WHEN status = "cancelled" AND payment_method = "khalti" THEN
					CASE
						WHEN payment_status = "refunded" THEN "refunded"
						WHEN payment_status IN ("paid", "pending", "cancelled") THEN "cancelled"
						ELSE payment_status
					END
				WHEN status = "cancelled" AND payment_method = "pay_on_arrival" THEN
					CASE
						WHEN payment_status = "paid" THEN "cancelled"
						WHEN payment_status IN ("pending", "cancelled", "unpaid") THEN payment_status
						ELSE payment_status
					END
				WHEN status IN ("on_trip", "overdue", "completed") THEN "paid"
				WHEN payment_method = "khalti" THEN "paid"
				WHEN payment_method = "pay_on_arrival" THEN "pending"
				ELSE payment_status
			END';

			$syncPaymentSql = 'UPDATE bookings
				SET payment_status = ' . $paymentCaseSql . ',
					updated_at = CURRENT_TIMESTAMP
				WHERE payment_status <> ' . $paymentCaseSql;

			$pdo->exec($syncPaymentSql);

			$paidAmountCaseSql = 'CASE
				WHEN payment_status = "paid" THEN GREATEST(paid_amount, total_amount)
				WHEN payment_method = "pay_on_arrival" AND payment_status IN ("pending", "unpaid", "cancelled") THEN 0
				ELSE paid_amount
			END';

			$syncPaidAmountSql = 'UPDATE bookings
				SET paid_amount = ' . $paidAmountCaseSql . ',
					updated_at = CURRENT_TIMESTAMP
				WHERE paid_amount <> ' . $paidAmountCaseSql;

			$pdo->exec($syncPaidAmountSql);
		} catch (Throwable $exception) {
			// Keep routing available even if booking sync fails.
			error_log('Booking lifecycle/payment sync failed: ' . $exception->getMessage());
		}
	}
}

if (!function_exists('ridex_sync_booking_users_and_payments')) {
	function ridex_sync_booking_users_and_payments(PDO $pdo, callable $ensurePaymentsTable): void
	{
		try {
			$ensurePaymentsTable($pdo);

			$bookingRows = $pdo->query(
				'SELECT
					id,
					user_id,
					payment_method,
					payment_status,
					total_amount,
					paid_amount,
					return_time,
					created_at,
					updated_at
				 FROM bookings
				 ORDER BY id ASC'
			)->fetchAll() ?: [];

			if (empty($bookingRows)) {
				return;
			}

			$bookingUserIds = [];
			foreach ($bookingRows as $bookingRow) {
				$bookingUserId = (int) ($bookingRow['user_id'] ?? 0);
				if ($bookingUserId > 0) {
					$bookingUserIds[] = $bookingUserId;
				}
			}
			$bookingUserIds = array_values(array_unique($bookingUserIds));
			sort($bookingUserIds);

			$existingUsersById = [];
			if (!empty($bookingUserIds)) {
				$userPlaceholders = implode(',', array_fill(0, count($bookingUserIds), '?'));
				$userLookupStmt = $pdo->prepare(
					'SELECT
						id,
						name,
						first_name,
						last_name,
						email,
						password_hash,
						phone,
						address,
						date_of_birth,
						street,
						post_code,
						city,
						province,
						role,
						drivers_id
					 FROM users
					 WHERE id IN (' . $userPlaceholders . ')'
				);
				$userLookupStmt->execute($bookingUserIds);
				foreach (($userLookupStmt->fetchAll() ?: []) as $userRow) {
					$existingUsersById[(int) ($userRow['id'] ?? 0)] = $userRow;
				}
			}

			$usedEmails = [];
			$emailRows = $pdo->query('SELECT id, email FROM users ORDER BY id ASC')->fetchAll() ?: [];
			foreach ($emailRows as $emailRow) {
				$emailValue = strtolower(trim((string) ($emailRow['email'] ?? '')));
				if ($emailValue !== '' && filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
					$usedEmails[$emailValue] = (int) ($emailRow['id'] ?? 0);
				}
			}

			$allowedProvinces = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudurpashchim'];

			$sanitizeEmailBase = static function (string $rawValue): string {
				$normalized = strtolower(trim($rawValue));
				$normalized = preg_replace('/[^a-z0-9]+/', '.', $normalized);
				$normalized = is_string($normalized) ? trim($normalized, '.') : '';
				if ($normalized === '') {
					$normalized = 'ridex.user';
				}

				return $normalized;
			};

			$buildStrongPassword = static function (int $userId): string {
				return 'RidexUser#' . max(1, $userId) . 'Aa';
			};

			$isStrongPassword = static function (string $password): bool {
				if (strlen($password) < 10) {
					return false;
				}

				$hasLower = preg_match('/[a-z]/', $password) === 1;
				$hasUpper = preg_match('/[A-Z]/', $password) === 1;
				$hasDigit = preg_match('/\d/', $password) === 1;
				$hasSpecial = preg_match('/[^a-zA-Z\d]/', $password) === 1;

				return $hasLower && $hasUpper && $hasDigit && $hasSpecial;
			};

			$generateUniqueGmail = static function (string $name, int $userId) use (&$usedEmails, $sanitizeEmailBase): string {
				$emailBase = $sanitizeEmailBase($name);
				$candidateEmail = $emailBase . '.ridex' . $userId . '@gmail.com';
				$counter = 1;

				while (isset($usedEmails[$candidateEmail])) {
					$candidateEmail = $emailBase . '.ridex' . $userId . '.' . $counter . '@gmail.com';
					$counter += 1;
				}

				$usedEmails[$candidateEmail] = $userId;
				return $candidateEmail;
			};

			$normalizePhoneDigits = static function (string $rawPhone, int $userId): string {
				$digits = preg_replace('/\D+/', '', $rawPhone);
				$digits = is_string($digits) ? $digits : '';

				if (strlen($digits) < 10) {
					$digits = '98' . str_pad((string) ($userId % 100000000), 8, '0', STR_PAD_LEFT);
				}

				if (strlen($digits) > 10) {
					$digits = substr($digits, -10);
				}

				return $digits;
			};

			$upsertUserStmt = $pdo->prepare(
				'INSERT INTO users (
					id,
					name,
					first_name,
					last_name,
					email,
					password_hash,
					phone,
					address,
					date_of_birth,
					street,
					post_code,
					city,
					province,
					role,
					drivers_id,
						drivers_id_image_path,
					created_at,
					updated_at
				) VALUES (
					:id,
					:name,
					:first_name,
					:last_name,
					:email,
					:password_hash,
					:phone,
					:address,
					:date_of_birth,
					:street,
					:post_code,
					:city,
					:province,
					:role,
					:drivers_id,
						:drivers_id_image_path,
					CURRENT_TIMESTAMP,
					CURRENT_TIMESTAMP
				)
				ON DUPLICATE KEY UPDATE
					name = VALUES(name),
					first_name = VALUES(first_name),
					last_name = VALUES(last_name),
					email = VALUES(email),
					password_hash = VALUES(password_hash),
					phone = VALUES(phone),
					address = VALUES(address),
					date_of_birth = VALUES(date_of_birth),
					street = VALUES(street),
					post_code = VALUES(post_code),
					city = VALUES(city),
					province = VALUES(province),
					role = VALUES(role),
					drivers_id = VALUES(drivers_id),
						drivers_id_image_path = VALUES(drivers_id_image_path),
					updated_at = CURRENT_TIMESTAMP'
			);

			$mapPaymentMethod = static function (string $bookingMethod): string {
				$bookingMethod = strtolower(trim($bookingMethod));
				return $bookingMethod === 'khalti' ? 'khalti' : 'cash';
			};

			$mapPaymentStatus = static function (string $bookingPaymentStatus, string $paymentMethod): string {
				$bookingPaymentStatus = strtolower(trim($bookingPaymentStatus));

				if ($bookingPaymentStatus === 'paid') {
					return 'success';
				}

				if ($bookingPaymentStatus === 'pending') {
					return 'pending';
				}

				if ($bookingPaymentStatus === 'cancelled') {
					return 'cancelled';
				}

				if ($bookingPaymentStatus === 'refunded') {
					return 'refunded';
				}

				if ($bookingPaymentStatus === 'unpaid') {
					return $paymentMethod === 'khalti' ? 'pending' : 'initiated';
				}

				return 'failed';
			};

			$insertPaymentStmt = $pdo->prepare(
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
					:method,
					:status,
					:transaction_time,
					NULL,
					:provider_response,
					CURRENT_TIMESTAMP,
					CURRENT_TIMESTAMP
				)'
			);

			$updatePaymentStmt = $pdo->prepare(
				'UPDATE payments
				 SET amount = :amount,
					 method = :method,
					 status = :status,
					 transaction_time = :transaction_time,
					 provider_response = :provider_response,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :id
				 LIMIT 1'
			);

			$pdo->beginTransaction();

			foreach ($bookingUserIds as $bookingUserId) {
				$existingUser = $existingUsersById[$bookingUserId] ?? [];
				$existingRole = strtolower(trim((string) ($existingUser['role'] ?? 'user')));
				if ($existingRole === 'admin') {
					continue;
				}

				$userName = trim((string) ($existingUser['name'] ?? ''));
				if ($userName === '') {
					$userName = 'Ridex User ' . $bookingUserId;
				}

				$firstName = trim((string) ($existingUser['first_name'] ?? ''));
				$lastName = trim((string) ($existingUser['last_name'] ?? ''));
				$nameParts = preg_split('/\s+/', $userName) ?: [];

				if ($firstName === '') {
					$firstName = trim((string) ($nameParts[0] ?? 'Ridex'));
				}

				if ($lastName === '') {
					$lastName = trim((string) (count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'User'));
				}

				if ($firstName === '') {
					$firstName = 'Ridex';
				}

				if ($lastName === '') {
					$lastName = 'User';
				}

				$userName = trim($firstName . ' ' . $lastName);

				$currentEmail = strtolower(trim((string) ($existingUser['email'] ?? '')));
				$currentEmailIsValid = $currentEmail !== '' && filter_var($currentEmail, FILTER_VALIDATE_EMAIL);
				$currentEmailOwnerId = $currentEmailIsValid ? (int) ($usedEmails[$currentEmail] ?? 0) : 0;

				$emailNeedsRefresh = !$currentEmailIsValid
					|| !str_ends_with($currentEmail, '@gmail.com')
					|| ($currentEmailOwnerId > 0 && $currentEmailOwnerId !== $bookingUserId);

				if ($emailNeedsRefresh) {
					$finalEmail = $generateUniqueGmail($userName, $bookingUserId);
				} else {
					$finalEmail = $currentEmail;
					$usedEmails[$finalEmail] = $bookingUserId;
				}

				$passwordHash = trim((string) ($existingUser['password_hash'] ?? ''));
				$generatedPassword = $buildStrongPassword($bookingUserId);
				$legacyGeneratedPassword = 'RidexUser!' . max(1, $bookingUserId) . 'Aa';
				if (!$isStrongPassword($generatedPassword)) {
					$generatedPassword = 'RidexUser#' . max(1, $bookingUserId) . 'A1';
				}

				$passwordNeedsRefresh = $passwordHash === '' || $emailNeedsRefresh;
				if (!$passwordNeedsRefresh && $passwordHash !== '') {
					$matchesCurrentPattern = password_verify($generatedPassword, $passwordHash);
					$matchesLegacyPattern = password_verify($legacyGeneratedPassword, $passwordHash);
					if (!$matchesCurrentPattern && $matchesLegacyPattern) {
						$passwordNeedsRefresh = true;
					}
				}

				if ($passwordNeedsRefresh) {
					$passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);
				}

				$cityValue = trim((string) ($existingUser['city'] ?? ''));
				if ($cityValue === '') {
					$cityValue = 'Kathmandu';
				}

				$streetValue = trim((string) ($existingUser['street'] ?? ''));
				if ($streetValue === '') {
					$streetValue = 'Street ' . str_pad((string) $bookingUserId, 3, '0', STR_PAD_LEFT);
				}

				$postCodeValue = trim((string) ($existingUser['post_code'] ?? ''));
				if ($postCodeValue === '') {
					$postCodeValue = '44600';
				}

				$addressValue = trim((string) ($existingUser['address'] ?? ''));
				if ($addressValue === '') {
					$addressValue = $streetValue . ', ' . $cityValue;
				}

				$provinceValue = trim((string) ($existingUser['province'] ?? ''));
				if (!in_array($provinceValue, $allowedProvinces, true)) {
					$provinceValue = 'Bagmati';
				}

				$dateOfBirthValue = trim((string) ($existingUser['date_of_birth'] ?? ''));
				$validDateOfBirth = DateTimeImmutable::createFromFormat('Y-m-d', $dateOfBirthValue) instanceof DateTimeImmutable;
				if (!$validDateOfBirth) {
					$dateOfBirthValue = (new DateTimeImmutable('now'))
						->sub(new DateInterval('P' . (22 + ($bookingUserId % 12)) . 'Y'))
						->format('Y-m-d');
				}

				$driversIdValue = trim((string) ($existingUser['drivers_id'] ?? ''));
				if ($driversIdValue === '') {
					$driversIdValue = 'RIDEX-' . str_pad((string) $bookingUserId, 6, '0', STR_PAD_LEFT);
				}

				$phoneValue = $normalizePhoneDigits((string) ($existingUser['phone'] ?? ''), $bookingUserId);

				$upsertUserStmt->execute([
					'id' => $bookingUserId,
					'name' => $userName,
					'first_name' => $firstName,
					'last_name' => $lastName,
					'email' => $finalEmail,
					'password_hash' => $passwordHash,
					'phone' => $phoneValue,
					'address' => $addressValue,
					'date_of_birth' => $dateOfBirthValue,
					'street' => $streetValue,
					'post_code' => $postCodeValue,
					'city' => $cityValue,
					'province' => $provinceValue,
					'role' => 'user',
					'drivers_id' => $driversIdValue,
				]);
			}

			$existingPaymentRows = $pdo->query(
				'SELECT id, booking_id
				 FROM payments
				 ORDER BY booking_id ASC, id DESC'
			)->fetchAll() ?: [];

			$latestPaymentByBooking = [];
			$stalePaymentIds = [];
			foreach ($existingPaymentRows as $paymentRow) {
				$paymentId = (int) ($paymentRow['id'] ?? 0);
				$bookingId = (int) ($paymentRow['booking_id'] ?? 0);
				if ($paymentId <= 0 || $bookingId <= 0) {
					continue;
				}

				if (!isset($latestPaymentByBooking[$bookingId])) {
					$latestPaymentByBooking[$bookingId] = $paymentId;
				} else {
					$stalePaymentIds[] = $paymentId;
				}
			}

			foreach ($bookingRows as $bookingRow) {
				$bookingId = (int) ($bookingRow['id'] ?? 0);
				if ($bookingId <= 0) {
					continue;
				}

				$paymentMethod = $mapPaymentMethod((string) ($bookingRow['payment_method'] ?? ''));
				$paymentStatus = $mapPaymentStatus((string) ($bookingRow['payment_status'] ?? ''), $paymentMethod);

				$paidAmount = (int) ($bookingRow['paid_amount'] ?? 0);
				$totalAmount = (int) ($bookingRow['total_amount'] ?? 0);
				$transactionAmount = $paidAmount > 0 ? $paidAmount : $totalAmount;
				if ($transactionAmount < 0) {
					$transactionAmount = 0;
				}

				$transactionTime = trim((string) ($bookingRow['return_time'] ?? ''));
				if ($transactionTime === '') {
					$transactionTime = trim((string) ($bookingRow['updated_at'] ?? ''));
				}
				if ($transactionTime === '') {
					$transactionTime = trim((string) ($bookingRow['created_at'] ?? ''));
				}
				if ($transactionTime === '') {
					$transactionTime = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
				}

				$providerResponse = json_encode([
					'source' => 'booking_sync',
					'booking_id' => $bookingId,
					'booking_payment_method' => strtolower(trim((string) ($bookingRow['payment_method'] ?? ''))),
					'booking_payment_status' => strtolower(trim((string) ($bookingRow['payment_status'] ?? ''))),
					'synced_at' => gmdate(DATE_ATOM),
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				if (!is_string($providerResponse) || $providerResponse === '') {
					$providerResponse = '{}';
				}

				if (isset($latestPaymentByBooking[$bookingId])) {
					$updatePaymentStmt->execute([
						'id' => $latestPaymentByBooking[$bookingId],
						'amount' => $transactionAmount,
						'method' => $paymentMethod,
						'status' => $paymentStatus,
						'transaction_time' => $transactionTime,
						'provider_response' => $providerResponse,
					]);
				} else {
					$insertPaymentStmt->execute([
						'booking_id' => $bookingId,
						'amount' => $transactionAmount,
						'method' => $paymentMethod,
						'status' => $paymentStatus,
						'transaction_time' => $transactionTime,
						'provider_response' => $providerResponse,
					]);
				}
			}

			if (!empty($stalePaymentIds)) {
				$stalePlaceholders = implode(',', array_fill(0, count($stalePaymentIds), '?'));
				$removeStaleStmt = $pdo->prepare('DELETE FROM payments WHERE id IN (' . $stalePlaceholders . ')');
				$removeStaleStmt->execute($stalePaymentIds);
			}

			$pdo->exec(
				'DELETE p
				 FROM payments p
				 LEFT JOIN bookings b ON b.id = p.booking_id
				 WHERE b.id IS NULL'
			);

			$pdo->commit();
		} catch (Throwable $exception) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}

			error_log('Booking users/payments sync failed: ' . $exception->getMessage());
		}
	}
}

if (!function_exists('ridex_sync_sequential_auto_increment')) {
	function ridex_sync_sequential_auto_increment(PDO $pdo): void
	{
		try {
			$tables = ['bookings', 'users', 'payments'];

			foreach ($tables as $tableName) {
				$maxIdStmt = $pdo->query('SELECT COALESCE(MAX(id), 0) FROM `' . $tableName . '`');
				$maxId = (int) $maxIdStmt->fetchColumn();
				$nextId = $maxId + 1;

				$autoIncrementStmt = $pdo->query(
					"SELECT COALESCE(AUTO_INCREMENT, 1)
					 FROM INFORMATION_SCHEMA.TABLES
					 WHERE TABLE_SCHEMA = DATABASE()
						AND TABLE_NAME = '" . $tableName . "'"
				);
				$currentAutoIncrement = (int) $autoIncrementStmt->fetchColumn();

				if ($currentAutoIncrement !== $nextId) {
					$pdo->exec('ALTER TABLE `' . $tableName . '` AUTO_INCREMENT = ' . $nextId);
				}
			}
		} catch (Throwable $exception) {
			error_log('Auto-increment normalization failed: ' . $exception->getMessage());
		}
	}
}
