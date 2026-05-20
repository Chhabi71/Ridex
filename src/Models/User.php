<?php
/**
 * Purpose: User model for retrieving and mutating user records.
 * Website Section: Authentication & Account Management.
 * Developer Notes: Implement queries for lookup by email/id, password hash storage, role checks, and profile updates.
 */

if (!function_exists('ridex_user_find_id_by_email')) {
	function ridex_user_find_id_by_email(PDO $pdo, string $email, ?string $role = null): int
	{
		$email = strtolower(trim($email));
		if ($email === '') {
			return 0;
		}

		$role = $role !== null ? strtolower(trim($role)) : null;
		if ($role === 'admin' || $role === 'user') {
			$statement = $pdo->prepare(
				'SELECT id
				 FROM users
				 WHERE role = :role AND LOWER(email) = LOWER(:email)
				 LIMIT 1'
			);
			$statement->execute([
				'role' => $role,
				'email' => $email,
			]);
		} else {
			$statement = $pdo->prepare(
				'SELECT id
				 FROM users
				 WHERE LOWER(email) = LOWER(:email)
				 LIMIT 1'
			);
			$statement->execute([
				'email' => $email,
			]);
		}

		return (int) $statement->fetchColumn();
	}
}


if (!function_exists('ridex_user_find_by_email')) {
	function ridex_user_find_by_email(PDO $pdo, string $email, ?string $role = null): ?array
	{
		$email = strtolower(trim($email));
		if ($email === '') {
			return null;
		}

		$role = $role !== null ? strtolower(trim($role)) : null;
		if ($role === 'admin' || $role === 'user') {
			$statement = $pdo->prepare(
				'SELECT id, name, first_name, last_name, email, phone, drivers_id, password_hash, role,
						COALESCE(email_verified, 0) AS email_verified,
						email_verified_at,
						password_reset_expires
				 FROM users
				 WHERE role = :role AND LOWER(email) = LOWER(:email)
				 LIMIT 1'
			);
			$statement->execute([
				'role' => $role,
				'email' => $email,
			]);
		} else {
			$statement = $pdo->prepare(
				'SELECT id, name, first_name, last_name, email, phone, drivers_id, password_hash, role,
						COALESCE(email_verified, 0) AS email_verified,
						email_verified_at,
						password_reset_expires
				 FROM users
				 WHERE LOWER(email) = LOWER(:email)
				 LIMIT 1'
			);
			$statement->execute([
				'email' => $email,
			]);
		}

		$result = $statement->fetch();
		return is_array($result) ? $result : null;
	}
}

if (!function_exists('ridex_user_find_id_by_email_and_driver')) {
	function ridex_user_find_id_by_email_and_driver(PDO $pdo, string $email, string $driversId): int
	{
		$email = strtolower(trim($email));
		$driversId = trim($driversId);
		if ($email === '' || $driversId === '') {
			return 0;
		}

		$statement = $pdo->prepare(
			'SELECT id
			 FROM users
			 WHERE role = :role
				AND LOWER(email) = LOWER(:email)
				AND LOWER(drivers_id) = LOWER(:drivers_id)
			 LIMIT 1'
		);
		$statement->execute([
			'role' => 'user',
			'email' => $email,
			'drivers_id' => $driversId,
		]);

		return (int) $statement->fetchColumn();
	}
}

if (!function_exists('ridex_user_find_id_by_driver_id')) {
	function ridex_user_find_id_by_driver_id(PDO $pdo, string $driversId, ?string $role = null): int
	{
		$driversId = trim($driversId);
		if ($driversId === '') {
			return 0;
		}

		$role = $role !== null ? strtolower(trim($role)) : null;
		if ($role === 'admin' || $role === 'user') {
			$statement = $pdo->prepare(
				'SELECT id
				 FROM users
				 WHERE role = :role AND LOWER(drivers_id) = LOWER(:drivers_id)
				 LIMIT 1'
			);
			$statement->execute([
				'role' => $role,
				'drivers_id' => $driversId,
			]);
		} else {
			$statement = $pdo->prepare(
				'SELECT id
				 FROM users
				 WHERE LOWER(drivers_id) = LOWER(:drivers_id)
				 LIMIT 1'
			);
			$statement->execute([
				'drivers_id' => $driversId,
			]);
		}

		return (int) $statement->fetchColumn();
	}
}

