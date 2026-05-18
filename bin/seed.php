<?php
/**
 * Purpose: CLI seeder to insert initial data (admin user, categories, sample vehicles).
*/

require_once __DIR__ . '/../config/database.php';

$pdo = db();
$pdo->beginTransaction();

try {
	$categories = [
		['Cars', 'Passenger cars and sedans'],
		['Bikes', 'Two-wheel vehicles'],
		['Luxury', 'Premium and high-end vehicles'],
	];

	$categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1');
	$categoryInsertStmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
	$categoryIdsByType = [];

	foreach ($categories as [$name, $description]) {
		$categoryStmt->execute(['name' => $name]);
		$categoryId = (int) $categoryStmt->fetchColumn();
		if ($categoryId <= 0) {
			$categoryInsertStmt->execute([
				'name' => $name,
				'description' => $description,
			]);
			$categoryId = (int) $pdo->lastInsertId();
		}

		$categoryKey = strtolower($name);
		$categoryIdsByType[$categoryKey] = $categoryId;
	}

	// admin login: ensure the requested default admin credential exists as a hashed password record
	$defaultAdminEmail = 'rupikadangol@gmail.com';
	$defaultAdminPassword = '12345678';
	$defaultAdminHash = password_hash($defaultAdminPassword, PASSWORD_DEFAULT);

	$canonicalUserStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
	$canonicalUserStmt->execute(['email' => $defaultAdminEmail]);
	$canonicalUserId = (int) $canonicalUserStmt->fetchColumn();

	if ($canonicalUserId <= 0) {
		$existingAdminStmt = $pdo->prepare('SELECT id FROM users WHERE role = :role ORDER BY id ASC LIMIT 1');
		$existingAdminStmt->execute(['role' => 'admin']);
		$existingAdminId = (int) $existingAdminStmt->fetchColumn();

		if ($existingAdminId > 0) {
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
		$updateCanonicalAdminStmt = $pdo->prepare(
			'UPDATE users
			 SET role = :role,
				 name = :name,
				 email = :email,
				 password_hash = :password_hash,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :id'
		);
		$updateCanonicalAdminStmt->execute([
			'role' => 'admin',
			'name' => 'Ridex Admin',
			'email' => $defaultAdminEmail,
			'password_hash' => $defaultAdminHash,
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

	$vehicles = [
		[
			'category_key' => 'cars',
			'vehicle_type' => 'cars',
			'short_name' => 'Victoris',
			'full_name' => 'Maruti Suzuki Victoris',
			'price_per_day' => 52,
			'driver_age_requirement' => 21,
			'image_path' => 'images/car1.png',
			'number_of_seats' => 5,
			'transmission_type' => 'automatic',
			'fuel_type' => 'electric',
			'license_plate' => 'BAA-1234',
			'status' => 'available',
			'gps_id' => 'GPS-001',
			'last_service_date' => null,
			'description' => 'Maruti Suzuki Victoris combines comfort, safety, and modern technology for city and highway travel.',
		],
		[
			'category_key' => 'cars',
			'vehicle_type' => 'cars',
			'short_name' => 'Honda Elevate',
			'full_name' => 'Honda Elevate',
			'price_per_day' => 47,
			'driver_age_requirement' => 21,
			'image_path' => 'images/honda-elevate-1.png',
			'number_of_seats' => 5,
			'transmission_type' => 'automatic',
			'fuel_type' => 'petrol',
			'license_plate' => 'BAA-1235',
			'status' => 'available',
			'gps_id' => null,
			'last_service_date' => null,
			'description' => 'Honda Elevate is a versatile SUV designed for mixed road conditions with strong comfort and safety features.',
		],
		[
			'category_key' => 'cars',
			'vehicle_type' => 'cars',
			'short_name' => 'Honda Elevate',
			'full_name' => 'Honda Elevate',
			'price_per_day' => 47,
			'driver_age_requirement' => 21,
			'image_path' => 'images/honda-elevate-2.png',
			'number_of_seats' => 5,
			'transmission_type' => 'automatic',
			'fuel_type' => 'petrol',
			'license_plate' => 'BAA-1236',
			'status' => 'available',
			'gps_id' => null,
			'last_service_date' => null,
			'description' => 'Honda Elevate is a versatile SUV designed for mixed road conditions with strong comfort and safety features.',
		],
		[
			'category_key' => 'cars',
			'vehicle_type' => 'cars',
			'short_name' => 'L07',
			'full_name' => 'DEEPAL L07',
			'price_per_day' => 70,
			'driver_age_requirement' => 21,
			'image_path' => 'images/deepal-l07-black.png',
			'number_of_seats' => 5,
			'transmission_type' => 'automatic',
			'fuel_type' => 'electric',
			'license_plate' => 'BAA-1237',
			'status' => 'available',
			'gps_id' => null,
			'last_service_date' => null,
			'description' => 'DEEPAL L07 is an all-electric sedan with modern design and a comfortable luxury cabin.',
		],
		[
			'category_key' => 'cars',
			'vehicle_type' => 'cars',
			'short_name' => 'L07',
			'full_name' => 'DEEPAL L07',
			'price_per_day' => 70,
			'driver_age_requirement' => 21,
			'image_path' => 'images/deepal-l07-white.png',
			'number_of_seats' => 5,
			'transmission_type' => 'automatic',
			'fuel_type' => 'electric',
			'license_plate' => 'BAA-1238',
			'status' => 'available',
			'gps_id' => null,
			'last_service_date' => null,
			'description' => 'DEEPAL L07 is an all-electric sedan with modern design and a comfortable luxury cabin.',
		],
		[
			'category_key' => 'bikes',
			'vehicle_type' => 'bikes',
			'short_name' => 'Vespa',
			'full_name' => 'Vespa ZX 125',
			'price_per_day' => 30,
			'driver_age_requirement' => 18,
			'image_path' => 'images/bikes.png',
			'number_of_seats' => 2,
			'transmission_type' => 'N/A',
			'fuel_type' => 'petrol',
			'license_plate' => 'BAA-1723',
			'status' => 'available',
			'gps_id' => null,
			'last_service_date' => null,
			'description' => 'Vespa ZX 125 offers practical commuting performance with classic scooter styling.',
		],
		[
			'category_key' => 'luxury',
			'vehicle_type' => 'luxury',
			'short_name' => 'SL-Class (R231)',
			'full_name' => 'Mercedes-Benz SL-Class (R231)',
			'price_per_day' => 100,
			'driver_age_requirement' => 21,
			'image_path' => 'images/luxury.png',
			'number_of_seats' => 2,
			'transmission_type' => 'hybrid',
			'fuel_type' => 'petrol',
			'license_plate' => 'BAA-1903',
			'status' => 'available',
			'gps_id' => null,
			'last_service_date' => null,
			'description' => 'Mercedes-Benz SL-Class delivers premium grand touring comfort with high-performance engineering.',
		],
	];

	$vehicleUpsertStmt = $pdo->prepare(
		'INSERT INTO vehicles (
			category_id,
			vehicle_type,
			short_name,
			full_name,
			price_per_day,
			driver_age_requirement,
			image_path,
			number_of_seats,
			transmission_type,
			fuel_type,
			license_plate,
			status,
			gps_id,
			last_service_date,
			description
		) VALUES (
			:category_id,
			:vehicle_type,
			:short_name,
			:full_name,
			:price_per_day,
			:driver_age_requirement,
			:image_path,
			:number_of_seats,
			:transmission_type,
			:fuel_type,
			:license_plate,
			:status,
			:gps_id,
			:last_service_date,
			:description
		)
		ON DUPLICATE KEY UPDATE
			category_id = VALUES(category_id),
			vehicle_type = VALUES(vehicle_type),
			short_name = VALUES(short_name),
			full_name = VALUES(full_name),
			price_per_day = VALUES(price_per_day),
			driver_age_requirement = VALUES(driver_age_requirement),
			image_path = VALUES(image_path),
			number_of_seats = VALUES(number_of_seats),
			transmission_type = VALUES(transmission_type),
			fuel_type = VALUES(fuel_type),
			status = VALUES(status),
			gps_id = VALUES(gps_id),
			last_service_date = VALUES(last_service_date),
			description = VALUES(description),
			updated_at = CURRENT_TIMESTAMP'
	);

	$upsertedVehicleCount = 0;
	foreach ($vehicles as $vehicle) {
		$categoryKey = strtolower((string) ($vehicle['category_key'] ?? ''));
		$categoryId = (int) ($categoryIdsByType[$categoryKey] ?? 0);
		if ($categoryId <= 0) {
			throw new RuntimeException('Missing category for vehicle seed key: ' . $categoryKey);
		}

		$vehicleUpsertStmt->execute([
			'category_id' => $categoryId,
			'vehicle_type' => $vehicle['vehicle_type'],
			'short_name' => $vehicle['short_name'],
			'full_name' => $vehicle['full_name'],
			'price_per_day' => $vehicle['price_per_day'],
			'driver_age_requirement' => $vehicle['driver_age_requirement'],
			'image_path' => $vehicle['image_path'],
			'number_of_seats' => $vehicle['number_of_seats'],
			'transmission_type' => $vehicle['transmission_type'],
			'fuel_type' => $vehicle['fuel_type'],
			'license_plate' => $vehicle['license_plate'],
			'status' => $vehicle['status'],
			'gps_id' => $vehicle['gps_id'],
			'last_service_date' => $vehicle['last_service_date'],
			'description' => $vehicle['description'],
		]);
		$upsertedVehicleCount++;
	}

	$pdo->commit();
	echo 'Seed completed. Vehicle rows processed: ' . $upsertedVehicleCount . PHP_EOL;
} catch (Throwable $exception) {
	$pdo->rollBack();
	fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