if (!function_exists('ridex_user_find_for_login')) {
	function ridex_user_find_for_login(PDO $pdo, string $identifier): ?array
	{
		$identifier = trim($identifier);
		if ($identifier === '') {
			return null;
		}

		$statement = $pdo->prepare(
			'SELECT id, name, first_name, last_name, email, phone, drivers_id, drivers_id_image_path, date_of_birth, password_hash, role,
					COALESCE(email_verified, 0) AS email_verified,
					email_verified_at
			 FROM users
			 WHERE role = :role
				AND (
					LOWER(email) = LOWER(:identifier_email)
					OR LOWER(drivers_id) = LOWER(:identifier_driver)
				)
			 LIMIT 1'
		);
		$statement->execute([
			'role' => 'user',
			'identifier_email' => $identifier,
			'identifier_driver' => $identifier,
		]);

		$result = $statement->fetch();
		return is_array($result) ? $result : null;
	}
}

if (!function_exists('ridex_user_create_registered_user')) {
	function ridex_user_create_registered_user(PDO $pdo, array $attributes): int
	{
		$statement = $pdo->prepare(
			'INSERT INTO users (
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
				email_verified,
				email_verification_token_hash,
				email_verification_expires,
				created_at,
				updated_at
			) VALUES (
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
				:user_role,
				:drivers_id,
				:drivers_id_image_path,
				:email_verified,
				:email_verification_token_hash,
				:email_verification_expires,
				CURRENT_TIMESTAMP,
				CURRENT_TIMESTAMP
			)'
		);

		$statement->execute([
			'name' => trim((string) ($attributes['name'] ?? 'Ridex User')),
			'first_name' => trim((string) ($attributes['first_name'] ?? '')),
			'last_name' => trim((string) ($attributes['last_name'] ?? '')),
			'email' => strtolower(trim((string) ($attributes['email'] ?? ''))),
			'password_hash' => (string) ($attributes['password_hash'] ?? ''),
			'phone' => trim((string) ($attributes['phone'] ?? '')),
			'address' => trim((string) ($attributes['address'] ?? '')),
			'date_of_birth' => trim((string) ($attributes['date_of_birth'] ?? '')),
			'street' => trim((string) ($attributes['street'] ?? '')),
			'post_code' => trim((string) ($attributes['post_code'] ?? '')),
			'city' => trim((string) ($attributes['city'] ?? '')),
			'province' => trim((string) ($attributes['province'] ?? '')),
			'user_role' => 'user',
			'drivers_id' => trim((string) ($attributes['drivers_id'] ?? '')),
			'drivers_id_image_path' => trim((string) ($attributes['drivers_id_image_path'] ?? '')),
			'email_verified' => (int) ($attributes['email_verified'] ?? 0),
			'email_verification_token_hash' => trim((string) ($attributes['email_verification_token_hash'] ?? '')),
			'email_verification_expires' => trim((string) ($attributes['email_verification_expires'] ?? '')),
		]);

		return (int) $pdo->lastInsertId();
	}
}

if (!function_exists('ridex_user_ensure_default_admin_account')) {
	function ridex_user_ensure_default_admin_account(PDO $pdo, string $defaultAdminEmail, string $defaultAdminPassword): void
	{
		$defaultAdminEmail = strtolower(trim($defaultAdminEmail));
		if ($defaultAdminEmail === '') {
			return;
		}

		$defaultAdminHash = password_hash($defaultAdminPassword, PASSWORD_DEFAULT);
		if (!is_string($defaultAdminHash) || $defaultAdminHash === '') {
			throw new RuntimeException('Unable to hash default admin password.');
		}

		$canonicalUserStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
		$canonicalUserStmt->execute(['email' => $defaultAdminEmail]);
		$canonicalUserId = (int) $canonicalUserStmt->fetchColumn();

		if ($canonicalUserId <= 0) {
			$existingAdminStmt = $pdo->prepare('SELECT id FROM users WHERE role = :role ORDER BY id ASC LIMIT 1');
			$existingAdminStmt->execute(['role' => 'admin']);
			$existingAdminId = (int) $existingAdminStmt->fetchColumn();

			if ($existingAdminId > 0) {
				$reuseAdminStmt = $pdo->prepare(
					'UPDATE users
					 SET name = :name,
						 email = :email,
						 password_hash = :password_hash,
						 role = :role,
						 updated_at = CURRENT_TIMESTAMP
					 WHERE id = :id'
				);
				$reuseAdminStmt->execute([
					'name' => 'Ridex Admin',
					'email' => $defaultAdminEmail,
					'password_hash' => $defaultAdminHash,
					'role' => 'admin',
					'id' => $existingAdminId,
				]);
				$canonicalUserId = $existingAdminId;
			} else {
				$insertAdminStmt = $pdo->prepare(
					'INSERT INTO users (name, email, password_hash, role, created_at, updated_at)
					 VALUES (:name, :email, :password_hash, :role, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
				);
				$insertAdminStmt->execute([
					'name' => 'Ridex Admin',
					'email' => $defaultAdminEmail,
					'password_hash' => $defaultAdminHash,
					'role' => 'admin',
				]);
				$canonicalUserId = (int) $pdo->lastInsertId();
			}
		}

		if ($canonicalUserId > 0) {
			$normalizeCanonicalStmt = $pdo->prepare(
				'UPDATE users
				 SET name = :name,
					 email = :email,
					 password_hash = :password_hash,
					 role = :role,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :id'
			);
			$normalizeCanonicalStmt->execute([
				'name' => 'Ridex Admin',
				'email' => $defaultAdminEmail,
				'password_hash' => $defaultAdminHash,
				'role' => 'admin',
				'id' => $canonicalUserId,
			]);

			$demoteOtherAdminsStmt = $pdo->prepare(
				'UPDATE users
				 SET role = :user_role,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE role = :admin_role AND id <> :id'
			);
			$demoteOtherAdminsStmt->execute([
				'user_role' => 'user',
				'admin_role' => 'admin',
				'id' => $canonicalUserId,
			]);
		}
	}
}


if (!function_exists('ridex_user_find_by_email_and_driver')) {
	function ridex_user_find_by_email_and_driver(PDO $pdo, string $email, string $driversId): ?array
	{
		$email = strtolower(trim($email));
		$driversId = trim($driversId);
		if ($email === '' || $driversId === '') {
			return null;
		}

		$statement = $pdo->prepare(
			'SELECT id, name, first_name, last_name, email, drivers_id, role,
					COALESCE(email_verified, 0) AS email_verified
			 FROM users
			 WHERE role = :role
				AND LOWER(email) = LOWER(:email)
				AND LOWER(drivers_id) = LOWER(:drivers_id)
			 LIMIT 1'
		);
		$statement->execute([
			'role' => 'user',
			'email' => $email,
			'drivers_id' => $driversId,
		]);

		$result = $statement->fetch();
		return is_array($result) ? $result : null;
	}
}

if (!function_exists('ridex_user_store_email_verification_token')) {
	function ridex_user_store_email_verification_token(PDO $pdo, int $userId, string $tokenHash, DateTimeInterface $expiresAt): void
	{
		$statement = $pdo->prepare(
			'UPDATE users
			 SET email_verified = 0,
				 email_verified_at = NULL,
				 email_verification_token_hash = :token_hash,
				 email_verification_expires = :expires_at,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :id AND role = :role
			 LIMIT 1'
		);
		$statement->execute([
			'token_hash' => $tokenHash,
			'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
			'id' => $userId,
			'role' => 'user',
		]);
	}
}

if (!function_exists('ridex_user_find_by_email_verification_token')) {
	function ridex_user_find_by_email_verification_token(PDO $pdo, string $tokenHash): ?array
	{
		$tokenHash = trim($tokenHash);
		if ($tokenHash === '') {
			return null;
		}

		$statement = $pdo->prepare(
			'SELECT id, name, email, email_verification_expires, COALESCE(email_verified, 0) AS email_verified
			 FROM users
			 WHERE role = :role AND email_verification_token_hash = :token_hash
			 LIMIT 1'
		);
		$statement->execute([
			'role' => 'user',
			'token_hash' => $tokenHash,
		]);

		$result = $statement->fetch();
		return is_array($result) ? $result : null;
	}
}

if (!function_exists('ridex_user_mark_email_verified')) {
	function ridex_user_mark_email_verified(PDO $pdo, int $userId): void
	{
		$statement = $pdo->prepare(
			'UPDATE users
			 SET email_verified = 1,
				 email_verified_at = CURRENT_TIMESTAMP,
				 email_verification_token_hash = NULL,
				 email_verification_expires = NULL,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :id AND role = :role
			 LIMIT 1'
		);
		$statement->execute([
			'id' => $userId,
			'role' => 'user',
		]);
	}
}

if (!function_exists('ridex_user_store_password_reset_token')) {
	function ridex_user_store_password_reset_token(PDO $pdo, int $userId, string $tokenHash, DateTimeInterface $expiresAt, string $role = 'user'): void
	{
		$role = strtolower(trim($role));
		if (!in_array($role, ['user', 'admin'], true)) {
			$role = 'user';
		}

		$statement = $pdo->prepare(
			'UPDATE users
			 SET password_reset_token_hash = :token_hash,
				 password_reset_expires = :expires_at,
				 password_reset_requested_at = CURRENT_TIMESTAMP,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :id AND role = :role
			 LIMIT 1'
		);
		$statement->execute([
			'token_hash' => $tokenHash,
			'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
			'id' => $userId,
			'role' => $role,
		]);
	}
}

if (!function_exists('ridex_user_find_by_password_reset_token')) {
	function ridex_user_find_by_password_reset_token(PDO $pdo, string $tokenHash, ?string $role = null): ?array
	{
		$tokenHash = trim($tokenHash);
		if ($tokenHash === '') {
			return null;
		}

		$role = $role !== null ? strtolower(trim($role)) : null;
		if ($role === 'admin' || $role === 'user') {
			$statement = $pdo->prepare(
				'SELECT id, name, first_name, last_name, email, role, password_reset_expires, COALESCE(email_verified, 0) AS email_verified
				 FROM users
				 WHERE role = :role AND password_reset_token_hash = :token_hash
				 LIMIT 1'
			);
			$statement->execute([
				'role' => $role,
				'token_hash' => $tokenHash,
			]);
		} else {
			$statement = $pdo->prepare(
				'SELECT id, name, first_name, last_name, email, role, password_reset_expires, COALESCE(email_verified, 0) AS email_verified
				 FROM users
				 WHERE role IN ("user", "admin") AND password_reset_token_hash = :token_hash
				 LIMIT 1'
			);
			$statement->execute([
				'token_hash' => $tokenHash,
			]);
		}

		$result = $statement->fetch();
		return is_array($result) ? $result : null;
	}
}

if (!function_exists('ridex_user_update_password_and_clear_reset_token')) {
	function ridex_user_update_password_and_clear_reset_token(PDO $pdo, int $userId, string $passwordHash, string $role = 'user'): void
	{
		$role = strtolower(trim($role));
		if (!in_array($role, ['user', 'admin'], true)) {
			$role = 'user';
		}

		$statement = $pdo->prepare(
			'UPDATE users
			 SET password_hash = :password_hash,
				 password_reset_token_hash = NULL,
				 password_reset_expires = NULL,
				 password_reset_requested_at = NULL,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :id AND role = :role
			 LIMIT 1'
		);
		$statement->execute([
			'password_hash' => $passwordHash,
			'id' => $userId,
			'role' => $role,
		]);
	}
}
