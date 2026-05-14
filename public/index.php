<?php
/**
 * Purpose: Front controller for web requests; loads config, routes, and dispatches controllers.
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Helpers/vehicle_json_sync.php';
require_once __DIR__ . '/../src/Helpers/runtime_sync.php';
require_once __DIR__ . '/../src/Helpers/booking_flow.php';
require_once __DIR__ . '/../src/Helpers/auth.php';
require_once __DIR__ . '/../src/Helpers/validation.php';
require_once __DIR__ . '/../src/Helpers/url.php';
require_once __DIR__ . '/../src/Helpers/logs.php';
require_once __DIR__ . '/../src/Models/BaseModel.php';
require_once __DIR__ . '/../src/Models/Category.php';
require_once __DIR__ . '/../src/Models/Session.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/Booking.php';
require_once __DIR__ . '/../src/Models/Payment.php';
require_once __DIR__ . '/../src/Models/Vehicle.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$vehicleJsonSyncDir = APP_ROOT . '/data/vehicles-json';
$defaultVehicleSyncStrategy = strtolower((string) env('APP_ENV', 'production')) === 'production'
	? 'db-first'
	: 'json-first';
$vehicleSyncStrategy = strtolower(trim((string) env('VEHICLE_SYNC_STRATEGY', $defaultVehicleSyncStrategy)));
if (!in_array($vehicleSyncStrategy, ['db-first', 'json-first'], true)) {
	$vehicleSyncStrategy = $defaultVehicleSyncStrategy;
}

$toBoolEnv = static function ($value): bool {
	$normalized = strtolower(trim((string) $value));
	return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
};

$defaultLegacyMirror = strtolower((string) env('APP_ENV', 'production')) === 'local' ? '1' : '0';
$enableLegacyVehicleJsonMirror = $toBoolEnv(env('ENABLE_LEGACY_JSON_MIRROR', $defaultLegacyMirror));
$enableGpsRuntimeCoverage = $toBoolEnv(env('ENABLE_GPS_RUNTIME_COVERAGE', '0'));

$legacyVehicleJsonSyncDir = APP_ROOT . '/var/cache/vehicles-json';
$mirrorVehicleJsonFiles = static function (string $sourceDir, string $targetDir) use ($enableLegacyVehicleJsonMirror): void {
	if (!$enableLegacyVehicleJsonMirror) {
		return;
	}

	$vehicleTypes = ['cars', 'bikes', 'luxury'];
	if (!is_dir($sourceDir)) {
		return;
	}

	if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
		error_log('Vehicle JSON mirror failed: unable to create directory ' . $targetDir);
		return;
	}

	foreach ($vehicleTypes as $vehicleType) {
		$sourceFile = rtrim($sourceDir, '\\/') . DIRECTORY_SEPARATOR . $vehicleType . '.json';
		$targetFile = rtrim($targetDir, '\\/') . DIRECTORY_SEPARATOR . $vehicleType . '.json';
		if (!is_file($sourceFile)) {
			continue;
		}

		$sourceMtime = (int) (@filemtime($sourceFile) ?: 0);
		$targetMtime = is_file($targetFile) ? (int) (@filemtime($targetFile) ?: 0) : 0;
		if ($sourceMtime <= $targetMtime) {
			continue;
		}

		if (!@copy($sourceFile, $targetFile)) {
			error_log('Vehicle JSON mirror failed while copying ' . $sourceFile . ' to ' . $targetFile);
		}
	}
};

// maintenance edit/fillup form: ensure the maintenance records table exists before maintenance CRUD actions.
$ensureMaintenanceRecordsTable = static function (PDO $pdo): void {
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS vehicle_maintenance_records (
			id INT AUTO_INCREMENT PRIMARY KEY,
			vehicle_id INT NOT NULL,
			issue_description TEXT NOT NULL,
			workshop_name VARCHAR(150) NOT NULL,
			estimate_completion_date DATE NOT NULL,
			service_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
			status ENUM("open","completed") NOT NULL DEFAULT "open",
			completed_at TIMESTAMP NULL DEFAULT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			CONSTRAINT fk_maintenance_records_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
			INDEX idx_maintenance_vehicle_status (vehicle_id, status),
			INDEX idx_maintenance_vehicle_created (vehicle_id, created_at)
		)'
	);
};

// admin login: shared form state for login modal
$adminLoginError = '';
$adminLoginEmail = '';
$adminLoginEmailInvalid = false;
$adminLoginPasswordInvalid = false;
$userLoginError = '';
$userLoginIdentifier = '';
$userLoginIdentifierInvalid = false;
$userLoginPasswordInvalid = false;
$userRegisterErrors = [];
$userRegisterOld = [];
$userRegisterSuccessEmail = '';
$userPostAuthRedirect = 'index.php';

$isAdminLoginPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-login';

$isAdminLogoutPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-logout';

$isUserRegisterPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'user-register';

$isUserLoginPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'user-login';

$isUserLogoutPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'user-logout';

$isUserBookingCreatePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'user-booking-create';

$isUserBookingCancellationRequestPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'user-request-booking-cancellation';

$isAdminDeleteVehiclePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-delete-vehicle';

$isAdminStartMaintenancePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-start-maintenance';

$isAdminUpdateMaintenancePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-update-maintenance';

$isAdminCompleteMaintenancePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-complete-maintenance';

$isAdminCreateVehiclePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-create-vehicle';

$isAdminUpdateVehiclePost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-update-vehicle';

$isAdminCompleteBookingPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-complete-booking';

$isAdminApproveBookingCancellationPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-approve-booking-cancellation';

$isAdminDeleteBookingPost =
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& strtolower(trim((string) ($_POST['action'] ?? ''))) === 'admin-delete-booking';

$normalizePostAuthRedirect = static function ($rawRedirect): string {
	return ridex_normalize_post_auth_redirect($rawRedirect);
};

$ensureVehicleSoftDeleteColumn = static function (PDO $pdo): void {
	ridex_basemodel_ensure_vehicle_soft_delete_column($pdo);
};

$ensureVehicleMileageColumn = static function (PDO $pdo): void {
	ridex_basemodel_ensure_vehicle_mileage_column($pdo);
};

$ensureVehicleImagesTable = static function (PDO $pdo): void {
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS vehicle_images (
			id INT AUTO_INCREMENT PRIMARY KEY,
			vehicle_id INT NOT NULL,
			image_path VARCHAR(255) NOT NULL,
			is_primary TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			CONSTRAINT fk_vehicle_images_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
			UNIQUE KEY uniq_vehicle_images_sort (vehicle_id, sort_order),
			INDEX idx_vehicle_images_vehicle (vehicle_id),
			INDEX idx_vehicle_images_primary (vehicle_id, is_primary)
		)'
	);
};

$collectVehicleImageUploads = static function ($uploadInput, int $maxFiles = 5): array {
	$result = [
		'files' => [],
		'has_error' => false,
	];

	if (!is_array($uploadInput) || !array_key_exists('name', $uploadInput)) {
		return $result;
	}

	$names = $uploadInput['name'] ?? [];
	if (!is_array($names)) {
		$errorCode = (int) ($uploadInput['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($errorCode === UPLOAD_ERR_NO_FILE) {
			return $result;
		}

		if ($errorCode !== UPLOAD_ERR_OK) {
			$result['has_error'] = true;
			return $result;
		}

		$tmpName = trim((string) ($uploadInput['tmp_name'] ?? ''));
		$fileName = trim((string) ($uploadInput['name'] ?? ''));
		if ($tmpName === '' || $fileName === '') {
			$result['has_error'] = true;
			return $result;
		}

		$result['files'][] = [
			'name' => $fileName,
			'tmp_name' => $tmpName,
		];

		return $result;
	}

	foreach ($names as $index => $rawName) {
		$errorCode = (int) ($uploadInput['error'][$index] ?? UPLOAD_ERR_NO_FILE);
		if ($errorCode === UPLOAD_ERR_NO_FILE) {
			continue;
		}

		if ($errorCode !== UPLOAD_ERR_OK) {
			$result['has_error'] = true;
			continue;
		}

		$fileName = trim((string) $rawName);
		$tmpName = trim((string) ($uploadInput['tmp_name'][$index] ?? ''));
		if ($fileName === '' || $tmpName === '') {
			$result['has_error'] = true;
			continue;
		}

		if (count($result['files']) >= $maxFiles) {
			$result['has_error'] = true;
			continue;
		}

		$result['files'][] = [
			'name' => $fileName,
			'tmp_name' => $tmpName,
		];
	}

	return $result;
};

$ensureGpsLogsTable = static function (PDO $pdo): void {
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS gps_logs (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			vehicle_id INT NOT NULL,
			timestamp DATETIME NOT NULL,
			latitude DECIMAL(9,6) NOT NULL,
			longitude DECIMAL(9,6) NOT NULL,
				heading DECIMAL(5,2) NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			CONSTRAINT fk_gpslogs_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
			INDEX idx_vehicle_timestamp (vehicle_id, timestamp)
		)'
	);
};

$ensurePaymentsTable = static function (PDO $pdo): void {
	ridex_payment_ensure_table($pdo);
};

$ensureVehicleGpsCoverage = static function () use ($ensureGpsLogsTable): void {
	try {
		$pdo = db();
		$ensureGpsLogsTable($pdo);

		$vehicleRows = $pdo->query('SELECT id, gps_id FROM vehicles ORDER BY id ASC')->fetchAll() ?: [];
		if (empty($vehicleRows)) {
			return;
		}

		$existingGpsVehicleIds = $pdo->query('SELECT DISTINCT vehicle_id FROM gps_logs')->fetchAll(PDO::FETCH_COLUMN);
		$gpsVehicleLookup = [];
		foreach ($existingGpsVehicleIds as $gpsVehicleId) {
			$gpsVehicleLookup[(int) $gpsVehicleId] = true;
		}

		$updateGpsIdStmt = $pdo->prepare(
			'UPDATE vehicles
			 SET gps_id = :gps_id,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :id'
		);

		$insertPlaceholderGpsStmt = $pdo->prepare(
			'INSERT INTO gps_logs (
				vehicle_id,
				timestamp,
				latitude,
				longitude,
				heading
			) VALUES (
				:vehicle_id,
				CURRENT_TIMESTAMP,
				:latitude,
				:longitude,
				NULL
			)'
		);

		$pdo->beginTransaction();

		foreach ($vehicleRows as $vehicleRow) {
			$vehicleId = (int) ($vehicleRow['id'] ?? 0);
			if ($vehicleId <= 0) {
				continue;
			}

			$targetGpsId = 'GP-' . $vehicleId;
			$currentGpsId = trim((string) ($vehicleRow['gps_id'] ?? ''));
			if ($currentGpsId !== $targetGpsId) {
				$updateGpsIdStmt->execute([
					'gps_id' => $targetGpsId,
					'id' => $vehicleId,
				]);
			}

			if (!isset($gpsVehicleLookup[$vehicleId])) {
				$insertPlaceholderGpsStmt->execute([
					'vehicle_id' => $vehicleId,
					'latitude' => 0,
					'longitude' => 0,
				]);
				$gpsVehicleLookup[$vehicleId] = true;
			}
		}

		$pdo->commit();
	} catch (Throwable $exception) {
		if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
			$pdo->rollBack();
		}

		ridex_log_exception('Vehicle GPS normalization failed', $exception);
	}
};

if ($isAdminLogoutPost || $isUserLogoutPost) {
	ridex_session_clear_auth_context();
	session_regenerate_id(true);
	ridex_redirect('index.php', 303);
}

if (!$isUserRegisterPost) {
	$userRegisterFlash = ridex_session_pull_flash('user_register_flash');
	if (!empty($userRegisterFlash)) {
		$userRegisterErrors = isset($userRegisterFlash['errors']) && is_array($userRegisterFlash['errors'])
			? $userRegisterFlash['errors']
			: [];
		$userRegisterOld = isset($userRegisterFlash['old']) && is_array($userRegisterFlash['old'])
			? $userRegisterFlash['old']
			: [];
		$userPostAuthRedirect = $normalizePostAuthRedirect($userRegisterOld['post_auth_redirect'] ?? $userPostAuthRedirect);
	}

	$userRegisterSuccess = ridex_session_pull_flash('user_register_success');
	if (!empty($userRegisterSuccess)) {
		$userRegisterSuccessEmail = trim((string) ($userRegisterSuccess['email'] ?? ''));
	}
}

if ($isUserRegisterPost) {
	$userPostAuthRedirectInput = $normalizePostAuthRedirect($_POST['post_auth_redirect'] ?? 'index.php');
	$userPostAuthRedirect = $userPostAuthRedirectInput;
	$allowedProvinces = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudurpashchim'];

	$firstName = trim((string) ($_POST['first_name'] ?? ''));
	$lastName = trim((string) ($_POST['last_name'] ?? ''));
	$dateOfBirthRaw = trim((string) ($_POST['date_of_birth'] ?? ''));
	$dateOfBirth = ridex_normalize_date_for_storage($dateOfBirthRaw);
	$driversId = trim((string) ($_POST['drivers_id'] ?? ''));
	$driversIdImageUpload = $_FILES['drivers_id_image'] ?? null;
	$phoneNumberRaw = trim((string) ($_POST['phone'] ?? ''));
	$phoneParts = ridex_normalize_phone_for_storage($phoneNumberRaw);
	$phoneLocalDigits = (string) ($phoneParts['local_digits'] ?? '');
	$phoneNumber = (string) ($phoneParts['formatted'] ?? '+977 ');
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$street = trim((string) ($_POST['street'] ?? ''));
	$postCode = trim((string) ($_POST['post_code'] ?? ''));
	$city = trim((string) ($_POST['city'] ?? ''));
	$province = trim((string) ($_POST['province'] ?? ''));
	if ($province === '') {
		$province = 'Bagmati';
	}
	$password = (string) ($_POST['password'] ?? '');

	$subscribeNewsletter = ridex_bool_from_form_value($_POST['subscribe_newsletter'] ?? '');

	$termsPrivacyAccepted = ridex_bool_from_form_value($_POST['terms_privacy'] ?? '');
	$termsDepositAccepted = ridex_bool_from_form_value($_POST['terms_deposit'] ?? '');
	$termsDamageAccepted = ridex_bool_from_form_value($_POST['terms_damage'] ?? '');

	$userRegisterOld = [
		'first_name' => $firstName,
		'last_name' => $lastName,
		'date_of_birth' => $dateOfBirthRaw,
		'drivers_id' => $driversId,
		'phone' => $phoneNumberRaw === '' ? '' : $phoneNumber,
		'email' => $email,
		'street' => $street,
		'post_code' => $postCode,
		'city' => $city,
		'province' => $province,
		'subscribe_newsletter' => $subscribeNewsletter,
		'terms_privacy' => $termsPrivacyAccepted,
		'terms_deposit' => $termsDepositAccepted,
		'terms_damage' => $termsDamageAccepted,
		'post_auth_redirect' => $userPostAuthRedirectInput,
	];

	$registrationErrors = [];

	if ($firstName === '') {
		$registrationErrors[] = 'First name is required.';
	}

	if ($lastName === '') {
		$registrationErrors[] = 'Last name is required.';
	}

	if ($dateOfBirth === '') {
		$registrationErrors[] = 'Date of birth is required.';
	} else {
		try {
			$dateOfBirthDate = new DateTimeImmutable($dateOfBirth);
			$today = new DateTimeImmutable('today');
			if ($dateOfBirthDate > $today) {
				$registrationErrors[] = 'Date of birth cannot be in the future.';
			} elseif ((int) $dateOfBirthDate->diff($today)->y < 18) {
				$registrationErrors[] = 'You must be at least 18 years old to register.';
			}
		} catch (Throwable $exception) {
			$registrationErrors[] = 'Date of birth is invalid.';
		}
	}

	if ($driversId === '') {
		$registrationErrors[] = 'Driver\'s ID is required.';
	}

	if (!is_array($driversIdImageUpload) || (int) ($driversIdImageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		$registrationErrors[] = 'Driver\'s ID image is required.';
	} else {
		$driversIdImageTmpPath = (string) ($driversIdImageUpload['tmp_name'] ?? '');
		$driversIdImageMimeType = '';
		if ($driversIdImageTmpPath !== '' && is_file($driversIdImageTmpPath)) {
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$driversIdImageMimeType = (string) $finfo->file($driversIdImageTmpPath);
		}

		$allowedDriversIdImageMimeTypes = [
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
		];

		if (!array_key_exists($driversIdImageMimeType, $allowedDriversIdImageMimeTypes)) {
			$registrationErrors[] = 'Driver\'s ID image must be a JPG, PNG, WEBP, or GIF file.';
		}
	}

	if ($phoneNumberRaw === '') {
		$registrationErrors[] = 'Phone number is required.';
	} else {
		if (!ridex_is_valid_nepal_phone_local_digits($phoneLocalDigits)) {
			$registrationErrors[] = 'Phone number must be in +977 9XXXXXXXXX format.';
		}
	}

	if ($email === '') {
		$registrationErrors[] = 'Email is required.';
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$registrationErrors[] = 'Email format is invalid.';
	}

	if ($street === '') {
		$registrationErrors[] = 'Street is required.';
	}

	if ($postCode === '') {
		$registrationErrors[] = 'Post code is required.';
	}

	if ($city === '') {
		$registrationErrors[] = 'City is required.';
	}

	if ($province === '' || !in_array($province, $allowedProvinces, true)) {
		$registrationErrors[] = 'Please select a valid province.';
	}

	if (!ridex_is_valid_password_strength($password)) {
		$registrationErrors[] = 'Password must contain lowercase, uppercase, digit, symbol, and at least 8 characters.';
	}

	if (!$termsPrivacyAccepted || !$termsDepositAccepted || !$termsDamageAccepted) {
		$registrationErrors[] = 'All policy checkboxes must be accepted to continue.';
	}

	$redirectWithUserRegisterFlash = static function (array $errors, array $old): void {
		ridex_session_set_user_register_flash($errors, $old);
		ridex_redirect('index.php', 303);
	};

	if (!empty($registrationErrors)) {
		$redirectWithUserRegisterFlash($registrationErrors, $userRegisterOld);
	}

	$driverIdImageRelativePath = '';

	try {
		$pdo = db();

		$existingUserId = ridex_user_find_id_by_email($pdo, $email);
		if ($existingUserId > 0) {
			$registrationErrors[] = 'This email is already registered.';
			$redirectWithUserRegisterFlash($registrationErrors, $userRegisterOld);
		}

		$existingDriverIdUser = ridex_user_find_id_by_driver_id($pdo, $driversId, 'user');
		if ($existingDriverIdUser > 0) {
			$registrationErrors[] = 'This Driver ID has already been used to create an account.';
			$redirectWithUserRegisterFlash($registrationErrors, $userRegisterOld);
		}

		$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		if (!is_string($passwordHash) || $passwordHash === '') {
			throw new RuntimeException('Unable to hash user password.');
		}

		$fullName = trim($firstName . ' ' . $lastName);
		if ($fullName === '') {
			$fullName = 'Ridex User';
		}

		$addressParts = array_values(array_filter([
			$street,
			$city,
			$province,
		], static function ($value): bool {
			return trim((string) $value) !== '';
		}));
		$address = implode(', ', $addressParts);
		$driverIdImageRelativePath = '';
		if (is_array($driversIdImageUpload) && (int) ($driversIdImageUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
			$driversIdImageTmpPath = (string) ($driversIdImageUpload['tmp_name'] ?? '');
			$driversIdImageMimeType = '';
			if ($driversIdImageTmpPath !== '' && is_file($driversIdImageTmpPath)) {
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				$driversIdImageMimeType = (string) $finfo->file($driversIdImageTmpPath);
			}

			$allowedDriversIdImageMimeTypes = [
				'image/jpeg' => 'jpg',
				'image/png' => 'png',
				'image/webp' => 'webp',
				'image/gif' => 'gif',
			];
			$driversIdImageExtension = $allowedDriversIdImageMimeTypes[$driversIdImageMimeType] ?? '';
			if ($driversIdImageTmpPath === '' || !is_file($driversIdImageTmpPath) || $driversIdImageExtension === '') {
				throw new RuntimeException('Invalid Driver\'s ID image upload.');
			}

			$driverIdImageUploadDir = APP_ROOT . '/public/uploads/profiles/driver-ids';
			if (!is_dir($driverIdImageUploadDir) && !mkdir($driverIdImageUploadDir, 0775, true) && !is_dir($driverIdImageUploadDir)) {
				throw new RuntimeException('Unable to create driver ID upload directory.');
			}

			$driverIdImageFileName = 'driver-id-' . bin2hex(random_bytes(8)) . '.' . $driversIdImageExtension;
			$driverIdImageAbsolutePath = $driverIdImageUploadDir . DIRECTORY_SEPARATOR . $driverIdImageFileName;
			if (!move_uploaded_file($driversIdImageTmpPath, $driverIdImageAbsolutePath)) {
				throw new RuntimeException('Unable to store Driver\'s ID image.');
			}

			$driverIdImageRelativePath = 'uploads/profiles/driver-ids/' . $driverIdImageFileName;
		}

		$newUserId = ridex_user_create_registered_user($pdo, [
			'name' => $fullName,
			'first_name' => $firstName,
			'last_name' => $lastName,
			'email' => $email,
			'password_hash' => $passwordHash,
			'phone' => $phoneNumber,
			'address' => $address,
			'date_of_birth' => $dateOfBirth,
			'street' => $street,
			'post_code' => $postCode,
			'city' => $city,
			'province' => $province,
			'drivers_id' => $driversId,
			'drivers_id_image_path' => $driverIdImageRelativePath,
		]);
		session_regenerate_id(true);
		$_SESSION['auth_user'] = [
			'id' => $newUserId,
			'name' => $fullName,
			'email' => $email,
			'phone' => $phoneNumber,
			'drivers_id' => $driversId,
			'drivers_id_image_path' => $driverIdImageRelativePath,
			'date_of_birth' => $dateOfBirth,
			'role' => 'user',
		];

		ridex_session_pull_flash('user_register_flash');
		$successfulRegisterRedirect = $normalizePostAuthRedirect($userPostAuthRedirectInput);
		if ($successfulRegisterRedirect !== 'index.php') {
			ridex_session_pull_flash('user_register_success');
			ridex_redirect($successfulRegisterRedirect, 303);
		}

		ridex_session_set_user_register_success($email, $subscribeNewsletter);
		ridex_redirect('index.php', 303);
	} catch (Throwable $exception) {
		if (!empty($driverIdImageRelativePath)) {
			$driverIdImageAbsolutePath = APP_ROOT . '/public/' . ltrim($driverIdImageRelativePath, '/\\');
			if (is_file($driverIdImageAbsolutePath)) {
				@unlink($driverIdImageAbsolutePath);
			}
		}
		ridex_log_exception('User register failed', $exception);
		$registrationErrors[] = 'Unable to create account right now. Please try again.';
		$redirectWithUserRegisterFlash($registrationErrors, $userRegisterOld);
	}
}

// admin fleet delete: process delete requests from the Manage Fleet confirmation modal
// and persist the deletion directly in DB, then return to the same fleet filter context.
if ($isAdminDeleteVehiclePost) {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$deleteAllowedModes = ['type', 'status'];
	$deleteAllowedTypes = ['cars', 'bikes', 'luxury'];
	$deleteAllowedStatuses = ['reserved', 'on_trip', 'overdue', 'maintenance'];

	$deleteFleetMode = strtolower(trim((string) ($_POST['fleet_mode'] ?? 'type')));
	if (!in_array($deleteFleetMode, $deleteAllowedModes, true)) {
		$deleteFleetMode = 'type';
	}

	$deleteFleetType = strtolower(trim((string) ($_POST['fleet_type'] ?? 'cars')));
	if (!in_array($deleteFleetType, $deleteAllowedTypes, true)) {
		$deleteFleetType = 'cars';
	}

	$deleteFleetStatus = strtolower(trim((string) ($_POST['fleet_status'] ?? 'reserved')));
	if (!in_array($deleteFleetStatus, $deleteAllowedStatuses, true)) {
		$deleteFleetStatus = 'reserved';
	}

	$deleteVehicleId = (int) ($_POST['vehicle_id'] ?? 0);

	$deleteRedirectQuery = [
		'page' => 'admin-manage-fleet',
		'fleet_mode' => $deleteFleetMode,
	];
	if ($deleteFleetMode === 'status') {
		$deleteRedirectQuery['fleet_status'] = $deleteFleetStatus;
	} else {
		$deleteRedirectQuery['fleet_type'] = $deleteFleetType;
	}
	$deleteRedirectUrl = ridex_url_with_query($deleteRedirectQuery);

	if ($deleteVehicleId > 0) {
		try {
			$pdo = db();
			$ensureVehicleSoftDeleteColumn($pdo);
			$ensureVehicleMileageColumn($pdo);
			$ensureVehicleImagesTable($pdo);
			$mirrorVehicleJsonFiles($legacyVehicleJsonSyncDir, $vehicleJsonSyncDir);

			// Ensure sync state exists before delete so DB-side deletion is detected as an intentional remove.
			sync_vehicles_json_bidirectional($pdo, $vehicleJsonSyncDir, [
				'prefer_json' => false,
				'prefer_db_timestamps' => true,
			]);

			$pdo->beginTransaction();

			$linkedBookingsStmt = $pdo->prepare(
				'SELECT
					COUNT(*) AS linked_count,
					SUM(
						CASE
							WHEN status IN ("reserved", "on_trip", "overdue")
								AND return_time IS NULL
							THEN 1
							ELSE 0
						END
					) AS active_count
				 FROM bookings
				 WHERE vehicle_id = :vehicle_id
				 FOR UPDATE'
			);
			$linkedBookingsStmt->execute([
				'vehicle_id' => $deleteVehicleId,
			]);
			$linkedBookings = $linkedBookingsStmt->fetch() ?: [];
			$activeLinkedBookingCount = (int) ($linkedBookings['active_count'] ?? 0);

			if ($activeLinkedBookingCount > 0) {
				$pdo->rollBack();
				header('Location: ' . $deleteRedirectUrl, true, 303);
				exit;
			}

			$softDeleteVehicleStmt = $pdo->prepare(
				'UPDATE vehicles
				 SET status = :status,
					 deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP),
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :id
				 LIMIT 1'
			);
			$softDeleteVehicleStmt->execute([
				'status' => 'maintenance',
				'id' => $deleteVehicleId,
			]);

			$pdo->commit();

			// Keep JSON source aligned with DB delete so sync does not restore removed rows.
			sync_vehicles_json_bidirectional($pdo, $vehicleJsonSyncDir, [
				'prefer_json' => false,
				'prefer_db_timestamps' => true,
			]);
			$mirrorVehicleJsonFiles($vehicleJsonSyncDir, $legacyVehicleJsonSyncDir);
		} catch (Throwable $exception) {
			if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			error_log('Admin vehicle delete failed: ' . $exception->getMessage());
		}
	}

	header('Location: ' . $deleteRedirectUrl, true, 303);
	exit;
}

// admin booking modal actions: complete return, approve cancellation, and guarded delete from all-bookings view.
if ($isAdminCompleteBookingPost || $isAdminApproveBookingCancellationPost || $isAdminDeleteBookingPost) {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$bookingId = (int) ($_POST['booking_id'] ?? 0);
	$redirectQuery = ['page' => 'admin-all-bookings'];
	if ($bookingId > 0 && !$isAdminDeleteBookingPost) {
		$redirectQuery['open_booking_id'] = $bookingId;
	}
	$redirectUrl = ridex_url_with_query($redirectQuery);

	if ($bookingId <= 0) {
		header('Location: ' . $redirectUrl, true, 303);
		exit;
	}

	$parseAdminDateTime = static function ($rawDate): ?DateTimeImmutable {
		$rawDate = trim((string) $rawDate);
		if ($rawDate === '') {
			return null;
		}

		$formats = [
			'Y-m-d\TH:i:s',
			'Y-m-d\TH:i',
			'Y-m-d H:i:s',
			'Y-m-d H:i',
		];

		foreach ($formats as $format) {
			$parsedDate = DateTimeImmutable::createFromFormat($format, $rawDate);
			$errors = DateTimeImmutable::getLastErrors();
			$hasDateErrors = is_array($errors)
				&& ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0);

			if ($parsedDate instanceof DateTimeImmutable && !$hasDateErrors) {
				return $parsedDate;
			}
		}

		try {
			return new DateTimeImmutable($rawDate);
		} catch (Throwable $exception) {
			return null;
		}
	};

	try {
		$pdo = db();
		$pdo->beginTransaction();

		$bookingLookupStmt = $pdo->prepare(
			'SELECT
				b.id,
				b.vehicle_id,
				b.status,
				b.payment_status,
				b.payment_method,
				b.pickup_datetime,
				b.return_datetime,
				b.return_time,
				b.total_amount,
				b.paid_amount,
				b.late_fee
			 FROM bookings b
			 WHERE b.id = :id
			 LIMIT 1
			 FOR UPDATE'
		);
		$bookingLookupStmt->execute([
			'id' => $bookingId,
		]);
		$bookingRow = $bookingLookupStmt->fetch();

		if (!is_array($bookingRow)) {
			$pdo->rollBack();
			header('Location: ' . $redirectUrl, true, 303);
			exit;
		}

		$bookingStatus = strtolower(trim((string) ($bookingRow['status'] ?? '')));
		$paymentStatus = strtolower(trim((string) ($bookingRow['payment_status'] ?? '')));
		$paymentMethod = strtolower(trim((string) ($bookingRow['payment_method'] ?? '')));
		$pickupDateTime = $parseAdminDateTime($bookingRow['pickup_datetime'] ?? null);
		$scheduledReturnDateTime = $parseAdminDateTime($bookingRow['return_datetime'] ?? null);
		$nowDateTime = new DateTimeImmutable('now');

		$effectiveBookingStatus = $bookingStatus;
		if (!in_array($bookingStatus, ['completed', 'cancelled'], true)) {
			if ($scheduledReturnDateTime instanceof DateTimeImmutable && $nowDateTime > $scheduledReturnDateTime) {
				$effectiveBookingStatus = 'overdue';
			} elseif ($bookingStatus === 'reserved' && $pickupDateTime instanceof DateTimeImmutable && $nowDateTime >= $pickupDateTime) {
				$effectiveBookingStatus = 'on_trip';
			}
		}

		$vehicleId = (int) ($bookingRow['vehicle_id'] ?? 0);

		if ($isAdminCompleteBookingPost) {
			if (!in_array($effectiveBookingStatus, ['on_trip', 'overdue'], true)) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			$returnTimeInput = trim((string) ($_POST['return_time'] ?? ''));
			if ($returnTimeInput === '') {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			$actualReturnDateTime = $parseAdminDateTime($returnTimeInput);
			if (!($actualReturnDateTime instanceof DateTimeImmutable)) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			if ($actualReturnDateTime > $nowDateTime) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			if ($pickupDateTime instanceof DateTimeImmutable && $actualReturnDateTime < $pickupDateTime) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			$lateHours = 0;
			if ($scheduledReturnDateTime instanceof DateTimeImmutable && $actualReturnDateTime > $scheduledReturnDateTime) {
				$lateSeconds = $actualReturnDateTime->getTimestamp() - $scheduledReturnDateTime->getTimestamp();
				$lateHours = (int) floor($lateSeconds / 3600);
				if ($lateHours < 0) {
					$lateHours = 0;
				}
			}
			$lateFee = $lateHours * 10;

			$nextPaymentStatus = $paymentStatus;
			if (!in_array($nextPaymentStatus, ['paid', 'refunded'], true)) {
				$nextPaymentStatus = 'paid';
			}

			$nextPaidAmount = (int) ($bookingRow['paid_amount'] ?? 0);
			if ($nextPaymentStatus === 'paid') {
				$minimumPaidAmount = (int) ($bookingRow['total_amount'] ?? 0);
				if ($paymentMethod === 'pay_on_arrival') {
					$minimumPaidAmount += $lateFee;
				}

				if ($nextPaidAmount < $minimumPaidAmount) {
					$nextPaidAmount = $minimumPaidAmount;
				}
			}

			$completeBookingStmt = $pdo->prepare(
				'UPDATE bookings
				 SET status = :status,
					 payment_status = :payment_status,
					 paid_amount = :paid_amount,
					 return_time = :return_time,
					 late_fee = :late_fee,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :id'
			);
			$completeBookingStmt->execute([
				'status' => 'completed',
				'payment_status' => $nextPaymentStatus,
				'paid_amount' => $nextPaidAmount,
				'return_time' => $actualReturnDateTime->format('Y-m-d H:i:s'),
				'late_fee' => $lateFee,
				'id' => $bookingId,
			]);
		}

		if ($isAdminApproveBookingCancellationPost) {
			$canApproveCancellation = $bookingStatus === 'cancelled'
				&& in_array($paymentStatus, ['cancelled', 'pending', 'paid'], true);

			if (!$canApproveCancellation) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			$nextPaymentStatus = $paymentStatus;
			if ($paymentMethod === 'khalti' && in_array($paymentStatus, ['paid', 'cancelled', 'pending'], true)) {
				$nextPaymentStatus = 'refunded';
			} elseif ($paymentMethod === 'pay_on_arrival' && in_array($paymentStatus, ['paid', 'cancelled', 'pending'], true)) {
				$nextPaymentStatus = 'unpaid';
			} elseif ($paymentStatus === 'cancelled') {
				$nextPaymentStatus = 'refunded';
			} elseif ($paymentStatus === 'pending') {
				$nextPaymentStatus = 'unpaid';
			}

			if ($nextPaymentStatus !== $paymentStatus) {
				$nextPaidAmount = (int) ($bookingRow['paid_amount'] ?? 0);
				if ($nextPaymentStatus === 'unpaid') {
					$nextPaidAmount = 0;
				}

				$approveCancellationStmt = $pdo->prepare(
					'UPDATE bookings
					 SET payment_status = :payment_status,
						 paid_amount = :paid_amount,
						 updated_at = CURRENT_TIMESTAMP
					 WHERE id = :id'
				);
				$approveCancellationStmt->execute([
					'payment_status' => $nextPaymentStatus,
					'paid_amount' => $nextPaidAmount,
					'id' => $bookingId,
				]);
			}
		}

		if ($isAdminDeleteBookingPost) {
			$hasReturnTime = trim((string) ($bookingRow['return_time'] ?? '')) !== '';
			$canDeleteCancelledBooking = $bookingStatus === 'cancelled'
				&& in_array($paymentStatus, ['refunded', 'unpaid'], true);
			$canDeleteBooking = $bookingStatus === 'completed' || $hasReturnTime || $canDeleteCancelledBooking;

			if (!$canDeleteBooking) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			$deleteBookingStmt = $pdo->prepare('DELETE FROM bookings WHERE id = :id LIMIT 1');
			$deleteBookingStmt->execute([
				'id' => $bookingId,
			]);

			$redirectUrl = 'index.php?page=admin-all-bookings';
		}

		if ($vehicleId > 0 && ($isAdminCompleteBookingPost || $isAdminApproveBookingCancellationPost || $isAdminDeleteBookingPost)) {
			$setVehicleAvailableStmt = $pdo->prepare(
				'UPDATE vehicles
				 SET status = :status,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :id
				 LIMIT 1'
			);
			$setVehicleAvailableStmt->execute([
				'status' => 'available',
				'id' => $vehicleId,
			]);
		}

		$pdo->commit();
	} catch (Throwable $exception) {
		if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
			$pdo->rollBack();
		}

		error_log('Admin booking action failed: ' . $exception->getMessage());
	}

	header('Location: ' . $redirectUrl, true, 303);
	exit;
}

// create vehicle modal: persist a new vehicle from the 3-part create modal with full required-field validation.
if ($isAdminCreateVehiclePost) {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$allowedFleetModes = ['type', 'status'];
	$allowedFleetTypes = ['cars', 'bikes', 'luxury'];
	$allowedDriverAges = [18, 21];
	$allowedVehicleStatuses = ['available', 'reserved', 'on_trip', 'overdue', 'maintenance'];
	$allowedFuelTypes = ['petrol', 'diesel', 'electric'];
	$transmissionMap = [
		'manual' => 'manual',
		'automatic' => 'automatic',
		'hybrid' => 'hybrid',
		'n/a' => 'N/A',
		'na' => 'N/A',
	];

	$fleetMode = strtolower(trim((string) ($_POST['fleet_mode'] ?? 'type')));
	if (!in_array($fleetMode, $allowedFleetModes, true)) {
		$fleetMode = 'type';
	}

	$fleetType = strtolower(trim((string) ($_POST['fleet_type'] ?? 'cars')));
	if (!in_array($fleetType, $allowedFleetTypes, true)) {
		$fleetType = 'cars';
	}

	$vehicleType = strtolower(trim((string) ($_POST['vehicle_type'] ?? $fleetType)));
	if (!in_array($vehicleType, $allowedFleetTypes, true)) {
		$vehicleType = $fleetType;
	}

	$fullName = trim((string) ($_POST['full_name'] ?? ''));
	$shortName = trim((string) ($_POST['short_name'] ?? ''));
	$pricePerDayRaw = (float) ($_POST['price_per_day'] ?? 0);
	$driverAgeRequirement = (int) ($_POST['driver_age_requirement'] ?? 0);
	$numberOfSeats = (int) ($_POST['number_of_seats'] ?? 0);
	$transmissionInput = strtolower(trim((string) ($_POST['transmission_type'] ?? 'manual')));
	$fuelType = strtolower(trim((string) ($_POST['fuel_type'] ?? 'petrol')));
	$mileageRaw = trim((string) ($_POST['mileage_km_per_l'] ?? ''));
	$licensePlate = strtoupper(trim((string) ($_POST['license_plate'] ?? '')));
	$gpsId = trim((string) ($_POST['gps_id'] ?? ''));
	$status = strtolower(trim((string) ($_POST['status'] ?? 'available')));
	$lastServiceDateRaw = trim((string) ($_POST['last_service_date'] ?? ''));
	$description = trim((string) ($_POST['description'] ?? ''));

	$redirectQuery = [
		'page' => 'admin-manage-fleet',
		'fleet_mode' => 'type',
		'fleet_type' => $fleetType,
	];
	$redirectUrl = ridex_url_with_query($redirectQuery);

	$parseServiceDate = static function (string $rawDate): ?string {
		$normalizedDate = trim($rawDate);
		if ($normalizedDate === '') {
			return null;
		}

		$formats = ['Y-m-d', 'd/m/Y', 'd M, Y'];
		foreach ($formats as $format) {
			$parsedDate = DateTimeImmutable::createFromFormat('!' . $format, $normalizedDate);
			$errors = DateTimeImmutable::getLastErrors();
			$hasDateErrors = is_array($errors)
				&& ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0);

			if ($parsedDate instanceof DateTimeImmutable && !$hasDateErrors) {
				return $parsedDate->format('Y-m-d');
			}
		}

		return null;
	};

	$lastServiceDate = $parseServiceDate($lastServiceDateRaw);
	$transmissionType = $transmissionMap[$transmissionInput] ?? '';
	$pricePerDay = is_finite($pricePerDayRaw) ? (int) round($pricePerDayRaw) : 0;
	$mileageValue = is_numeric($mileageRaw) ? (float) $mileageRaw : 0.0;
	$mileageValue = is_finite($mileageValue) ? round($mileageValue, 2) : 0.0;
	$isLastServiceDateInFuture = false;
	if ($lastServiceDate !== null) {
		$lastServiceDateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $lastServiceDate);
		if ($lastServiceDateTime instanceof DateTimeImmutable) {
			$isLastServiceDateInFuture = $lastServiceDateTime > new DateTimeImmutable('today');
		}
	}

	$uploadCollection = $collectVehicleImageUploads(
		$_FILES['image_files'] ?? ($_FILES['image_file'] ?? null),
		5
	);
	$uploadedImageFiles = isset($uploadCollection['files']) && is_array($uploadCollection['files'])
		? $uploadCollection['files']
		: [];
	$hasUploadError = (bool) ($uploadCollection['has_error'] ?? false);

	$hasMissingRequiredData =
		$vehicleType === ''
		|| $fullName === ''
		|| $shortName === ''
		|| $pricePerDay <= 0
		|| !in_array($driverAgeRequirement, $allowedDriverAges, true)
		|| $numberOfSeats <= 0
		|| $transmissionType === ''
		|| !in_array($fuelType, $allowedFuelTypes, true)
		|| $mileageValue <= 0
		|| $licensePlate === ''
		|| $gpsId === ''
		|| !in_array($status, $allowedVehicleStatuses, true)
		|| $lastServiceDate === null
		|| $isLastServiceDateInFuture
		|| $description === ''
		|| $hasUploadError
		|| empty($uploadedImageFiles)
		|| count($uploadedImageFiles) > 5;

	if ($hasMissingRequiredData) {
		header('Location: ' . $redirectUrl, true, 303);
		exit;
	}

	$categoryNameByType = [
		'cars' => 'Cars',
		'bikes' => 'Bikes',
		'luxury' => 'Luxury',
	];
	$categoryDescriptionByType = [
		'cars' => 'Passenger cars and sedans',
		'bikes' => 'Two-wheel vehicles',
		'luxury' => 'Premium and high-end vehicles',
	];

	$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
	$allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
	$validatedUploadImages = [];
	foreach ($uploadedImageFiles as $uploadedImageFile) {
		$sourceImageName = trim((string) ($uploadedImageFile['name'] ?? ''));
		$sourceImageTmpPath = trim((string) ($uploadedImageFile['tmp_name'] ?? ''));
		$imageExtension = strtolower((string) pathinfo($sourceImageName, PATHINFO_EXTENSION));

		if ($sourceImageTmpPath === '' || !in_array($imageExtension, $allowedImageExtensions, true)) {
			header('Location: ' . $redirectUrl, true, 303);
			exit;
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$detectedMimeType = $finfo ? (string) finfo_file($finfo, $sourceImageTmpPath) : '';
		if ($finfo) {
			finfo_close($finfo);
		}

		if (!in_array($detectedMimeType, $allowedImageMimeTypes, true)) {
			header('Location: ' . $redirectUrl, true, 303);
			exit;
		}

		$validatedUploadImages[] = [
			'tmp_name' => $sourceImageTmpPath,
			'extension' => $imageExtension,
		];
	}

	$uploadDirectory = APP_ROOT . '/public/uploads/vehicles';
	if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
		header('Location: ' . $redirectUrl, true, 303);
		exit;
	}

	$syncVehicleDataAfterCreate = static function (PDO $pdo) use (
		$mirrorVehicleJsonFiles,
		$legacyVehicleJsonSyncDir,
		$vehicleJsonSyncDir
	): void {
		try {
			$mirrorVehicleJsonFiles($legacyVehicleJsonSyncDir, $vehicleJsonSyncDir);
			sync_vehicles_json_bidirectional($pdo, $vehicleJsonSyncDir, [
				'prefer_json' => false,
				'prefer_db_timestamps' => true,
			]);
			$mirrorVehicleJsonFiles($vehicleJsonSyncDir, $legacyVehicleJsonSyncDir);
		} catch (Throwable $syncException) {
			error_log('Admin create vehicle sync failed: ' . $syncException->getMessage());
		}
	};

	$storedImageAbsolutePaths = [];
	$createdVehicleId = 0;

	try {
		$pdo = db();
		$ensureVehicleImagesTable($pdo);
		$pdo->beginTransaction();

		$categoryName = $categoryNameByType[$vehicleType] ?? 'Cars';
		$categoryDescription = $categoryDescriptionByType[$vehicleType] ?? 'Vehicle category';

		$categoryLookupStmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1 FOR UPDATE');
		$categoryLookupStmt->execute([
			'name' => $categoryName,
		]);
		$categoryId = (int) $categoryLookupStmt->fetchColumn();

		if ($categoryId <= 0) {
			$createCategoryStmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
			$createCategoryStmt->execute([
				'name' => $categoryName,
				'description' => $categoryDescription,
			]);
			$categoryId = (int) $pdo->lastInsertId();
		}

		if ($categoryId <= 0) {
			throw new RuntimeException('Unable to resolve category for vehicle creation.');
		}

		$duplicateLicenseStmt = $pdo->prepare('SELECT id FROM vehicles WHERE license_plate = :license_plate LIMIT 1 FOR UPDATE');
		$duplicateLicenseStmt->execute([
			'license_plate' => $licensePlate,
		]);
		if ((int) $duplicateLicenseStmt->fetchColumn() > 0) {
			throw new RuntimeException('Vehicle license plate already exists.');
		}

		$duplicateGpsStmt = $pdo->prepare('SELECT id FROM vehicles WHERE LOWER(gps_id) = LOWER(:gps_id) LIMIT 1 FOR UPDATE');
		$duplicateGpsStmt->execute([
			'gps_id' => $gpsId,
		]);
		if ((int) $duplicateGpsStmt->fetchColumn() > 0) {
			throw new RuntimeException('Vehicle GPS ID already exists.');
		}

		$storedImageRelativePaths = [];
		foreach ($validatedUploadImages as $validatedUploadImage) {
			try {
				$randomSuffix = bin2hex(random_bytes(5));
			} catch (Throwable $randomException) {
				$randomSuffix = (string) mt_rand(10000, 99999);
			}

			$imageExtension = (string) ($validatedUploadImage['extension'] ?? 'jpg');
			$tmpImagePath = (string) ($validatedUploadImage['tmp_name'] ?? '');
			$storedImageName = 'vehicle-' . date('YmdHis') . '-' . $randomSuffix . '.' . $imageExtension;
			$storedImageAbsolutePath = rtrim($uploadDirectory, '\/') . DIRECTORY_SEPARATOR . $storedImageName;
			$storedImageRelativePath = 'uploads/vehicles/' . $storedImageName;

			if (!move_uploaded_file($tmpImagePath, $storedImageAbsolutePath)) {
				throw new RuntimeException('Vehicle image upload failed.');
			}

			$storedImageAbsolutePaths[] = $storedImageAbsolutePath;
			$storedImageRelativePaths[] = $storedImageRelativePath;
		}

		$primaryImagePath = $storedImageRelativePaths[0] ?? '';
		if ($primaryImagePath === '') {
			throw new RuntimeException('Vehicle image upload failed.');
		}

		// create vehicle modal: assign the next in-line vehicle ID (MAX(id)+1) instead of relying on auto-increment gaps.
		$nextVehicleIdStmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_vehicle_id FROM vehicles FOR UPDATE');
		$nextVehicleId = (int) $nextVehicleIdStmt->fetchColumn();
		if ($nextVehicleId <= 0) {
			$nextVehicleId = 1;
		}

		$insertVehicleStmt = $pdo->prepare(
			'INSERT INTO vehicles (
				id,
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
				mileage_km_per_l,
				license_plate,
				status,
				gps_id,
				last_service_date,
				description
			) VALUES (
				:id,
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
				:mileage_km_per_l,
				:license_plate,
				:status,
				:gps_id,
				:last_service_date,
				:description
			)'
		);
		$insertVehicleStmt->execute([
			'id' => $nextVehicleId,
			'category_id' => $categoryId,
			'vehicle_type' => $vehicleType,
			'short_name' => $shortName,
			'full_name' => $fullName,
			'price_per_day' => $pricePerDay,
			'driver_age_requirement' => $driverAgeRequirement,
			'image_path' => $primaryImagePath,
			'number_of_seats' => $numberOfSeats,
			'transmission_type' => $transmissionType,
			'fuel_type' => $fuelType,
			'mileage_km_per_l' => $mileageValue,
			'license_plate' => $licensePlate,
			'status' => $status,
			'gps_id' => $gpsId,
			'last_service_date' => $lastServiceDate,
			'description' => $description,
		]);

		$insertVehicleImageStmt = $pdo->prepare(
			'INSERT INTO vehicle_images (
				vehicle_id,
				image_path,
				is_primary,
				sort_order
			) VALUES (
				:vehicle_id,
				:image_path,
				:is_primary,
				:sort_order
			)'
		);
		foreach ($storedImageRelativePaths as $index => $imagePath) {
			$insertVehicleImageStmt->execute([
				'vehicle_id' => $nextVehicleId,
				'image_path' => $imagePath,
				'is_primary' => $index === 0 ? 1 : 0,
				'sort_order' => $index,
			]);
		}

		$createdVehicleId = $nextVehicleId;
		$pdo->commit();
		$syncVehicleDataAfterCreate($pdo);

		$successRedirectQuery = [
			'page' => 'admin-manage-fleet',
		];
		if ($status === 'available') {
			$successRedirectQuery['fleet_mode'] = 'type';
			$successRedirectQuery['fleet_type'] = $vehicleType;
		} else {
			$successRedirectQuery['fleet_mode'] = 'status';
			$successRedirectQuery['fleet_status'] = $status;
		}
		if ($createdVehicleId > 0) {
			$successRedirectQuery['open_read_vehicle_id'] = $createdVehicleId;
		}

		header('Location: index.php?' . http_build_query($successRedirectQuery), true, 303);
		exit;
	} catch (Throwable $exception) {
		if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
			$pdo->rollBack();
		}

		foreach ($storedImageAbsolutePaths as $storedImageAbsolutePath) {
			if ($storedImageAbsolutePath !== '' && is_file($storedImageAbsolutePath)) {
				@unlink($storedImageAbsolutePath);
			}
		}

		error_log('Admin create vehicle failed: ' . $exception->getMessage());
	}

	header('Location: ' . $redirectUrl, true, 303);
	exit;
}

	// edit vehicle modal: persist vehicle updates from the 3-part edit modal with optional multi-image upload.
	if ($isAdminUpdateVehiclePost) {
		$sessionUser = $_SESSION['auth_user'] ?? [];
		$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

		if (!$isAdminSession) {
			header('Location: index.php', true, 302);
			exit;
		}

		$allowedFleetModes = ['type', 'status'];
		$allowedFleetTypes = ['cars', 'bikes', 'luxury'];
		$allowedDriverAges = [18, 21];
		$allowedVehicleStatuses = ['available', 'reserved', 'on_trip', 'overdue', 'maintenance'];
		$allowedFuelTypes = ['petrol', 'diesel', 'electric'];
		$transmissionMap = [
			'manual' => 'manual',
			'automatic' => 'automatic',
			'hybrid' => 'hybrid',
			'n/a' => 'N/A',
			'na' => 'N/A',
		];

		$fleetMode = strtolower(trim((string) ($_POST['fleet_mode'] ?? 'type')));
		if (!in_array($fleetMode, $allowedFleetModes, true)) {
			$fleetMode = 'type';
		}

		$fleetType = strtolower(trim((string) ($_POST['fleet_type'] ?? 'cars')));
		if (!in_array($fleetType, $allowedFleetTypes, true)) {
			$fleetType = 'cars';
		}

		$fleetStatus = strtolower(trim((string) ($_POST['fleet_status'] ?? 'reserved')));
		if (!in_array($fleetStatus, $allowedVehicleStatuses, true)) {
			$fleetStatus = 'reserved';
		}

		$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
		$vehicleType = strtolower(trim((string) ($_POST['vehicle_type'] ?? $fleetType)));
		if (!in_array($vehicleType, $allowedFleetTypes, true)) {
			$vehicleType = $fleetType;
		}

		$fullName = trim((string) ($_POST['full_name'] ?? ''));
		$shortName = trim((string) ($_POST['short_name'] ?? ''));
		$pricePerDayRaw = (float) ($_POST['price_per_day'] ?? 0);
		$driverAgeRequirement = (int) ($_POST['driver_age_requirement'] ?? 0);
		$numberOfSeats = (int) ($_POST['number_of_seats'] ?? 0);
		$transmissionInput = strtolower(trim((string) ($_POST['transmission_type'] ?? 'manual')));
		$fuelType = strtolower(trim((string) ($_POST['fuel_type'] ?? 'petrol')));
		$mileageRaw = trim((string) ($_POST['mileage_km_per_l'] ?? ''));
		$licensePlate = strtoupper(trim((string) ($_POST['license_plate'] ?? '')));
		$gpsId = trim((string) ($_POST['gps_id'] ?? ''));
		$status = strtolower(trim((string) ($_POST['status'] ?? 'available')));
		$lastServiceDateRaw = trim((string) ($_POST['last_service_date'] ?? ''));
		$description = trim((string) ($_POST['description'] ?? ''));
		$removeCurrentImage = trim((string) ($_POST['remove_current_image'] ?? '0')) === '1';
		$replacePrimaryImage = trim((string) ($_POST['replace_primary_image'] ?? '0')) === '1';

		$redirectQuery = [
			'page' => 'admin-manage-fleet',
			'fleet_mode' => $fleetMode,
		];
		if ($fleetMode === 'status') {
			$redirectQuery['fleet_status'] = $fleetStatus;
		} else {
			$redirectQuery['fleet_type'] = $fleetType;
		}
		$redirectUrl = 'index.php?' . http_build_query($redirectQuery);

		if ($vehicleId <= 0) {
			header('Location: ' . $redirectUrl, true, 303);
			exit;
		}

		$parseServiceDate = static function (string $rawDate): ?string {
			$normalizedDate = trim($rawDate);
			if ($normalizedDate === '') {
				return null;
			}

			$formats = ['Y-m-d', 'd/m/Y', 'd M, Y'];
			foreach ($formats as $format) {
				$parsedDate = DateTimeImmutable::createFromFormat('!' . $format, $normalizedDate);
				$errors = DateTimeImmutable::getLastErrors();
				$hasDateErrors = is_array($errors)
					&& ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0);

				if ($parsedDate instanceof DateTimeImmutable && !$hasDateErrors) {
					return $parsedDate->format('Y-m-d');
				}
			}

			return null;
		};

		$lastServiceDate = $parseServiceDate($lastServiceDateRaw);
		$transmissionType = $transmissionMap[$transmissionInput] ?? '';
		$pricePerDay = is_finite($pricePerDayRaw) ? (int) round($pricePerDayRaw) : 0;
		$mileageValue = is_numeric($mileageRaw) ? (float) $mileageRaw : 0.0;
		$mileageValue = is_finite($mileageValue) ? round($mileageValue, 2) : 0.0;
		$isLastServiceDateInFuture = false;
		if ($lastServiceDate !== null) {
			$lastServiceDateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $lastServiceDate);
			if ($lastServiceDateTime instanceof DateTimeImmutable) {
				$isLastServiceDateInFuture = $lastServiceDateTime > new DateTimeImmutable('today');
			}
		}

		$uploadCollection = $collectVehicleImageUploads(
			$_FILES['image_files'] ?? ($_FILES['image_file'] ?? null),
			5
		);
		$uploadedImageFiles = isset($uploadCollection['files']) && is_array($uploadCollection['files'])
			? $uploadCollection['files']
			: [];
		$hasUploadError = (bool) ($uploadCollection['has_error'] ?? false);
		$isAddingImages = !empty($uploadedImageFiles);

		if ($hasUploadError) {
			header('Location: ' . $redirectUrl, true, 303);
			exit;
		}

		$hasMissingRequiredData =
			$vehicleType === ''
			|| $fullName === ''
			|| $shortName === ''
			|| $pricePerDay <= 0
			|| !in_array($driverAgeRequirement, $allowedDriverAges, true)
			|| $numberOfSeats <= 0
			|| $transmissionType === ''
			|| !in_array($fuelType, $allowedFuelTypes, true)
			|| $mileageValue <= 0
			|| $licensePlate === ''
			|| $gpsId === ''
			|| !in_array($status, $allowedVehicleStatuses, true)
			|| $lastServiceDate === null
			|| $isLastServiceDateInFuture
			|| $description === '';

		if ($hasMissingRequiredData) {
			header('Location: ' . $redirectUrl, true, 303);
			exit;
		}

		$uploadDirectory = APP_ROOT . '/public/uploads/vehicles';
		$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
		$allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
		$validatedUploadImages = [];
		if ($isAddingImages) {
			foreach ($uploadedImageFiles as $uploadedImageFile) {
				$sourceImageName = trim((string) ($uploadedImageFile['name'] ?? ''));
				$sourceImageTmpPath = trim((string) ($uploadedImageFile['tmp_name'] ?? ''));
				$imageExtension = strtolower((string) pathinfo($sourceImageName, PATHINFO_EXTENSION));

				if ($sourceImageTmpPath === '' || !in_array($imageExtension, $allowedImageExtensions, true)) {
					header('Location: ' . $redirectUrl, true, 303);
					exit;
				}

				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$detectedMimeType = $finfo ? (string) finfo_file($finfo, $sourceImageTmpPath) : '';
				if ($finfo) {
					finfo_close($finfo);
				}

				if (!in_array($detectedMimeType, $allowedImageMimeTypes, true)) {
					header('Location: ' . $redirectUrl, true, 303);
					exit;
				}

				$validatedUploadImages[] = [
					'tmp_name' => $sourceImageTmpPath,
					'extension' => $imageExtension,
				];
			}

			if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}
		}

		$syncVehicleDataAfterUpdate = static function (PDO $pdo) use (
			$mirrorVehicleJsonFiles,
			$legacyVehicleJsonSyncDir,
			$vehicleJsonSyncDir
		): void {
			try {
				$mirrorVehicleJsonFiles($legacyVehicleJsonSyncDir, $vehicleJsonSyncDir);
				sync_vehicles_json_bidirectional($pdo, $vehicleJsonSyncDir, [
					'prefer_json' => false,
					'prefer_db_timestamps' => true,
				]);
				$mirrorVehicleJsonFiles($vehicleJsonSyncDir, $legacyVehicleJsonSyncDir);
			} catch (Throwable $syncException) {
				error_log('Admin update vehicle sync failed: ' . $syncException->getMessage());
			}
		};

		$newImageAbsolutePaths = [];
		$previousImageRelativePath = '';

		try {
			$pdo = db();
			$ensureVehicleImagesTable($pdo);
			$pdo->beginTransaction();

			$existingVehicleStmt = $pdo->prepare(
				'SELECT id, image_path
				 FROM vehicles
				 WHERE id = :id
				 LIMIT 1
				 FOR UPDATE'
			);
			$existingVehicleStmt->execute(['id' => $vehicleId]);
			$existingVehicle = $existingVehicleStmt->fetch();

			if (!is_array($existingVehicle)) {
				$pdo->rollBack();
				header('Location: ' . $redirectUrl, true, 303);
				exit;
			}

			$previousImageRelativePath = trim((string) ($existingVehicle['image_path'] ?? ''));
			$updatedImageRelativePath = $previousImageRelativePath;

			$galleryCountStmt = $pdo->prepare('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = :vehicle_id FOR UPDATE');
			$galleryCountStmt->execute([
				'vehicle_id' => $vehicleId,
			]);
			$existingGalleryImageCount = (int) $galleryCountStmt->fetchColumn();

			if ($existingGalleryImageCount <= 0 && $previousImageRelativePath !== '') {
				$seedPrimaryImageStmt = $pdo->prepare(
					'INSERT INTO vehicle_images (
						vehicle_id,
						image_path,
						is_primary,
						sort_order
					) VALUES (
						:vehicle_id,
						:image_path,
						1,
						0
					)'
				);
				$seedPrimaryImageStmt->execute([
					'vehicle_id' => $vehicleId,
					'image_path' => $previousImageRelativePath,
				]);
				$existingGalleryImageCount = 1;
			}

			if ($removeCurrentImage && $previousImageRelativePath !== '') {
				$deleteCurrentImageStmt = $pdo->prepare(
					'DELETE FROM vehicle_images
					 WHERE vehicle_id = :vehicle_id
					   AND image_path = :image_path'
				);
				$deleteCurrentImageStmt->execute([
					'vehicle_id' => $vehicleId,
					'image_path' => $previousImageRelativePath,
				]);

				$recountGalleryStmt = $pdo->prepare('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = :vehicle_id FOR UPDATE');
				$recountGalleryStmt->execute([
					'vehicle_id' => $vehicleId,
				]);
				$existingGalleryImageCount = (int) $recountGalleryStmt->fetchColumn();

				if ($existingGalleryImageCount > 0) {
					$firstGalleryImageStmt = $pdo->prepare(
						'SELECT id, image_path
						 FROM vehicle_images
						 WHERE vehicle_id = :vehicle_id
						 ORDER BY is_primary DESC, sort_order ASC, id ASC
						 LIMIT 1
						 FOR UPDATE'
					);
					$firstGalleryImageStmt->execute([
						'vehicle_id' => $vehicleId,
					]);
					$firstGalleryImage = $firstGalleryImageStmt->fetch();

					if (is_array($firstGalleryImage)) {
						$clearPrimaryStmt = $pdo->prepare('UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = :vehicle_id');
						$clearPrimaryStmt->execute([
							'vehicle_id' => $vehicleId,
						]);

						$setPrimaryStmt = $pdo->prepare(
							'UPDATE vehicle_images
							 SET is_primary = 1,
							     sort_order = 0
							 WHERE id = :id'
						);
						$setPrimaryStmt->execute([
							'id' => (int) ($firstGalleryImage['id'] ?? 0),
						]);

						$updatedImageRelativePath = trim((string) ($firstGalleryImage['image_path'] ?? ''));
					}
				} else {
					$updatedImageRelativePath = '';
				}
			}

			$primaryReplacementImage = null;
			if ($replacePrimaryImage && $isAddingImages && !empty($validatedUploadImages)) {
				$primaryReplacementImage = array_shift($validatedUploadImages);
				$validatedUploadImages = array_values($validatedUploadImages);
			}

			$additionalUploadCount = count($validatedUploadImages);
			if ($existingGalleryImageCount + $additionalUploadCount > 5) {
				throw new RuntimeException('A vehicle can only have up to 5 images.');
			}

			$categoryNameByType = [
				'cars' => 'Cars',
				'bikes' => 'Bikes',
				'luxury' => 'Luxury',
			];
			$categoryDescriptionByType = [
				'cars' => 'Passenger cars and sedans',
				'bikes' => 'Two-wheel vehicles',
				'luxury' => 'Premium and high-end vehicles',
			];

			$categoryName = $categoryNameByType[$vehicleType] ?? 'Cars';
			$categoryDescription = $categoryDescriptionByType[$vehicleType] ?? 'Vehicle category';

			$categoryLookupStmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1 FOR UPDATE');
			$categoryLookupStmt->execute([
				'name' => $categoryName,
			]);
			$categoryId = (int) $categoryLookupStmt->fetchColumn();

			if ($categoryId <= 0) {
				$createCategoryStmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
				$createCategoryStmt->execute([
					'name' => $categoryName,
					'description' => $categoryDescription,
				]);
				$categoryId = (int) $pdo->lastInsertId();
			}

			if ($categoryId <= 0) {
				throw new RuntimeException('Unable to resolve category for vehicle update.');
			}

			$duplicateLicenseStmt = $pdo->prepare('SELECT id FROM vehicles WHERE license_plate = :license_plate AND id <> :id LIMIT 1 FOR UPDATE');
			$duplicateLicenseStmt->execute([
				'license_plate' => $licensePlate,
				'id' => $vehicleId,
			]);
			if ((int) $duplicateLicenseStmt->fetchColumn() > 0) {
				throw new RuntimeException('Vehicle license plate already exists.');
			}

			$duplicateGpsStmt = $pdo->prepare('SELECT id FROM vehicles WHERE LOWER(gps_id) = LOWER(:gps_id) AND id <> :id LIMIT 1 FOR UPDATE');
			$duplicateGpsStmt->execute([
				'gps_id' => $gpsId,
				'id' => $vehicleId,
			]);
			if ((int) $duplicateGpsStmt->fetchColumn() > 0) {
				throw new RuntimeException('Vehicle GPS ID already exists.');
			}

			if (is_array($primaryReplacementImage)) {
				try {
					$randomSuffix = bin2hex(random_bytes(5));
				} catch (Throwable $randomException) {
					$randomSuffix = (string) mt_rand(10000, 99999);
				}

				$imageExtension = (string) ($primaryReplacementImage['extension'] ?? 'jpg');
				$tmpImagePath = (string) ($primaryReplacementImage['tmp_name'] ?? '');
				$newImageName = 'vehicle-' . date('YmdHis') . '-' . $randomSuffix . '.' . $imageExtension;
				$newImageAbsolutePath = rtrim($uploadDirectory, '\/') . DIRECTORY_SEPARATOR . $newImageName;
				$newImageRelativePath = 'uploads/vehicles/' . $newImageName;

				if (!move_uploaded_file($tmpImagePath, $newImageAbsolutePath)) {
					throw new RuntimeException('Vehicle image upload failed.');
				}

				$newImageAbsolutePaths[] = $newImageAbsolutePath;

				$primaryRowStmt = $pdo->prepare(
					'SELECT id, image_path
					 FROM vehicle_images
					 WHERE vehicle_id = :vehicle_id
					   AND is_primary = 1
					 ORDER BY sort_order ASC, id ASC
					 LIMIT 1
					 FOR UPDATE'
				);
				$primaryRowStmt->execute([
					'vehicle_id' => $vehicleId,
				]);
				$primaryRow = $primaryRowStmt->fetch();

				if (!is_array($primaryRow)) {
					$fallbackRowStmt = $pdo->prepare(
						'SELECT id, image_path
						 FROM vehicle_images
						 WHERE vehicle_id = :vehicle_id
						 ORDER BY sort_order ASC, id ASC
						 LIMIT 1
						 FOR UPDATE'
					);
					$fallbackRowStmt->execute([
						'vehicle_id' => $vehicleId,
					]);
					$primaryRow = $fallbackRowStmt->fetch();
				}

				if (is_array($primaryRow)) {
					$clearPrimaryStmt = $pdo->prepare('UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = :vehicle_id');
					$clearPrimaryStmt->execute([
						'vehicle_id' => $vehicleId,
					]);

					$updatePrimaryStmt = $pdo->prepare(
						'UPDATE vehicle_images
						 SET image_path = :image_path,
						     is_primary = 1
						 WHERE id = :id'
					);
					$updatePrimaryStmt->execute([
						'image_path' => $newImageRelativePath,
						'id' => (int) ($primaryRow['id'] ?? 0),
					]);
				} else {
					$insertPrimaryStmt = $pdo->prepare(
						'INSERT INTO vehicle_images (
							vehicle_id,
							image_path,
							is_primary,
							sort_order
						) VALUES (
							:vehicle_id,
							:image_path,
							1,
							0
						)'
					);
					$insertPrimaryStmt->execute([
						'vehicle_id' => $vehicleId,
						'image_path' => $newImageRelativePath,
					]);
					$existingGalleryImageCount = 1;
				}

				$updatedImageRelativePath = $newImageRelativePath;
			}

			if (!empty($validatedUploadImages)) {
				$maxSortOrderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM vehicle_images WHERE vehicle_id = :vehicle_id FOR UPDATE');
				$maxSortOrderStmt->execute([
					'vehicle_id' => $vehicleId,
				]);
				$nextSortOrder = (int) $maxSortOrderStmt->fetchColumn() + 1;

				$insertVehicleImageStmt = $pdo->prepare(
					'INSERT INTO vehicle_images (
						vehicle_id,
						image_path,
						is_primary,
						sort_order
					) VALUES (
						:vehicle_id,
						:image_path,
						:is_primary,
						:sort_order
					)'
				);

				foreach ($validatedUploadImages as $index => $validatedUploadImage) {
					try {
						$randomSuffix = bin2hex(random_bytes(5));
					} catch (Throwable $randomException) {
						$randomSuffix = (string) mt_rand(10000, 99999);
					}

					$imageExtension = (string) ($validatedUploadImage['extension'] ?? 'jpg');
					$tmpImagePath = (string) ($validatedUploadImage['tmp_name'] ?? '');
					$newImageName = 'vehicle-' . date('YmdHis') . '-' . $randomSuffix . '.' . $imageExtension;
					$newImageAbsolutePath = rtrim($uploadDirectory, '\/') . DIRECTORY_SEPARATOR . $newImageName;
					$newImageRelativePath = 'uploads/vehicles/' . $newImageName;

					if (!move_uploaded_file($tmpImagePath, $newImageAbsolutePath)) {
						throw new RuntimeException('Vehicle image upload failed.');
					}

					$newImageAbsolutePaths[] = $newImageAbsolutePath;

					$isPrimaryImage = $existingGalleryImageCount <= 0 && $index === 0 && $primaryReplacementImage === null;
					$insertVehicleImageStmt->execute([
						'vehicle_id' => $vehicleId,
						'image_path' => $newImageRelativePath,
						'is_primary' => $isPrimaryImage ? 1 : 0,
						'sort_order' => $nextSortOrder,
					]);
					$nextSortOrder += 1;

					if ($isPrimaryImage || $updatedImageRelativePath === '') {
						$updatedImageRelativePath = $newImageRelativePath;
					}
				}
			}

			$updateVehicleStmt = $pdo->prepare(
				'UPDATE vehicles
				 SET category_id = :category_id,
					 vehicle_type = :vehicle_type,
					 short_name = :short_name,
					 full_name = :full_name,
					 price_per_day = :price_per_day,
					 driver_age_requirement = :driver_age_requirement,
					 image_path = :image_path,
					 number_of_seats = :number_of_seats,
					 transmission_type = :transmission_type,
					 fuel_type = :fuel_type,
					 mileage_km_per_l = :mileage_km_per_l,
					 license_plate = :license_plate,
					 status = :status,
					 gps_id = :gps_id,
					 last_service_date = :last_service_date,
					 description = :description,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE id = :id'
			);
			$updateVehicleStmt->execute([
				'category_id' => $categoryId,
				'vehicle_type' => $vehicleType,
				'short_name' => $shortName,
				'full_name' => $fullName,
				'price_per_day' => $pricePerDay,
				'driver_age_requirement' => $driverAgeRequirement,
				'image_path' => $updatedImageRelativePath,
				'number_of_seats' => $numberOfSeats,
				'transmission_type' => $transmissionType,
				'fuel_type' => $fuelType,
				'mileage_km_per_l' => $mileageValue,
				'license_plate' => $licensePlate,
				'status' => $status,
				'gps_id' => $gpsId,
				'last_service_date' => $lastServiceDate,
				'description' => $description,
				'id' => $vehicleId,
			]);

			$pdo->commit();
			$syncVehicleDataAfterUpdate($pdo);

			$successRedirectQuery = [
				'page' => 'admin-manage-fleet',
			];
			if ($status === 'available') {
				$successRedirectQuery['fleet_mode'] = 'type';
				$successRedirectQuery['fleet_type'] = $vehicleType;
			} else {
				$successRedirectQuery['fleet_mode'] = 'status';
				$successRedirectQuery['fleet_status'] = $status;
			}
			$successRedirectQuery['open_read_vehicle_id'] = $vehicleId;

			header('Location: index.php?' . http_build_query($successRedirectQuery), true, 303);
			exit;
		} catch (Throwable $exception) {
			if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			foreach ($newImageAbsolutePaths as $newImageAbsolutePath) {
				if ($newImageAbsolutePath !== '' && is_file($newImageAbsolutePath)) {
					@unlink($newImageAbsolutePath);
				}
			}

			error_log('Admin update vehicle failed: ' . $exception->getMessage());
		}

		header('Location: ' . $redirectUrl, true, 303);
		exit;
	}

// maintenance edit/fillup form: process start/update/complete lifecycle from fleet maintenance modals.
if ($isAdminStartMaintenancePost || $isAdminUpdateMaintenancePost || $isAdminCompleteMaintenancePost) {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$allowedFleetModes = ['type', 'status'];
	$allowedFleetTypes = ['cars', 'bikes', 'luxury'];
	$allowedFleetStatuses = ['reserved', 'on_trip', 'overdue', 'maintenance'];

	$fleetMode = strtolower(trim((string) ($_POST['fleet_mode'] ?? 'status')));
	if (!in_array($fleetMode, $allowedFleetModes, true)) {
		$fleetMode = 'status';
	}

	$fleetType = strtolower(trim((string) ($_POST['fleet_type'] ?? 'cars')));
	if (!in_array($fleetType, $allowedFleetTypes, true)) {
		$fleetType = 'cars';
	}

	$fleetStatus = strtolower(trim((string) ($_POST['fleet_status'] ?? 'maintenance')));
	if (!in_array($fleetStatus, $allowedFleetStatuses, true)) {
		$fleetStatus = 'maintenance';
	}

	$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
	$issueDescription = trim((string) ($_POST['issue_description'] ?? ''));
	$workshopName = trim((string) ($_POST['workshop_name'] ?? ''));
	$estimateCompletionDate = trim((string) ($_POST['estimate_completion_date'] ?? ''));
	$serviceCost = (float) ($_POST['service_cost'] ?? 0);

	$redirectQuery = [
		'page' => 'admin-manage-fleet',
		'fleet_mode' => $fleetMode,
	];
	if ($fleetMode === 'status') {
		$redirectQuery['fleet_status'] = $fleetStatus;
	} else {
		$redirectQuery['fleet_type'] = $fleetType;
	}

	$redirectWithVehicle = static function (array $baseQuery, int $id): string {
		if ($id > 0) {
			$baseQuery['open_read_vehicle_id'] = $id;
		}

		return 'index.php?' . http_build_query($baseQuery);
	};

	$syncVehicleDataAfterMaintenance = static function (PDO $pdo) use (
		$mirrorVehicleJsonFiles,
		$legacyVehicleJsonSyncDir,
		$vehicleJsonSyncDir
	): void {
		try {
			$mirrorVehicleJsonFiles($legacyVehicleJsonSyncDir, $vehicleJsonSyncDir);
			sync_vehicles_json_bidirectional($pdo, $vehicleJsonSyncDir, [
				'prefer_json' => false,
				'prefer_db_timestamps' => true,
			]);
			$mirrorVehicleJsonFiles($vehicleJsonSyncDir, $legacyVehicleJsonSyncDir);
		} catch (Throwable $syncException) {
			error_log('Admin maintenance sync failed: ' . $syncException->getMessage());
		}
	};

	if ($vehicleId <= 0) {
		header('Location: ' . $redirectWithVehicle($redirectQuery, 0), true, 303);
		exit;
	}

	try {
		$pdo = db();
		$ensureMaintenanceRecordsTable($pdo);
		$pdo->beginTransaction();

		$vehicleLookupStmt = $pdo->prepare(
			'SELECT id, vehicle_type, status
			 FROM vehicles
			 WHERE id = :id
			 LIMIT 1
			 FOR UPDATE'
		);
		$vehicleLookupStmt->execute(['id' => $vehicleId]);
		$vehicleRow = $vehicleLookupStmt->fetch();

		if (!is_array($vehicleRow)) {
			$pdo->rollBack();
			header('Location: ' . $redirectWithVehicle($redirectQuery, 0), true, 303);
			exit;
		}

		$vehicleTypeValue = strtolower(trim((string) ($vehicleRow['vehicle_type'] ?? 'cars')));
		if (!in_array($vehicleTypeValue, $allowedFleetTypes, true)) {
			$vehicleTypeValue = 'cars';
		}

		if ($isAdminStartMaintenancePost || $isAdminUpdateMaintenancePost) {
			if ($issueDescription === '' || $workshopName === '' || $estimateCompletionDate === '') {
				$pdo->rollBack();
				$redirectQuery['fleet_mode'] = 'status';
				$redirectQuery['fleet_status'] = 'maintenance';
				unset($redirectQuery['fleet_type']);
				header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
				exit;
			}

			$estimateDateTime = DateTimeImmutable::createFromFormat('Y-m-d', $estimateCompletionDate)
				?: DateTimeImmutable::createFromFormat('d M, Y', $estimateCompletionDate);
			if (!$estimateDateTime instanceof DateTimeImmutable) {
				$pdo->rollBack();
				$redirectQuery['fleet_mode'] = 'status';
				$redirectQuery['fleet_status'] = 'maintenance';
				unset($redirectQuery['fleet_type']);
				header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
				exit;
			}

			if ($estimateDateTime < new DateTimeImmutable('today')) {
				$pdo->rollBack();
				$redirectQuery['fleet_mode'] = 'status';
				$redirectQuery['fleet_status'] = 'maintenance';
				unset($redirectQuery['fleet_type']);
				header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
				exit;
			}

			$estimateCompletionDate = $estimateDateTime->format('Y-m-d');
			if (!is_finite($serviceCost) || $serviceCost < 0) {
				$serviceCost = 0;
			}
		}

		if ($isAdminStartMaintenancePost) {
			$closeOpenRecordsStmt = $pdo->prepare(
				'UPDATE vehicle_maintenance_records
				 SET status = "completed",
					 completed_at = CURRENT_TIMESTAMP,
					 updated_at = CURRENT_TIMESTAMP
				 WHERE vehicle_id = :vehicle_id AND status = "open"'
			);
			$closeOpenRecordsStmt->execute(['vehicle_id' => $vehicleId]);

			$insertMaintenanceStmt = $pdo->prepare(
				'INSERT INTO vehicle_maintenance_records
					(vehicle_id, issue_description, workshop_name, estimate_completion_date, service_cost, status)
				 VALUES
					(:vehicle_id, :issue_description, :workshop_name, :estimate_completion_date, :service_cost, "open")'
			);
			$insertMaintenanceStmt->execute([
				'vehicle_id' => $vehicleId,
				'issue_description' => $issueDescription,
				'workshop_name' => $workshopName,
				'estimate_completion_date' => $estimateCompletionDate,
				'service_cost' => number_format($serviceCost, 2, '.', ''),
			]);

			$markVehicleMaintenanceStmt = $pdo->prepare(
				'UPDATE vehicles
				 SET status = "maintenance"
				 WHERE id = :id'
			);
			$markVehicleMaintenanceStmt->execute(['id' => $vehicleId]);

			$pdo->commit();
			$syncVehicleDataAfterMaintenance($pdo);

			$redirectQuery = [
				'page' => 'admin-manage-fleet',
				'fleet_mode' => 'status',
				'fleet_status' => 'maintenance',
			];
			header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
			exit;
		}

		if ($isAdminUpdateMaintenancePost) {
			$openRecordLookupStmt = $pdo->prepare(
				'SELECT id
				 FROM vehicle_maintenance_records
				 WHERE vehicle_id = :vehicle_id AND status = "open"
				 ORDER BY id DESC
				 LIMIT 1
				 FOR UPDATE'
			);
			$openRecordLookupStmt->execute(['vehicle_id' => $vehicleId]);
			$openRecordId = (int) $openRecordLookupStmt->fetchColumn();

			if ($openRecordId > 0) {
				$updateRecordStmt = $pdo->prepare(
					'UPDATE vehicle_maintenance_records
					 SET issue_description = :issue_description,
						 workshop_name = :workshop_name,
						 estimate_completion_date = :estimate_completion_date,
						 service_cost = :service_cost,
						 updated_at = CURRENT_TIMESTAMP
					 WHERE id = :id'
				);
				$updateRecordStmt->execute([
					'issue_description' => $issueDescription,
					'workshop_name' => $workshopName,
					'estimate_completion_date' => $estimateCompletionDate,
					'service_cost' => number_format($serviceCost, 2, '.', ''),
					'id' => $openRecordId,
				]);
			} else {
				$insertRecordStmt = $pdo->prepare(
					'INSERT INTO vehicle_maintenance_records
						(vehicle_id, issue_description, workshop_name, estimate_completion_date, service_cost, status)
					 VALUES
						(:vehicle_id, :issue_description, :workshop_name, :estimate_completion_date, :service_cost, "open")'
				);
				$insertRecordStmt->execute([
					'vehicle_id' => $vehicleId,
					'issue_description' => $issueDescription,
					'workshop_name' => $workshopName,
					'estimate_completion_date' => $estimateCompletionDate,
					'service_cost' => number_format($serviceCost, 2, '.', ''),
				]);
			}

			$ensureVehicleMaintenanceStmt = $pdo->prepare(
				'UPDATE vehicles
				 SET status = "maintenance"
				 WHERE id = :id'
			);
			$ensureVehicleMaintenanceStmt->execute(['id' => $vehicleId]);

			$pdo->commit();
			$syncVehicleDataAfterMaintenance($pdo);

			$redirectQuery = [
				'page' => 'admin-manage-fleet',
				'fleet_mode' => 'status',
				'fleet_status' => 'maintenance',
			];
			header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
			exit;
		}

		$completeOpenRecordsStmt = $pdo->prepare(
			'UPDATE vehicle_maintenance_records
			 SET status = "completed",
				 completed_at = CURRENT_TIMESTAMP,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE vehicle_id = :vehicle_id AND status = "open"'
		);
		$completeOpenRecordsStmt->execute([
			'vehicle_id' => $vehicleId,
		]);

		$markVehicleAvailableStmt = $pdo->prepare(
			'UPDATE vehicles
			 SET status = "available",
				 last_service_date = CURRENT_DATE
			 WHERE id = :id'
		);
		$markVehicleAvailableStmt->execute([
			'id' => $vehicleId,
		]);

		$pdo->commit();
		$syncVehicleDataAfterMaintenance($pdo);

		$redirectQuery = [
			'page' => 'admin-manage-fleet',
			'fleet_mode' => 'type',
			'fleet_type' => $vehicleTypeValue,
		];
		header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
		exit;
	} catch (Throwable $exception) {
		if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
			$pdo->rollBack();
		}
		error_log('Admin maintenance action failed: ' . $exception->getMessage());
	}

	header('Location: ' . $redirectWithVehicle($redirectQuery, $vehicleId), true, 303);
	exit;
}

// admin login: keep validation state for one request only so refresh/close does not remember old errors.
$redirectWithAdminLoginFlash = static function (
	string $error,
	string $email,
	bool $emailInvalid,
	bool $passwordInvalid
): void {
	ridex_session_set_admin_login_flash(
		$error,
		$email,
		$emailInvalid,
		$passwordInvalid
	);
	ridex_redirect('index.php', 303);
};

// user login: keep validation state for one request only so modal errors survive redirect.
$redirectWithUserLoginFlash = static function (
	string $error,
	string $identifier,
	bool $identifierInvalid,
	bool $passwordInvalid,
	string $postAuthRedirect = 'index.php'
) use ($normalizePostAuthRedirect): void {
	ridex_session_set_user_login_flash(
		$error,
		$identifier,
		$identifierInvalid,
		$passwordInvalid,
		$normalizePostAuthRedirect($postAuthRedirect)
	);
	ridex_redirect('index.php', 303);
};

$minimumBookingAge = 18;
$bookingAgeRestrictionMessage = 'You must be at least 18 years old to book a vehicle.';
$bookingActiveOverlapMessage = 'booking conflict. you already have an active booking during this time.';
$bookingVehicleOverdueMessage = 'vehicle overdue. return current vehicle before booking again.';

$calculateAgeFromDateOfBirth = static function ($rawDateOfBirth): ?int {
	$normalizedDateOfBirth = trim((string) $rawDateOfBirth);
	if ($normalizedDateOfBirth === '') {
		return null;
	}

	$dateOfBirthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $normalizedDateOfBirth);
	if (!($dateOfBirthDate instanceof DateTimeImmutable)) {
		try {
			$dateOfBirthDate = new DateTimeImmutable($normalizedDateOfBirth);
		} catch (Throwable $exception) {
			return null;
		}
	}

	$todayDate = new DateTimeImmutable('today');
	if ($dateOfBirthDate > $todayDate) {
		return null;
	}

	return (int) $dateOfBirthDate->diff($todayDate)->y;
};

$isBookingPageRedirect = static function (string $redirectUrl): bool {
	$normalizedRedirectUrl = trim($redirectUrl);
	if ($normalizedRedirectUrl === '') {
		return false;
	}

	$parsedRedirectUrl = parse_url($normalizedRedirectUrl);
	if (!is_array($parsedRedirectUrl)) {
		return false;
	}

	$redirectQueryValues = [];
	if (isset($parsedRedirectUrl['query'])) {
		parse_str((string) $parsedRedirectUrl['query'], $redirectQueryValues);
	}
	if (!is_array($redirectQueryValues)) {
		return false;
	}

	$redirectPage = strtolower(trim((string) ($redirectQueryValues['page'] ?? '')));
	return in_array($redirectPage, ['booking-select', 'booking-engine', 'booking-checkout'], true);
};

$isUserEligibleForBooking = static function (array $userRow) use ($calculateAgeFromDateOfBirth, $minimumBookingAge): bool {
	$userAge = $calculateAgeFromDateOfBirth($userRow['date_of_birth'] ?? '');
	return $userAge !== null && $userAge >= $minimumBookingAge;
};

if (!$isAdminLoginPost) {
	$adminFlash = ridex_session_pull_flash('admin_login_flash');
	if (!empty($adminFlash)) {
		$adminLoginError = trim((string) ($adminFlash['error'] ?? ''));
		$adminLoginEmail = trim((string) ($adminFlash['email'] ?? ''));
		$adminLoginEmailInvalid = (bool) ($adminFlash['email_invalid'] ?? false);
		$adminLoginPasswordInvalid = (bool) ($adminFlash['password_invalid'] ?? false);
	}
}

if (!$isUserLoginPost) {
	$userLoginFlash = ridex_session_pull_flash('user_login_flash');
	if (!empty($userLoginFlash)) {
		$userLoginError = trim((string) ($userLoginFlash['error'] ?? ''));
		$userLoginIdentifier = trim((string) ($userLoginFlash['identifier'] ?? ''));
		$userLoginIdentifierInvalid = (bool) ($userLoginFlash['identifier_invalid'] ?? false);
		$userLoginPasswordInvalid = (bool) ($userLoginFlash['password_invalid'] ?? false);
		$userPostAuthRedirect = $normalizePostAuthRedirect($userLoginFlash['post_auth_redirect'] ?? $userPostAuthRedirect);
	}
}

// admin login: ensure the default admin credential exists with hashed password
$ensureDefaultAdminAccount = static function (PDO $pdo): void {
	ridex_user_ensure_default_admin_account(
		$pdo,
		'rupikadangol@gmail.com',
		'12345678'
	);
};

// admin login: always ensure the default admin account exists in users(role=admin).
try {
	$ensureDefaultAdminAccount(db());
} catch (Throwable $exception) {
	ridex_log_exception('Default admin setup failed', $exception);
}

// admin login: process modal login post and redirect to dashboard placeholder on success
if ($isAdminLoginPost) {
	$adminLoginEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
	$adminLoginPassword = (string) ($_POST['admin_password'] ?? '');

	if ($adminLoginEmail === '' && $adminLoginPassword === '') {
		$redirectWithAdminLoginFlash(
			'Email and password are required.',
			'',
			true,
			true
		);
	} elseif ($adminLoginEmail === '') {
		$redirectWithAdminLoginFlash(
			'Email is required.',
			'',
			true,
			false
		);
	} elseif ($adminLoginPassword === '') {
		$redirectWithAdminLoginFlash(
			'Password is required.',
			$adminLoginEmail,
			false,
			true
		);
	} elseif (!filter_var($adminLoginEmail, FILTER_VALIDATE_EMAIL)) {
		$redirectWithAdminLoginFlash(
			'Please enter a valid email address.',
			$adminLoginEmail,
			true,
			false
		);
	} else {
		try {
			$pdo = db();

			$adminLookupStmt = $pdo->prepare(
				'SELECT id, name, email, phone, password_hash, role
				 FROM users
				 WHERE LOWER(email) = LOWER(:email) AND role = :role
				 LIMIT 1'
			);
			$adminLookupStmt->execute([
				'email' => $adminLoginEmail,
				'role' => 'admin',
			]);
			$adminUser = $adminLookupStmt->fetch();

			if (!is_array($adminUser)) {
				$redirectWithAdminLoginFlash(
					'Email is invalid. Please double check or try again.',
					$adminLoginEmail,
					true,
					false
				);
			} else {
				$passwordMatches = password_verify($adminLoginPassword, (string) ($adminUser['password_hash'] ?? ''));

				if (!$passwordMatches) {
					$redirectWithAdminLoginFlash(
						'Password is invalid. Please double check or try again.',
						$adminLoginEmail,
						false,
						true
					);
				} else {
					session_regenerate_id(true);
					$_SESSION['auth_user'] = [
						'id' => (int) ($adminUser['id'] ?? 0),
						'name' => (string) ($adminUser['name'] ?? 'Admin'),
						'email' => (string) ($adminUser['email'] ?? $adminLoginEmail),
						'phone' => trim((string) ($adminUser['phone'] ?? '')),
						'role' => 'admin',
					];
					ridex_redirect('index.php?page=admin-dashboard', 302);
				}
			}
		} catch (Throwable $exception) {
			ridex_log_exception('Admin login failed', $exception);
			$redirectWithAdminLoginFlash(
				'Unable to complete admin login right now. Please try again.',
				$adminLoginEmail,
				true,
				true
			);
		}
	}
}

if ($isUserLoginPost) {
	$userLoginIdentifier = trim((string) ($_POST['user_identifier'] ?? ''));
	$userLoginPassword = (string) ($_POST['user_password'] ?? '');
	$userPostAuthRedirectInput = $normalizePostAuthRedirect($_POST['post_auth_redirect'] ?? 'index.php');
	$userPostAuthRedirect = $userPostAuthRedirectInput;

	if ($userLoginIdentifier === '' && $userLoginPassword === '') {
		$redirectWithUserLoginFlash(
			'Email/Driver ID and password are required.',
			'',
			true,
			true,
			$userPostAuthRedirectInput
		);
	} elseif ($userLoginIdentifier === '') {
		$redirectWithUserLoginFlash(
			'Email or Driver ID is required.',
			'',
			true,
			false,
			$userPostAuthRedirectInput
		);
	} elseif ($userLoginPassword === '') {
		$redirectWithUserLoginFlash(
			'Password is required.',
			$userLoginIdentifier,
			false,
			true,
			$userPostAuthRedirectInput
		);
	} else {
		try {
			$pdo = db();

			$userAccount = ridex_user_find_for_login($pdo, $userLoginIdentifier);

			$invalidCredentialsMessage = 'Invalid email/driver ID or password. Please try again.';

			if (!is_array($userAccount)) {
				$redirectWithUserLoginFlash(
					$invalidCredentialsMessage,
					$userLoginIdentifier,
					true,
					true,
					$userPostAuthRedirectInput
				);
			}

			$passwordMatches = password_verify(
				$userLoginPassword,
				(string) ($userAccount['password_hash'] ?? '')
			);

			if (!$passwordMatches) {
				$redirectWithUserLoginFlash(
					$invalidCredentialsMessage,
					$userLoginIdentifier,
					true,
					true,
					$userPostAuthRedirectInput
				);
			}

			$requiresBookingAgeCheck = $isBookingPageRedirect($userPostAuthRedirectInput);
			if ($requiresBookingAgeCheck && !$isUserEligibleForBooking($userAccount)) {
				$redirectWithUserLoginFlash(
					$bookingAgeRestrictionMessage,
					$userLoginIdentifier,
					false,
					false,
					$userPostAuthRedirectInput
				);
			}

			session_regenerate_id(true);
			$_SESSION['auth_user'] = ridex_build_user_session_payload($userAccount, 'user');

			ridex_session_pull_flash('user_login_flash');
			ridex_redirect($userPostAuthRedirectInput, 302);
		} catch (Throwable $exception) {
			ridex_log_exception('User login failed', $exception);
			$redirectWithUserLoginFlash(
				'Unable to complete user login right now. Please try again.',
				$userLoginIdentifier,
				true,
				true,
				$userPostAuthRedirectInput
			);
		}
	}
}

$runVehicleSync = static function (bool $preferDbTimestamps = false) use (
	$vehicleJsonSyncDir,
	$vehicleSyncStrategy,
	$legacyVehicleJsonSyncDir,
	$mirrorVehicleJsonFiles
): void {
	try {
		$mirrorVehicleJsonFiles($legacyVehicleJsonSyncDir, $vehicleJsonSyncDir);
		$useDbPriority = $preferDbTimestamps || $vehicleSyncStrategy === 'db-first';
		$syncOptions = $useDbPriority
			? [
				'prefer_json' => false,
				'prefer_db_timestamps' => true,
			]
			: [
				'prefer_json' => true,
			];

		sync_vehicles_json_bidirectional(db(), $vehicleJsonSyncDir, $syncOptions);
		$mirrorVehicleJsonFiles($vehicleJsonSyncDir, $legacyVehicleJsonSyncDir);
	} catch (Throwable $exception) {
		// Keep page rendering available even if JSON sync fails.
		ridex_log_exception('Vehicle JSON sync failed', $exception);
	}
};

$syncBookingLifecycleStatuses = static function (): void {
	ridex_sync_booking_lifecycle_statuses(db());
};

$syncBookingUsersAndPayments = static function () use ($ensurePaymentsTable): void {
	ridex_sync_booking_users_and_payments(db(), $ensurePaymentsTable);
};

$syncSequentialAutoIncrement = static function (): void {
	ridex_sync_sequential_auto_increment(db());
};

// Pull latest changes before read queries.
$runVehicleSync(false);
// Ensure soft-delete metadata exists for vehicle visibility and booking history preservation.
$ensureVehicleSoftDeleteColumn(db());
// Ensure mileage metadata exists for vehicle specs.
$ensureVehicleMileageColumn(db());
// Ensure multi-image gallery table exists for vehicle galleries.
$ensureVehicleImagesTable(db());
// Optional GPS normalization is disabled by default to keep non-GPS deployments lightweight.
if ($enableGpsRuntimeCoverage) {
	$ensureVehicleGpsCoverage();
}
// Keep DB booking statuses aligned with time-based lifecycle transitions.
$syncBookingLifecycleStatuses();
// Keep users/payments connected to current booking records and fill required fields.
$syncBookingUsersAndPayments();
// Keep next generated IDs aligned with current max IDs for booking/user records.
$syncSequentialAutoIncrement();
// Push website-originated DB changes (create/update/delete) after request handling.
register_shutdown_function(static function () use ($runVehicleSync): void {
	$runVehicleSync(true);
});

$sanitizeVehicleType = static function ($rawType): string {
	return ridex_category_sanitize_vehicle_type($rawType, 'cars');
};

$getAdminDashboardPayload = static function (): array {
	$calculatePercentChange = static function (?float $current, ?float $previous): ?float {
		if ($current === null || $previous === null) {
			return null;
		}

		$current = (float) $current;
		$previous = (float) $previous;

		if (abs($previous) < 0.00001) {
			if (abs($current) < 0.00001) {
				return 0.0;
			}

			// No stable baseline: avoid forcing ±100% when the previous period is zero.
			return null;
		}

		return (($current - $previous) / abs($previous)) * 100;
	};

	$dashboardKpis = [
		'totalRevenue' => [
			'value' => null,
			'trend' => null,
		],
		'activeRentals' => [
			'value' => null,
			'trend' => null,
		],
		'totalFleet' => [
			'value' => null,
			'trend' => null,
		],
		'fleetAvailability' => [
			'value' => null,
			'trend' => null,
		],
	];

	$dashboardCharts = [
		'salesVehicleCategory' => [
			'labels' => [],
			'datasets' => [
				[
					'label' => 'Cars',
					'data' => [],
					'borderColor' => '#f75b7a',
				],
				[
					'label' => 'Bikes',
					'data' => [],
					'borderColor' => '#45aaf2',
				],
				[
					'label' => 'Luxury',
					'data' => [],
					'borderColor' => '#2db9b0',
				],
			],
		],
		'mostRentedVehicleCategory' => [
			'labels' => ['Cars', 'Bikes', 'Luxury'],
			'datasets' => [
				[
					'label' => 'Most Rented',
					'data' => [0, 0, 0],
					'backgroundColor' => ['#f75b7a', '#f6a340', '#f4ca55'],
				],
			],
		],
	];

	try {
		$pdo = db();

		$fleetStatsStmt = $pdo->query(
			'SELECT
				COUNT(*) AS total_fleet,
				SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) AS available_fleet
			 FROM vehicles
			 WHERE deleted_at IS NULL'
		);
		$fleetStats = $fleetStatsStmt->fetch() ?: [];
		$totalFleet = (int) ($fleetStats['total_fleet'] ?? 0);
		$availableFleet = (int) ($fleetStats['available_fleet'] ?? 0);

		if ($totalFleet > 0) {
			$dashboardKpis['totalFleet']['value'] = $totalFleet;
			$dashboardKpis['fleetAvailability']['value'] = round(($availableFleet / $totalFleet) * 100, 1);
		}

		$bookingStatsStmt = $pdo->query(
			'SELECT
				COUNT(*) AS total_bookings,
				SUM(CASE WHEN status IN ("reserved", "on_trip", "overdue") THEN 1 ELSE 0 END) AS active_rentals,
				SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) AS paid_revenue
			 FROM bookings'
		);
		$bookingStats = $bookingStatsStmt->fetch() ?: [];
		$totalBookings = (int) ($bookingStats['total_bookings'] ?? 0);

		if ($totalBookings > 0) {
			$dashboardKpis['activeRentals']['value'] = (int) ($bookingStats['active_rentals'] ?? 0);
			$dashboardKpis['totalRevenue']['value'] = (float) ($bookingStats['paid_revenue'] ?? 0);
		}

		$trendEnd = new DateTimeImmutable('now');
		$trendWindow = new DateInterval('P2D');
		$currentStart = $trendEnd->sub($trendWindow);
		$previousStart = $currentStart->sub($trendWindow);

		$periodParams = [
			'current_start' => $currentStart->format('Y-m-d H:i:s'),
			'current_end' => $trendEnd->format('Y-m-d H:i:s'),
			'previous_start' => $previousStart->format('Y-m-d H:i:s'),
			'previous_end' => $currentStart->format('Y-m-d H:i:s'),
		];

		$revenueTrendStmt = $pdo->prepare(
			'SELECT
				SUM(CASE WHEN pickup_datetime >= :current_start AND pickup_datetime < :current_end AND payment_status = "paid" THEN paid_amount ELSE 0 END) AS current_revenue,
				SUM(CASE WHEN pickup_datetime >= :previous_start AND pickup_datetime < :previous_end AND payment_status = "paid" THEN paid_amount ELSE 0 END) AS previous_revenue
			 FROM bookings'
		);
		$revenueTrendStmt->execute($periodParams);
		$revenueTrend = $revenueTrendStmt->fetch() ?: [];
		$dashboardKpis['totalRevenue']['trend'] = $calculatePercentChange(
			(float) ($revenueTrend['current_revenue'] ?? 0),
			(float) ($revenueTrend['previous_revenue'] ?? 0)
		);

		$activeTrendStmt = $pdo->prepare(
			'SELECT
				SUM(CASE WHEN pickup_datetime <= :current_point_start AND COALESCE(return_time, return_datetime) > :current_point_end AND status <> "cancelled" THEN 1 ELSE 0 END) AS current_active,
				SUM(CASE WHEN pickup_datetime <= :previous_point_start AND COALESCE(return_time, return_datetime) > :previous_point_end AND status <> "cancelled" THEN 1 ELSE 0 END) AS previous_active
			 FROM bookings'
		);
		$activeTrendStmt->execute([
			'current_point_start' => $periodParams['current_end'],
			'current_point_end' => $periodParams['current_end'],
			'previous_point_start' => $periodParams['previous_end'],
			'previous_point_end' => $periodParams['previous_end'],
		]);
		$activeTrend = $activeTrendStmt->fetch() ?: [];
		$dashboardKpis['activeRentals']['value'] = (int) ($activeTrend['current_active'] ?? 0);
		$dashboardKpis['activeRentals']['trend'] = $calculatePercentChange(
			(float) ($activeTrend['current_active'] ?? 0),
			(float) ($activeTrend['previous_active'] ?? 0)
		);

		$fleetTrendStmt = $pdo->prepare(
			'SELECT
				SUM(CASE WHEN created_at < :fleet_previous_total_cutoff THEN 1 ELSE 0 END) AS previous_fleet,
				SUM(CASE WHEN created_at < :fleet_previous_available_cutoff AND status = "available" THEN 1 ELSE 0 END) AS previous_available
			 FROM vehicles
			 WHERE deleted_at IS NULL'
		);
		$fleetTrendStmt->execute([
			'fleet_previous_total_cutoff' => $periodParams['previous_end'],
			'fleet_previous_available_cutoff' => $periodParams['previous_end'],
		]);
		$fleetTrend = $fleetTrendStmt->fetch() ?: [];

		$currentFleet = (float) $totalFleet;
		$currentAvailable = (float) $availableFleet;
		$previousFleet = (float) ($fleetTrend['previous_fleet'] ?? 0);
		$previousAvailable = (float) ($fleetTrend['previous_available'] ?? 0);

		$dashboardKpis['totalFleet']['trend'] = $calculatePercentChange(
			$currentFleet,
			$previousFleet
		);

		$currentAvailabilityRatio = $currentFleet > 0
			? ($currentAvailable / $currentFleet) * 100
			: 0.0;
		$previousAvailabilityRatio = $previousFleet > 0
			? ($previousAvailable / $previousFleet) * 100
			: 0.0;
		$dashboardKpis['fleetAvailability']['trend'] = $calculatePercentChange($currentAvailabilityRatio, $previousAvailabilityRatio);

		$lineDays = 15;
		$lineDateKeys = [];
		$lineLabels = [];

		for ($index = $lineDays - 1; $index >= 0; $index--) {
			$date = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $index . 'D'));
			$dateKey = $date->format('Y-m-d');
			$lineDateKeys[] = $dateKey;
			$lineLabels[] = $date->format('M j');
		}

		$lineIndexByDate = array_flip($lineDateKeys);
		$lineCars = array_fill(0, $lineDays, 0);
		$lineBikes = array_fill(0, $lineDays, 0);
		$lineLuxury = array_fill(0, $lineDays, 0);

		$lineChartStmt = $pdo->prepare(
			'SELECT
				DATE(b.pickup_datetime) AS booking_date,
				v.vehicle_type,
				COALESCE(SUM(CASE WHEN b.payment_status = "paid" THEN b.paid_amount ELSE b.total_amount END), 0) AS total
			 FROM bookings b
			 INNER JOIN vehicles v ON v.id = b.vehicle_id
			 WHERE b.pickup_datetime >= :line_start
			 GROUP BY DATE(b.pickup_datetime), v.vehicle_type
			 ORDER BY booking_date ASC'
		);
		$lineChartStmt->execute([
			'line_start' => $lineDateKeys[0] . ' 00:00:00',
		]);

		while ($lineRow = $lineChartStmt->fetch()) {
			$dateKey = (string) ($lineRow['booking_date'] ?? '');
			$vehicleType = strtolower(trim((string) ($lineRow['vehicle_type'] ?? '')));
			$total = (float) ($lineRow['total'] ?? 0);

			if (!array_key_exists($dateKey, $lineIndexByDate)) {
				continue;
			}

			$targetIndex = (int) $lineIndexByDate[$dateKey];
			if ($vehicleType === 'cars') {
				$lineCars[$targetIndex] = $total;
			} elseif ($vehicleType === 'bikes') {
				$lineBikes[$targetIndex] = $total;
			} elseif ($vehicleType === 'luxury') {
				$lineLuxury[$targetIndex] = $total;
			}
		}

		$lineTotal = array_sum($lineCars) + array_sum($lineBikes) + array_sum($lineLuxury);
		if ($lineTotal <= 0) {
			$fleetLineStmt = $pdo->prepare(
				'SELECT
					DATE(created_at) AS created_date,
					vehicle_type,
					COUNT(*) AS total
				 FROM vehicles
				 WHERE created_at >= :line_start AND deleted_at IS NULL
				 GROUP BY DATE(created_at), vehicle_type
				 ORDER BY created_date ASC'
			);
			$fleetLineStmt->execute([
				'line_start' => $lineDateKeys[0] . ' 00:00:00',
			]);

			while ($fleetRow = $fleetLineStmt->fetch()) {
				$dateKey = (string) ($fleetRow['created_date'] ?? '');
				$vehicleType = strtolower(trim((string) ($fleetRow['vehicle_type'] ?? '')));
				$total = (int) ($fleetRow['total'] ?? 0);

				if (!array_key_exists($dateKey, $lineIndexByDate)) {
					continue;
				}

				$targetIndex = (int) $lineIndexByDate[$dateKey];
				if ($vehicleType === 'cars') {
					$lineCars[$targetIndex] = (float) $total;
				} elseif ($vehicleType === 'bikes') {
					$lineBikes[$targetIndex] = (float) $total;
				} elseif ($vehicleType === 'luxury') {
					$lineLuxury[$targetIndex] = (float) $total;
				}
			}
		}

		$dashboardCharts['salesVehicleCategory']['labels'] = $lineLabels;
		$dashboardCharts['salesVehicleCategory']['datasets'][0]['data'] = $lineCars;
		$dashboardCharts['salesVehicleCategory']['datasets'][1]['data'] = $lineBikes;
		$dashboardCharts['salesVehicleCategory']['datasets'][2]['data'] = $lineLuxury;

		$pieChartStmt = $pdo->query(
			'SELECT
				v.vehicle_type,
				COUNT(*) AS total
			 FROM bookings b
			 INNER JOIN vehicles v ON v.id = b.vehicle_id
			 GROUP BY v.vehicle_type'
		);

		$pieCounts = [
			'cars' => 0,
			'bikes' => 0,
			'luxury' => 0,
		];

		while ($pieRow = $pieChartStmt->fetch()) {
			$type = strtolower(trim((string) ($pieRow['vehicle_type'] ?? '')));
			if (array_key_exists($type, $pieCounts)) {
				$pieCounts[$type] = (int) ($pieRow['total'] ?? 0);
			}
		}

		$pieTotal = $pieCounts['cars'] + $pieCounts['bikes'] + $pieCounts['luxury'];
		if ($pieTotal <= 0) {
			$fleetPieStmt = $pdo->query(
				'SELECT
					vehicle_type,
					COUNT(*) AS total
				 FROM vehicles
				 WHERE deleted_at IS NULL
				 GROUP BY vehicle_type'
			);

			while ($fleetPieRow = $fleetPieStmt->fetch()) {
				$type = strtolower(trim((string) ($fleetPieRow['vehicle_type'] ?? '')));
				if (array_key_exists($type, $pieCounts)) {
					$pieCounts[$type] = (int) ($fleetPieRow['total'] ?? 0);
				}
			}
		}

		$dashboardCharts['mostRentedVehicleCategory']['datasets'][0]['data'] = [
			$pieCounts['cars'],
			$pieCounts['bikes'],
			$pieCounts['luxury'],
		];
	} catch (Throwable $exception) {
		error_log('Admin dashboard data failed: ' . $exception->getMessage());
	}

	return [
		'kpis' => $dashboardKpis,
		'charts' => $dashboardCharts,
		'generatedAt' => gmdate(DATE_ATOM),
	];
};

$getAdminLiveTrackingPayload = static function (): array {
	$payload = [
		'kpis' => [
			'totalAvailable' => 0,
			'totalUnavailable' => 0,
				'totalActive' => 0,
				'activeOverdue' => 0,
			],
		'markers' => [],
		'generatedAt' => gmdate(DATE_ATOM),
	];

	$clampPercent = static function (float $value, float $min, float $max): float {
		if ($value < $min) {
			return $min;
		}

		if ($value > $max) {
			return $max;
		}

		return $value;
	};

	try {
		$fleetCounts = db()->query(
			'SELECT
				SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) AS total_available,
				SUM(CASE WHEN status IN ("maintenance", "overdue", "on_trip", "reserved") THEN 1 ELSE 0 END) AS total_unavailable
			 FROM vehicles
			 WHERE deleted_at IS NULL'
		)->fetch() ?: [];

		$payload['kpis']['totalAvailable'] = (int) ($fleetCounts['total_available'] ?? 0);
		$payload['kpis']['totalUnavailable'] = (int) ($fleetCounts['total_unavailable'] ?? 0);

		$trackingRows = db()->query(
			'    SELECT
				b.id,
				b.booking_number,
				b.status,
				b.pickup_location,
				b.return_location,
				b.return_datetime,
				u.name AS customer_name,
				u.phone AS customer_phone,
				v.id AS vehicle_id,
				v.gps_id,
				latest_gps.latitude AS gps_latitude,
				latest_gps.longitude AS gps_longitude
			 FROM bookings b
			 LEFT JOIN users u ON u.id = b.user_id
			 LEFT JOIN vehicles v ON v.id = b.vehicle_id
			 LEFT JOIN gps_logs latest_gps ON latest_gps.id = (
				SELECT g1.id
				FROM gps_logs g1
				WHERE g1.vehicle_id = b.vehicle_id
				ORDER BY COALESCE(g1.timestamp, g1.created_at) DESC, g1.id DESC
				LIMIT 1
			 )
			 WHERE b.status IN ("reserved", "on_trip", "overdue")
			 ORDER BY
				CASE b.status
					WHEN "overdue" THEN 0
					WHEN "on_trip" THEN 1
					ELSE 2
				END ASC,
				b.return_datetime ASC,
				b.id DESC'
		)->fetchAll() ?: [];

		$totalActive = count($trackingRows);
		$payload['kpis']['totalActive'] = $totalActive;

		$overdueCount = 0;

		foreach ($trackingRows as $trackingRow) {
			$bookingId = (int) ($trackingRow['id'] ?? 0);
			$vehicleId = (int) ($trackingRow['vehicle_id'] ?? 0);
			$bookingStatus = strtolower(trim((string) ($trackingRow['status'] ?? 'reserved')));
			if ($bookingStatus === 'overdue') {
				$overdueCount += 1;
			}

			$bookingNumber = trim((string) ($trackingRow['booking_number'] ?? ''));
			if ($bookingNumber === '') {
				$bookingNumber = '#BK-' . str_pad((string) max(0, $bookingId), 4, '0', STR_PAD_LEFT);
			}

			$customerName = trim((string) ($trackingRow['customer_name'] ?? ''));
			if ($customerName === '') {
				$customerName = 'Unknown';
			}

			$customerPhone = trim((string) ($trackingRow['customer_phone'] ?? ''));
			if ($customerPhone === '') {
				$customerPhone = 'Unavailable';
			}

			$gpsLatitude = is_numeric($trackingRow['gps_latitude'] ?? null) ? (float) $trackingRow['gps_latitude'] : null;
			$gpsLongitude = is_numeric($trackingRow['gps_longitude'] ?? null) ? (float) $trackingRow['gps_longitude'] : null;
			$hasGpsSignal = $gpsLatitude !== null
				&& $gpsLongitude !== null
				&& (abs($gpsLatitude) > 0.00001 || abs($gpsLongitude) > 0.00001);

			// safety_score removed from payload

			$markerLocation = trim((string) ($trackingRow['return_location'] ?? ''));
			if ($markerLocation === '') {
				$markerLocation = trim((string) ($trackingRow['pickup_location'] ?? ''));
			}
			if ($markerLocation === '' && $hasGpsSignal) {
				$markerLocation = number_format((float) $gpsLatitude, 5, '.', '') . ', ' . number_format((float) $gpsLongitude, 5, '.', '');
			}
			if ($markerLocation === '') {
				$markerLocation = 'Unavailable';
			}

			$seedSource = $bookingNumber !== '' ? $bookingNumber : ('marker-' . $bookingId . '-' . $vehicleId);
			$seed = (int) sprintf('%u', crc32($seedSource));

			if ($hasGpsSignal) {
				$leftPercent = 10 + fmod((($gpsLongitude + 180) * 0.29), 78);
				$topPercent = 8 + fmod(((90 - $gpsLatitude) * 0.33), 76);
			} else {
				$leftPercent = 10 + ($seed % 78);
				$topPercent = 8 + (($seed >> 8) % 76);
			}

			$leftPercent = $clampPercent((float) $leftPercent, 8, 92);
			$topPercent = $clampPercent((float) $topPercent, 8, 86);

			$payload['markers'][] = [
				'bookingNumber' => $bookingNumber,
				'customerName' => $customerName,
				'customerPhone' => $customerPhone,
				'location' => $markerLocation,
				'status' => $bookingStatus,
				'hasGpsSignal' => $hasGpsSignal,
				'leftPercent' => round($leftPercent, 2),
				'topPercent' => round($topPercent, 2),
			];
		}

		$payload['kpis']['activeOverdue'] = $overdueCount;
	} catch (Throwable $exception) {
		error_log('Admin live tracking data failed: ' . $exception->getMessage());
	}

	return $payload;
};

$parseDateTimeSafe = static function ($rawDate): ?DateTimeImmutable {
	return ridex_vehicle_parse_datetime_safe($rawDate);
};

$resolveEffectiveVehicleStatus = static function (array $vehicleRow, ?DateTimeImmutable $referenceDateTime = null): string {
	return ridex_vehicle_resolve_effective_status($vehicleRow, $referenceDateTime);
};

$parseBookingDateTimeFromParts = static function (string $datePart, string $timePart): ?DateTimeImmutable {
	return ridex_parse_booking_datetime_from_parts($datePart, $timePart);
};

$sanitizeBookingSearchInput = static function (array $source): array {
	return ridex_sanitize_booking_search_input($source);
};

$buildBookingSearchQuery = static function (array $bookingSearch): array {
	return ridex_build_booking_search_query($bookingSearch);
};

$calculateBookingPriceBreakdown = static function (
	int $pricePerDay,
	DateTimeImmutable $pickupDateTime,
	DateTimeImmutable $returnDateTime
): array {
	return ridex_calculate_booking_price_breakdown($pricePerDay, $pickupDateTime, $returnDateTime);
};

$isVehicleAvailableForBookingWindow = static function (
	PDO $pdo,
	int $vehicleId,
	DateTimeImmutable $pickupDateTime,
	DateTimeImmutable $returnDateTime
): bool {
	return ridex_is_vehicle_available_for_booking_window($pdo, $vehicleId, $pickupDateTime, $returnDateTime);
};

$doesUserHaveOverlappingBookingWindow = static function (
	PDO $pdo,
	int $userId,
	DateTimeImmutable $pickupDateTime,
	DateTimeImmutable $returnDateTime
): bool {
	return ridex_does_user_have_overlapping_booking_window($pdo, $userId, $pickupDateTime, $returnDateTime);
};

$doesUserHaveOverdueBooking = static function (PDO $pdo, int $userId): bool {
	return ridex_user_has_overdue_booking($pdo, $userId);
};

$isVehicleBlockedByOverdueStatus = static function (PDO $pdo, int $vehicleId): bool {
	return ridex_is_vehicle_blocked_by_overdue_status($pdo, $vehicleId);
};

$fetchBookableVehicleById = static function (PDO $pdo, int $vehicleId): ?array {
	return ridex_fetch_bookable_vehicle_by_id($pdo, $vehicleId);
};

$fetchBookingReceiptData = static function (PDO $pdo, int $bookingId): ?array {
	return ridex_fetch_booking_receipt_data($pdo, $bookingId);
};

$formatBookingTimeline = static function (?DateTimeImmutable $dateTime): string {
	return ridex_format_booking_timeline($dateTime);
};

if ($isUserBookingCreatePost) {
	$bookingSearch = $sanitizeBookingSearchInput($_POST);
	$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
	$vehicleType = $sanitizeVehicleType($_POST['vehicle_type'] ?? 'cars');
	$paymentMethod = 'pay_on_arrival';

	$checkoutRedirectQuery = array_merge(
		[
			'page' => 'booking-checkout',
			'vehicle_id' => $vehicleId,
			'vehicle_type' => $vehicleType,
		],
		$buildBookingSearchQuery($bookingSearch)
	);

	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');
	if (!$isUserSession) {
		$redirectWithUserLoginFlash(
			'Please log in before confirming a booking.',
			'',
			false,
			false,
			'index.php?' . http_build_query($checkoutRedirectQuery)
		);
	}

	if (!$isUserEligibleForBooking($sessionUser)) {
		$fallbackQuery = array_merge(
			[
				'page' => 'booking-select',
				'vehicle_type' => $vehicleType,
				'booking_notice' => $bookingAgeRestrictionMessage,
			],
			$buildBookingSearchQuery($bookingSearch)
		);
		header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
		exit;
	}

	if (!$bookingSearch['is_valid'] || $vehicleId <= 0) {
		$bookingNotice = !empty($bookingSearch['errors'])
			? (string) $bookingSearch['errors'][0]
			: 'Please complete all booking fields before continuing.';
		$checkoutRedirectQuery['booking_notice'] = $bookingNotice;
		header('Location: index.php?' . http_build_query($checkoutRedirectQuery), true, 303);
		exit;
	}

	$userId = (int) ($sessionUser['id'] ?? 0);
	if ($userId <= 0) {
		header('Location: index.php', true, 303);
		exit;
	}

	$pickupDateTime = $bookingSearch['pickup_datetime'];
	$returnDateTime = $bookingSearch['return_datetime'];
	if (!($pickupDateTime instanceof DateTimeImmutable) || !($returnDateTime instanceof DateTimeImmutable)) {
		$checkoutRedirectQuery['booking_notice'] = 'Pickup and return datetime are invalid.';
		header('Location: index.php?' . http_build_query($checkoutRedirectQuery), true, 303);
		exit;
	}

	try {
		$pdo = db();
		$hasUserOverdueBooking = $doesUserHaveOverdueBooking($pdo, $userId);
		if ($hasUserOverdueBooking) {
			$checkoutRedirectQuery['booking_notice'] = $bookingVehicleOverdueMessage;
			header('Location: index.php?' . http_build_query($checkoutRedirectQuery), true, 303);
			exit;
		}

		$vehicleRow = $fetchBookableVehicleById($pdo, $vehicleId);
		if (!is_array($vehicleRow)) {
			$checkoutRedirectQuery['booking_notice'] = 'Selected vehicle was not found.';
			header('Location: index.php?' . http_build_query($checkoutRedirectQuery), true, 303);
			exit;
		}

		$isVehicleOverdueBlocked = $isVehicleBlockedByOverdueStatus($pdo, $vehicleId);
		if ($isVehicleOverdueBlocked) {
			$fallbackQuery = array_merge(
				[
					'page' => 'booking-select',
					'vehicle_type' => $vehicleType,
					'booking_notice' => $bookingVehicleOverdueMessage,
				],
				$buildBookingSearchQuery($bookingSearch)
			);
			header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
			exit;
		}

		$isAvailable = $isVehicleAvailableForBookingWindow($pdo, $vehicleId, $pickupDateTime, $returnDateTime);
		if (!$isAvailable) {
			$fallbackQuery = array_merge(
				[
					'page' => 'booking-select',
					'vehicle_type' => $vehicleType,
					'booking_notice' => 'Vehicle unavailable for the selected date range.',
				],
				$buildBookingSearchQuery($bookingSearch)
			);
			header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
			exit;
		}

		$hasUserOverlap = $doesUserHaveOverlappingBookingWindow($pdo, $userId, $pickupDateTime, $returnDateTime);
		if ($hasUserOverlap) {
			$engineRedirectQuery = array_merge(
				[
					'page' => 'booking-engine',
					'vehicle_id' => $vehicleId,
					'vehicle_type' => $vehicleType,
					'booking_notice' => $bookingActiveOverlapMessage,
				],
				$buildBookingSearchQuery($bookingSearch)
			);
			header('Location: index.php?' . http_build_query($engineRedirectQuery), true, 303);
			exit;
		}

		$vehiclePricePerDay = (int) ($vehicleRow['price_per_day'] ?? 0);
		$priceBreakdown = $calculateBookingPriceBreakdown($vehiclePricePerDay, $pickupDateTime, $returnDateTime);

		$userDriversId = trim((string) ($sessionUser['drivers_id'] ?? ''));
		if ($userDriversId === '') {
			$userLookupStmt = $pdo->prepare(
				'SELECT drivers_id
				 FROM users
				 WHERE id = :lookup_user_id
				 LIMIT 1'
			);
			$userLookupStmt->execute([
				'lookup_user_id' => $userId,
			]);
			$userDriversId = trim((string) $userLookupStmt->fetchColumn());
		}
		if ($userDriversId === '') {
			$userDriversId = 'RIDEX-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
		}

		$pdo->beginTransaction();

		$nextBookingIdStmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_booking_id FROM bookings FOR UPDATE');
		$nextBookingId = (int) $nextBookingIdStmt->fetchColumn();
		if ($nextBookingId <= 0) {
			$nextBookingId = 1;
		}

		$bookingNumber = '#RX-' . str_pad((string) $nextBookingId, 4, '0', STR_PAD_LEFT);
		$bookingPaymentStatus = 'pending';
		$paidAmount = 0;

		$insertBookingStmt = $pdo->prepare(
			'INSERT INTO bookings (
				id,
				booking_number,
				user_id,
				vehicle_id,
				pickup_location,
				return_location,
				pickup_datetime,
				return_datetime,
				status,
				payment_status,
				payment_method,
				total_amount,
				paid_amount,
				late_fee,
				drivers_id,
				created_at,
				updated_at
			) VALUES (
				:id,
				:booking_number,
				:user_id,
				:vehicle_id,
				:pickup_location,
				:return_location,
				:pickup_datetime,
				:return_datetime,
				:status,
				:payment_status,
				:payment_method,
				:total_amount,
				:paid_amount,
				:late_fee,
				:drivers_id,
				CURRENT_TIMESTAMP,
				CURRENT_TIMESTAMP
			)'
		);
		$insertBookingStmt->execute([
			'id' => $nextBookingId,
			'booking_number' => $bookingNumber,
			'user_id' => $userId,
			'vehicle_id' => $vehicleId,
			'pickup_location' => (string) ($bookingSearch['pickup_location'] ?? ''),
			'return_location' => (string) ($bookingSearch['return_location'] ?? ''),
			'pickup_datetime' => $pickupDateTime->format('Y-m-d H:i:s'),
			'return_datetime' => $returnDateTime->format('Y-m-d H:i:s'),
			'status' => 'reserved',
			'payment_status' => $bookingPaymentStatus,
			'payment_method' => $paymentMethod,
			'total_amount' => (int) ($priceBreakdown['total_amount'] ?? 0),
			'paid_amount' => $paidAmount,
			'late_fee' => 0,
			'drivers_id' => $userDriversId,
		]);

		$paymentAmount = (int) ($priceBreakdown['total_amount'] ?? 0);
		$paymentMethodValue = 'cash';
		$paymentStatusValue = 'initiated';
		$paymentProviderResponse = json_encode([
			'source' => 'booking-flow',
			'payment_method' => $paymentMethod,
		], JSON_UNESCAPED_SLASHES);
		if (!is_string($paymentProviderResponse)) {
			$paymentProviderResponse = '{}';
		}

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
				CURRENT_TIMESTAMP,
				NULL,
				:provider_response,
				CURRENT_TIMESTAMP,
				CURRENT_TIMESTAMP
			)'
		);
		$insertPaymentStmt->execute([
			'booking_id' => $nextBookingId,
			'amount' => $paymentAmount,
			'method' => $paymentMethodValue,
			'status' => $paymentStatusValue,
			'provider_response' => $paymentProviderResponse,
		]);

		$pdo->commit();

		try {
			$bookingFlowToken = bin2hex(random_bytes(12));
		} catch (Throwable $randomException) {
			$bookingFlowToken = sha1(uniqid((string) $nextBookingId, true));
		}

		$_SESSION['booking_flow_lock'] = [
			'booking_id' => $nextBookingId,
			'token' => $bookingFlowToken,
		];

		header(
			'Location: index.php?page=booking-thank-you&booking_id=' . urlencode((string) $nextBookingId) . '&flow_token=' . urlencode($bookingFlowToken),
			true,
			303
		);
		exit;
	} catch (Throwable $exception) {
		if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
			$pdo->rollBack();
		}

		error_log('User booking create failed: ' . $exception->getMessage());
		$checkoutRedirectQuery['booking_notice'] = 'Unable to create booking right now. Please try again.';
		header('Location: index.php?' . http_build_query($checkoutRedirectQuery), true, 303);
		exit;
	}
}

if ($isUserBookingCancellationRequestPost) {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');

	$allowedHistoryTabs = ['active', 'pending', 'completed', 'cancelled'];
	$historyTab = strtolower(trim((string) ($_POST['history_tab'] ?? 'pending')));
	if (!in_array($historyTab, $allowedHistoryTabs, true)) {
		$historyTab = 'pending';
	}

	$historyRedirectQuery = [
		'page' => 'user-booking-history',
		'tab' => $historyTab,
	];

	$redirectToHistoryWithNotice = static function (array $baseQuery, string $notice = ''): void {
		if ($notice !== '') {
			$baseQuery['booking_notice'] = $notice;
		}

		header('Location: index.php?' . http_build_query($baseQuery), true, 303);
		exit;
	};

	if (!$isUserSession) {
		$redirectWithUserLoginFlash(
			'Please log in to manage your bookings.',
			'',
			false,
			false,
			'index.php?' . http_build_query($historyRedirectQuery)
		);
	}

	$userId = (int) ($sessionUser['id'] ?? 0);
	$bookingId = (int) ($_POST['booking_id'] ?? 0);
	if ($userId <= 0 || $bookingId <= 0) {
		$redirectToHistoryWithNotice($historyRedirectQuery, 'Invalid cancellation request.');
	}

	try {
		$pdo = db();
		$pdo->beginTransaction();

		$bookingLookupStmt = $pdo->prepare(
			'SELECT
				id,
				user_id,
				vehicle_id,
				status,
				payment_status,
				pickup_datetime
			 FROM bookings
			 WHERE id = :booking_id
			 LIMIT 1
			 FOR UPDATE'
		);
		$bookingLookupStmt->execute([
			'booking_id' => $bookingId,
		]);
		$bookingRow = $bookingLookupStmt->fetch();

		if (!is_array($bookingRow) || (int) ($bookingRow['user_id'] ?? 0) !== $userId) {
			$pdo->rollBack();
			$redirectToHistoryWithNotice($historyRedirectQuery, 'Booking not found.');
		}

		$bookingStatus = strtolower(trim((string) ($bookingRow['status'] ?? '')));
		$pickupDateTime = $parseDateTimeSafe($bookingRow['pickup_datetime'] ?? null);
		$nowDateTime = new DateTimeImmutable('now');

		if ($bookingStatus !== 'reserved' || ($pickupDateTime instanceof DateTimeImmutable && $nowDateTime >= $pickupDateTime)) {
			$pdo->rollBack();
			$redirectToHistoryWithNotice($historyRedirectQuery, 'Only pending bookings can be cancelled.');
		}

		$paymentStatus = strtolower(trim((string) ($bookingRow['payment_status'] ?? 'pending')));
		$nextPaymentStatus = $paymentStatus === 'refunded' ? 'refunded' : 'cancelled';

		$cancelBookingStmt = $pdo->prepare(
			'UPDATE bookings
			 SET status = :status,
				 payment_status = :payment_status,
				 updated_at = CURRENT_TIMESTAMP
			 WHERE id = :booking_id
			 LIMIT 1'
		);
		$cancelBookingStmt->execute([
			'status' => 'cancelled',
			'payment_status' => $nextPaymentStatus,
			'booking_id' => $bookingId,
		]);

		$vehicleId = (int) ($bookingRow['vehicle_id'] ?? 0);
		if ($vehicleId > 0) {
			$remainingActiveBookingStmt = $pdo->prepare(
				'SELECT COUNT(*)
				 FROM bookings
				 WHERE vehicle_id = :vehicle_id
					AND id <> :booking_id
					AND status IN ("reserved", "on_trip", "overdue")'
			);
			$remainingActiveBookingStmt->execute([
				'vehicle_id' => $vehicleId,
				'booking_id' => $bookingId,
			]);
			$remainingActiveBookingCount = (int) $remainingActiveBookingStmt->fetchColumn();

			if ($remainingActiveBookingCount <= 0) {
				$markVehicleAvailableStmt = $pdo->prepare(
					'UPDATE vehicles
					 SET status = :status,
						 updated_at = CURRENT_TIMESTAMP
					 WHERE id = :vehicle_id
					 LIMIT 1'
				);
				$markVehicleAvailableStmt->execute([
					'status' => 'available',
					'vehicle_id' => $vehicleId,
				]);
			}
		}

		$pdo->commit();
		$redirectToHistoryWithNotice($historyRedirectQuery, 'Cancellation request submitted. Wait for admin approval.');
	} catch (Throwable $exception) {
		if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
			$pdo->rollBack();
		}

		error_log('User booking cancellation request failed: ' . $exception->getMessage());
		$redirectToHistoryWithNotice($historyRedirectQuery, 'Unable to submit cancellation request right now.');
	}
}

$page = strtolower(trim((string) ($_GET['page'] ?? 'home')));

if ($page === 'user-auth-lookup') {
	header('Content-Type: application/json; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	header('X-Content-Type-Options: nosniff');

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo '{"ok":false,"message":"Method not allowed."}';
		exit;
	}

	$lookupStep = strtolower(trim((string) ($_POST['step'] ?? '')));
	$lookupResponse = [
		'ok' => false,
		'message' => 'Invalid request.',
	];
	$lookupStatusCode = 200;

	try {
		$pdo = db();

		if ($lookupStep === 'email') {
			$lookupEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
			if ($lookupEmail === '' || !filter_var($lookupEmail, FILTER_VALIDATE_EMAIL)) {
				$lookupStatusCode = 400;
				$lookupResponse['message'] = 'Please enter a valid email address.';
			} else {
				$lookupUserId = ridex_user_find_id_by_email($pdo, $lookupEmail, 'user');

				if ($lookupUserId > 0) {
					$lookupResponse = [
						'ok' => true,
						'message' => 'Email verified.',
					];
				} else {
					$lookupResponse['message'] = 'No user account found for this email.';
				}
			}
		} elseif ($lookupStep === 'driver') {
			$lookupEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
			$lookupDriversId = trim((string) ($_POST['drivers_id'] ?? ''));

			if ($lookupEmail === '' || !filter_var($lookupEmail, FILTER_VALIDATE_EMAIL)) {
				$lookupStatusCode = 400;
				$lookupResponse['message'] = 'Please verify your email first.';
			} elseif ($lookupDriversId === '') {
				$lookupStatusCode = 400;
				$lookupResponse['message'] = 'Please enter your Driver ID.';
			} else {
				$lookupUserId = ridex_user_find_id_by_email_and_driver($pdo, $lookupEmail, $lookupDriversId);

				if ($lookupUserId > 0) {
					$lookupResponse = [
						'ok' => true,
						'message' => 'Driver ID verified.',
					];
				} else {
					$lookupResponse['message'] = 'Driver ID does not match the entered email.';
				}
			}
		} elseif ($lookupStep === 'register-driver') {
			$lookupDriversId = trim((string) ($_POST['drivers_id'] ?? ''));

			if ($lookupDriversId === '') {
				$lookupStatusCode = 400;
				$lookupResponse['message'] = 'Please enter your Driver ID.';
			} else {
				$lookupUserId = ridex_user_find_id_by_driver_id($pdo, $lookupDriversId, 'user');

				if ($lookupUserId > 0) {
					$lookupResponse['message'] = 'This Driver ID has already been used to create an account.';
				} else {
					$lookupResponse = [
						'ok' => true,
						'message' => 'Driver ID is available.',
					];
				}
			}
		} else {
			$lookupStatusCode = 400;
			$lookupResponse['message'] = 'Unknown verification step.';
		}
	} catch (Throwable $exception) {
		error_log('User auth lookup failed: ' . $exception->getMessage());
		$lookupStatusCode = 500;
		$lookupResponse = [
			'ok' => false,
			'message' => 'Unable to verify right now. Please try again.',
		];
	}

	http_response_code($lookupStatusCode);
	$lookupJson = json_encode(
		$lookupResponse,
		JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
	);

	if (!is_string($lookupJson)) {
		http_response_code(500);
		echo '{"ok":false,"message":"Unable to encode response."}';
		exit;
	}

	echo $lookupJson;
	exit;
}

$bookingSearchKeys = [
	'pickup-location',
	'return-location',
	'pickup-date',
	'return-date',
	'pickup-time',
	'return-time',
	'same-return',
];

$isBookingSearchAttempt = false;
foreach ($bookingSearchKeys as $bookingSearchKey) {
	if (array_key_exists($bookingSearchKey, $_GET)) {
		$isBookingSearchAttempt = true;
		break;
	}
}

if ($page === 'vehicles' && $isBookingSearchAttempt) {
	header('Location: index.php#home-vehicle-category', true, 302);
	exit;
}

if ($page === 'booking-select') {
	$selectedVehicleType = $sanitizeVehicleType($_GET['vehicle_type'] ?? 'cars');
	$bookingSearch = $sanitizeBookingSearchInput($_GET);
	$bookingSearchQuery = $buildBookingSearchQuery($bookingSearch);
	$bookingNotice = trim((string) ($_GET['booking_notice'] ?? ''));

	$selectedFilterTypesRaw = $_GET['types'] ?? [];
	if (!is_array($selectedFilterTypesRaw) || empty($selectedFilterTypesRaw)) {
		$selectedFilterTypesRaw = [$selectedVehicleType];
	}
	$selectedFilterTypes = [];
	foreach ($selectedFilterTypesRaw as $rawType) {
		$normalizedType = strtolower(trim((string) $rawType));
		if (in_array($normalizedType, ['cars', 'bikes', 'luxury'], true)) {
			$selectedFilterTypes[] = $normalizedType;
		}
	}
	$selectedFilterTypes = array_values(array_unique($selectedFilterTypes));
	if (empty($selectedFilterTypes)) {
		$selectedFilterTypes = [$selectedVehicleType];
	}

	$selectedTransmissionsRaw = $_GET['transmissions'] ?? [];
	if (!is_array($selectedTransmissionsRaw)) {
		$selectedTransmissionsRaw = [];
	}
	$selectedTransmissions = [];
	foreach ($selectedTransmissionsRaw as $rawTransmission) {
		$normalizedTransmission = strtolower(trim((string) $rawTransmission));
		if (in_array($normalizedTransmission, ['manual', 'automatic', 'hybrid'], true)) {
			$selectedTransmissions[] = $normalizedTransmission;
		}
	}
	$selectedTransmissions = array_values(array_unique($selectedTransmissions));

	$selectedSeatsMin = max(0, (int) ($_GET['seats_min'] ?? 0));
	$selectedPriceMin = max(0, (int) ($_GET['price_min'] ?? 0));
	$selectedPriceMax = max(0, (int) ($_GET['price_max'] ?? 0));
	if ($selectedPriceMax > 0 && $selectedPriceMax < $selectedPriceMin) {
		$tempPrice = $selectedPriceMin;
		$selectedPriceMin = $selectedPriceMax;
		$selectedPriceMax = $tempPrice;
	}

	$selectedSortPrice = strtolower(trim((string) ($_GET['sort_price'] ?? 'high')));
	if (!in_array($selectedSortPrice, ['low', 'high'], true)) {
		$selectedSortPrice = 'high';
	}

	$isFlowStartRequest = trim((string) ($_GET['flow_start'] ?? '')) === '1';
	if ($isFlowStartRequest) {
		unset($_SESSION['booking_flow_lock']);
	}

	$lockedBookingId = (int) (($_SESSION['booking_flow_lock']['booking_id'] ?? 0));
	if (!$isFlowStartRequest && $lockedBookingId > 0) {
		header(
			'Location: index.php?page=booking-thank-you&booking_id=' . urlencode((string) $lockedBookingId),
			true,
			303
		);
		exit;
	}

	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');
	if (!$isUserSession) {
		$authRedirectQuery = array_merge(
			[
				'page' => 'booking-select',
				'vehicle_type' => $selectedVehicleType,
				'flow_start' => '1',
			],
			$bookingSearchQuery
		);

		if (!empty($selectedFilterTypes)) {
			$authRedirectQuery['types'] = $selectedFilterTypes;
		}

		if (!empty($selectedTransmissions)) {
			$authRedirectQuery['transmissions'] = $selectedTransmissions;
		}

		if ($selectedSeatsMin > 0) {
			$authRedirectQuery['seats_min'] = $selectedSeatsMin;
		}

		if ($selectedPriceMin > 0) {
			$authRedirectQuery['price_min'] = $selectedPriceMin;
		}

		if ($selectedPriceMax > 0) {
			$authRedirectQuery['price_max'] = $selectedPriceMax;
		}

		$authRedirectQuery['sort_price'] = $selectedSortPrice;

		$redirectWithUserLoginFlash(
			'Please log in before searching vehicles.',
			'',
			false,
			false,
			'index.php?' . http_build_query($authRedirectQuery)
		);
	}

	$bookingSelectVehicles = [];
	$bookingNoAvailabilityMessage = '';
	$userId = (int) ($sessionUser['id'] ?? 0);
	$isUserBlockedByOverdue = false;
	$isUserBlockedByActiveOverlap = false;
	if ($userId > 0) {
		try {
			$isUserBlockedByOverdue = $doesUserHaveOverdueBooking(db(), $userId);

			$pickupDateTime = $bookingSearch['pickup_datetime'] ?? null;
			$returnDateTime = $bookingSearch['return_datetime'] ?? null;
			if (
				!$isUserBlockedByOverdue
				&& $pickupDateTime instanceof DateTimeImmutable
				&& $returnDateTime instanceof DateTimeImmutable
			) {
				$isUserBlockedByActiveOverlap = $doesUserHaveOverlappingBookingWindow(
					db(),
					$userId,
					$pickupDateTime,
					$returnDateTime
				);
			}
		} catch (Throwable $exception) {
			ridex_log_exception('Booking select pre-check failed', $exception);
		}
	}

	if ($isUserBlockedByOverdue && $bookingNotice === '') {
		$bookingNotice = $bookingVehicleOverdueMessage;
	}

	if ($isUserBlockedByActiveOverlap && $bookingNotice === '') {
		$bookingNotice = $bookingActiveOverlapMessage;
	}

	if ($isUserBlockedByOverdue || $isUserBlockedByActiveOverlap) {
		$bookingSelectVehicles = [];
		$bookingNoAvailabilityMessage = '';
	} elseif (!$bookingSearch['is_valid']) {
		if ($bookingNotice === '') {
			$bookingNotice = !empty($bookingSearch['errors'])
				? (string) $bookingSearch['errors'][0]
				: 'Please fill all booking fields to search vehicles.';
		}
	} else {
		try {
			$pdo = db();
			$sql = 'SELECT v.*, c.name AS category_name
				FROM vehicles v
				INNER JOIN categories c ON c.id = v.category_id
				WHERE v.deleted_at IS NULL
					AND v.status NOT IN (:maintenance_status, :overdue_status)
					AND NOT EXISTS (
						SELECT 1
						FROM bookings b_overdue
						WHERE b_overdue.vehicle_id = v.id
							AND b_overdue.status = "overdue"
					)';
			$params = [
				'maintenance_status' => 'maintenance',
				'overdue_status' => 'overdue',
			];

			$typePlaceholders = [];
			foreach ($selectedFilterTypes as $typeIndex => $filterType) {
				$placeholder = 'vehicle_type_' . $typeIndex;
				$typePlaceholders[] = ':' . $placeholder;
				$params[$placeholder] = $filterType;
			}
			if (!empty($typePlaceholders)) {
				$sql .= ' AND v.vehicle_type IN (' . implode(', ', $typePlaceholders) . ')';
			}

			$transmissionPlaceholders = [];
			foreach ($selectedTransmissions as $transmissionIndex => $filterTransmission) {
				$placeholder = 'transmission_' . $transmissionIndex;
				$transmissionPlaceholders[] = ':' . $placeholder;
				$params[$placeholder] = $filterTransmission;
			}
			if (!empty($transmissionPlaceholders)) {
				$sql .= ' AND LOWER(v.transmission_type) IN (' . implode(', ', $transmissionPlaceholders) . ')';
			}

			if ($selectedSeatsMin > 0) {
				$sql .= ' AND COALESCE(v.number_of_seats, 0) >= :minimum_seats';
				$params['minimum_seats'] = $selectedSeatsMin;
			}

			if ($selectedPriceMin > 0) {
				$sql .= ' AND v.price_per_day >= :minimum_price';
				$params['minimum_price'] = $selectedPriceMin;
			}

			if ($selectedPriceMax > 0) {
				$sql .= ' AND v.price_per_day <= :maximum_price';
				$params['maximum_price'] = $selectedPriceMax;
			}

			$pickupDateTime = $bookingSearch['pickup_datetime'];
			$returnDateTime = $bookingSearch['return_datetime'];
			if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
				$sql .= ' AND NOT EXISTS (
					SELECT 1
					FROM bookings b
					WHERE b.vehicle_id = v.id
						AND b.status IN ("reserved", "on_trip", "overdue")
						AND :requested_pickup < b.return_datetime
						AND :requested_return > DATE_SUB(b.pickup_datetime, INTERVAL 2 DAY)
				)';
				$params['requested_pickup'] = $pickupDateTime->format('Y-m-d H:i:s');
				$params['requested_return'] = $returnDateTime->format('Y-m-d H:i:s');
			}

			$sql .= $selectedSortPrice === 'low'
				? ' ORDER BY v.price_per_day ASC, v.id ASC'
				: ' ORDER BY v.price_per_day DESC, v.id DESC';

			$selectVehiclesStmt = $pdo->prepare($sql);
			$selectVehiclesStmt->execute($params);
			$bookingSelectVehicles = $selectVehiclesStmt->fetchAll() ?: [];

			if (empty($bookingSelectVehicles)) {
				$bookingNoAvailabilityMessage = 'No vehicles available for the selected date and filters.';
			}
		} catch (Throwable $exception) {
			error_log('Booking selection query failed: ' . $exception->getMessage());
			$bookingSelectVehicles = [];
			$bookingNoAvailabilityMessage = 'Unable to load vehicles right now. Please try again.';
		}
	}

	$title = 'Ridex | Vehicle Selection';
	$view = 'booking/select';
	$viewData = [
		'selectedVehicleType' => $selectedVehicleType,
		'bookingSearch' => $bookingSearch,
		'bookingSearchQuery' => $bookingSearchQuery,
		'selectedFilterTypes' => $selectedFilterTypes,
		'selectedTransmissions' => $selectedTransmissions,
		'selectedSeatsMin' => $selectedSeatsMin,
		'selectedPriceMin' => $selectedPriceMin,
		'selectedPriceMax' => $selectedPriceMax,
		'selectedSortPrice' => $selectedSortPrice,
		'bookingSelectVehicles' => $bookingSelectVehicles,
		'bookingNotice' => $bookingNotice,
		'bookingNoAvailabilityMessage' => $bookingNoAvailabilityMessage,
		'hideFooter' => true,
	];
} elseif ($page === 'booking-engine') {
	$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
	$selectedVehicleType = $sanitizeVehicleType($_GET['vehicle_type'] ?? 'cars');
	$bookingSearch = $sanitizeBookingSearchInput($_GET);
	$bookingSearchQuery = $buildBookingSearchQuery($bookingSearch);
	$bookingNotice = trim((string) ($_GET['booking_notice'] ?? ''));
	$directUnavailable = false;
	$directAttempt = trim((string) ($_GET['attempt'] ?? '')) === '1';
	$isFlowStartRequest = trim((string) ($_GET['flow_start'] ?? '')) === '1';

	if ($isFlowStartRequest) {
		unset($_SESSION['booking_flow_lock']);
	}

	$lockedBookingId = (int) (($_SESSION['booking_flow_lock']['booking_id'] ?? 0));
	if (!$isFlowStartRequest && $lockedBookingId > 0) {
		header(
			'Location: index.php?page=booking-thank-you&booking_id=' . urlencode((string) $lockedBookingId),
			true,
			303
		);
		exit;
	}

	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');
	if (!$isUserSession) {
		$authRedirectQuery = array_merge(
			[
				'page' => 'booking-engine',
				'vehicle_id' => $vehicleId,
				'vehicle_type' => $selectedVehicleType,
				'flow_start' => '1',
			],
			$bookingSearchQuery
		);

		if ($directAttempt) {
			$authRedirectQuery['attempt'] = '1';
		}

		$redirectWithUserLoginFlash(
			'Please log in before continuing to booking checkout.',
			'',
			false,
			false,
			'index.php?' . http_build_query($authRedirectQuery)
		);
	}

	$sessionUserId = (int) ($sessionUser['id'] ?? 0);
	if ($sessionUserId <= 0) {
		ridex_redirect('index.php', 303);
	}

	if (!$isUserEligibleForBooking($sessionUser)) {
		$underageRedirectQuery = array_merge(
			[
				'page' => 'booking-select',
				'vehicle_type' => $selectedVehicleType,
				'booking_notice' => $bookingAgeRestrictionMessage,
			],
			$bookingSearchQuery
		);
		header('Location: index.php?' . http_build_query($underageRedirectQuery), true, 303);
		exit;
	}

	$userHasOverdueBooking = false;
	try {
		$userHasOverdueBooking = $doesUserHaveOverdueBooking(db(), $sessionUserId);
	} catch (Throwable $exception) {
		ridex_log_exception('Booking engine overdue check failed', $exception);
	}
	if ($userHasOverdueBooking && $bookingNotice === '') {
		$bookingNotice = $bookingVehicleOverdueMessage;
	}

	$selectedVehicle = null;
	if ($vehicleId > 0) {
		try {
			$selectedVehicle = $fetchBookableVehicleById(db(), $vehicleId);
			if (is_array($selectedVehicle)) {
				$selectedVehicleType = $sanitizeVehicleType($selectedVehicle['vehicle_type'] ?? $selectedVehicleType);
			}
		} catch (Throwable $exception) {
			error_log('Booking engine vehicle lookup failed: ' . $exception->getMessage());
			$selectedVehicle = null;
		}
	}

	if ($directAttempt) {
		if ($userHasOverdueBooking) {
			$bookingNotice = $bookingVehicleOverdueMessage;
			$directUnavailable = true;
		} elseif (!$bookingSearch['is_valid']) {
			$bookingNotice = !empty($bookingSearch['errors'])
				? (string) $bookingSearch['errors'][0]
				: 'Please fill all booking fields.';
		} elseif (!is_array($selectedVehicle)) {
			$bookingNotice = 'Selected vehicle is unavailable.';
			$directUnavailable = true;
		} else {
			$pickupDateTime = $bookingSearch['pickup_datetime'];
			$returnDateTime = $bookingSearch['return_datetime'];
			if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
				try {
					$pdo = db();

					$isDirectVehicleOverdueBlocked = $isVehicleBlockedByOverdueStatus($pdo, $vehicleId);
					if ($isDirectVehicleOverdueBlocked) {
						$directUnavailable = true;
						$bookingNotice = $bookingVehicleOverdueMessage;
					} else {
						$hasUserOverlap = $doesUserHaveOverlappingBookingWindow($pdo, $sessionUserId, $pickupDateTime, $returnDateTime);
						if ($hasUserOverlap) {
							$bookingNotice = $bookingActiveOverlapMessage;
						} else {
							$isDirectVehicleAvailable = $isVehicleAvailableForBookingWindow($pdo, $vehicleId, $pickupDateTime, $returnDateTime);
							if ($isDirectVehicleAvailable) {
								$checkoutQuery = array_merge(
									[
										'page' => 'booking-checkout',
										'vehicle_id' => $vehicleId,
										'vehicle_type' => $selectedVehicleType,
									],
									$bookingSearchQuery
								);
								header('Location: index.php?' . http_build_query($checkoutQuery), true, 303);
								exit;
							}

							$directUnavailable = true;
							$bookingNotice = 'Vehicle unavailable for the selected date range. Please search alternatives.';
						}
					}
				} catch (Throwable $exception) {
					error_log('Direct booking availability check failed: ' . $exception->getMessage());
					$directUnavailable = true;
					$bookingNotice = 'Unable to verify vehicle availability right now.';
				}
			}
		}
	}

	$fallbackSearchUrl = 'index.php?' . http_build_query(array_merge(
		[
			'page' => 'booking-select',
			'vehicle_type' => $selectedVehicleType,
		],
		$bookingSearchQuery
	));

	$title = 'Ridex | Booking Engine';
	$view = 'booking/form';
	$viewData = [
		'selectedVehicle' => $selectedVehicle,
		'selectedVehicleType' => $selectedVehicleType,
		'selectedVehicleId' => $vehicleId,
		'bookingSearch' => $bookingSearch,
		'bookingNotice' => $bookingNotice,
		'directUnavailable' => $directUnavailable,
		'flowStart' => $isFlowStartRequest,
		'directSearchUrl' => $fallbackSearchUrl,
	];
} elseif ($page === 'booking-checkout') {
	$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
	$selectedVehicleType = $sanitizeVehicleType($_GET['vehicle_type'] ?? 'cars');
	$bookingSearch = $sanitizeBookingSearchInput($_GET);
	$bookingSearchQuery = $buildBookingSearchQuery($bookingSearch);
	$bookingNotice = trim((string) ($_GET['booking_notice'] ?? ''));
	$isFlowStartRequest = trim((string) ($_GET['flow_start'] ?? '')) === '1';

	if ($isFlowStartRequest) {
		unset($_SESSION['booking_flow_lock']);
	}

	$lockedBookingId = (int) (($_SESSION['booking_flow_lock']['booking_id'] ?? 0));
	if (!$isFlowStartRequest && $lockedBookingId > 0) {
		header(
			'Location: index.php?page=booking-thank-you&booking_id=' . urlencode((string) $lockedBookingId),
			true,
			303
		);
		exit;
	}

	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');
	if (!$isUserSession) {
		$authRedirectQuery = array_merge(
			[
				'page' => 'booking-checkout',
				'vehicle_id' => $vehicleId,
				'vehicle_type' => $selectedVehicleType,
			],
			$bookingSearchQuery
		);

		if ($isFlowStartRequest) {
			$authRedirectQuery['flow_start'] = '1';
		}

		$redirectWithUserLoginFlash(
			'Please log in before opening checkout.',
			'',
			false,
			false,
			'index.php?' . http_build_query($authRedirectQuery)
		);
	}

	$sessionUserId = (int) ($sessionUser['id'] ?? 0);
	if ($sessionUserId <= 0) {
		ridex_redirect('index.php', 303);
	}

	if (!$isUserEligibleForBooking($sessionUser)) {
		$underageRedirectQuery = array_merge(
			[
				'page' => 'booking-select',
				'vehicle_type' => $selectedVehicleType,
				'booking_notice' => $bookingAgeRestrictionMessage,
			],
			$bookingSearchQuery
		);
		header('Location: index.php?' . http_build_query($underageRedirectQuery), true, 303);
		exit;
	}

	$userHasOverdueBooking = false;
	try {
		$userHasOverdueBooking = $doesUserHaveOverdueBooking(db(), $sessionUserId);
	} catch (Throwable $exception) {
		ridex_log_exception('Booking checkout overdue check failed', $exception);
	}
	if ($userHasOverdueBooking) {
		$engineRedirectQuery = array_merge(
			[
				'page' => 'booking-engine',
				'vehicle_id' => $vehicleId,
				'vehicle_type' => $selectedVehicleType,
				'booking_notice' => $bookingVehicleOverdueMessage,
			],
			$bookingSearchQuery
		);
		header('Location: index.php?' . http_build_query($engineRedirectQuery), true, 303);
		exit;
	}

	if (!$bookingSearch['is_valid']) {
		$fallbackQuery = array_merge(
			[
				'page' => 'booking-select',
				'vehicle_type' => $selectedVehicleType,
				'booking_notice' => 'Please complete booking details first.',
			],
			$bookingSearchQuery
		);
		header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
		exit;
	}

	$checkoutVehicle = null;
	$bookingPriceBreakdown = null;
	$checkoutBookingNumberPreview = '#RX-0001';

	$pickupDateTime = $bookingSearch['pickup_datetime'];
	$returnDateTime = $bookingSearch['return_datetime'];

	try {
		$pdo = db();
		$nextBookingIdStmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_booking_id FROM bookings');
		$nextPreviewBookingId = (int) $nextBookingIdStmt->fetchColumn();
		if ($nextPreviewBookingId > 0) {
			$checkoutBookingNumberPreview = '#RX-' . str_pad((string) $nextPreviewBookingId, 4, '0', STR_PAD_LEFT);
		}

		$checkoutVehicle = $fetchBookableVehicleById($pdo, $vehicleId);
		if (!is_array($checkoutVehicle)) {
			$fallbackQuery = array_merge(
				[
					'page' => 'booking-select',
					'vehicle_type' => $selectedVehicleType,
					'booking_notice' => 'Selected vehicle was not found.',
				],
				$bookingSearchQuery
			);
			header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
			exit;
		}

		$selectedVehicleType = $sanitizeVehicleType($checkoutVehicle['vehicle_type'] ?? $selectedVehicleType);
		$isCheckoutVehicleOverdueBlocked = $isVehicleBlockedByOverdueStatus($pdo, $vehicleId);
		if ($isCheckoutVehicleOverdueBlocked) {
			$fallbackQuery = array_merge(
				[
					'page' => 'booking-select',
					'vehicle_type' => $selectedVehicleType,
					'booking_notice' => $bookingVehicleOverdueMessage,
				],
				$bookingSearchQuery
			);
			header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
			exit;
		}

		if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
			$hasUserOverlap = $doesUserHaveOverlappingBookingWindow($pdo, $sessionUserId, $pickupDateTime, $returnDateTime);
			if ($hasUserOverlap) {
				$engineRedirectQuery = array_merge(
					[
						'page' => 'booking-engine',
						'vehicle_id' => $vehicleId,
						'vehicle_type' => $selectedVehicleType,
						'booking_notice' => $bookingActiveOverlapMessage,
					],
					$bookingSearchQuery
				);
				header('Location: index.php?' . http_build_query($engineRedirectQuery), true, 303);
				exit;
			}

			$isCheckoutVehicleAvailable = $isVehicleAvailableForBookingWindow($pdo, $vehicleId, $pickupDateTime, $returnDateTime);
			if (!$isCheckoutVehicleAvailable) {
				$fallbackQuery = array_merge(
					[
						'page' => 'booking-select',
						'vehicle_type' => $selectedVehicleType,
						'booking_notice' => 'Vehicle unavailable for the selected date range.',
					],
					$bookingSearchQuery
				);
				header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
				exit;
			}

			$bookingPriceBreakdown = $calculateBookingPriceBreakdown(
				(int) ($checkoutVehicle['price_per_day'] ?? 0),
				$pickupDateTime,
				$returnDateTime
			);
		}
	} catch (Throwable $exception) {
		error_log('Booking checkout lookup failed: ' . $exception->getMessage());
		$fallbackQuery = array_merge(
			[
				'page' => 'booking-select',
				'vehicle_type' => $selectedVehicleType,
				'booking_notice' => 'Unable to prepare checkout right now.',
			],
			$bookingSearchQuery
		);
		header('Location: index.php?' . http_build_query($fallbackQuery), true, 303);
		exit;
	}

	$title = 'Ridex | Booking Checkout';
	$view = 'booking/detail';
	$viewData = [
		'checkoutVehicle' => $checkoutVehicle,
		'selectedVehicleType' => $selectedVehicleType,
		'bookingSearch' => $bookingSearch,
		'bookingSearchQuery' => $bookingSearchQuery,
		'bookingPriceBreakdown' => $bookingPriceBreakdown,
		'checkoutBookingNumberPreview' => $checkoutBookingNumberPreview,
		'bookingNotice' => $bookingNotice,
		'bookingCheckoutPayNowDisabled' => true,
	];
} elseif ($page === 'booking-thank-you') {
	$bookingId = (int) ($_GET['booking_id'] ?? 0);
	$requestedFlowToken = trim((string) ($_GET['flow_token'] ?? ''));
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');
	$bookingFlowLock = isset($_SESSION['booking_flow_lock']) && is_array($_SESSION['booking_flow_lock'])
		? $_SESSION['booking_flow_lock']
		: [];
	$lockedBookingId = (int) ($bookingFlowLock['booking_id'] ?? 0);
	$lockedFlowToken = trim((string) ($bookingFlowLock['token'] ?? ''));

	if (!$isUserSession || $bookingId <= 0) {
		header('Location: index.php', true, 302);
		exit;
	}

	if ($lockedBookingId > 0 && $lockedBookingId !== $bookingId) {
		$thankYouRedirectQuery = [
			'page' => 'booking-thank-you',
			'booking_id' => $lockedBookingId,
		];
		if ($lockedFlowToken !== '') {
			$thankYouRedirectQuery['flow_token'] = $lockedFlowToken;
		}
		header('Location: index.php?' . http_build_query($thankYouRedirectQuery), true, 303);
		exit;
	}

	if (
		$lockedBookingId === $bookingId
		&& $lockedFlowToken !== ''
		&& $requestedFlowToken !== ''
		&& !hash_equals($lockedFlowToken, $requestedFlowToken)
	) {
		header(
			'Location: index.php?page=booking-thank-you&booking_id=' . urlencode((string) $bookingId) . '&flow_token=' . urlencode($lockedFlowToken),
			true,
			303
		);
		exit;
	}

	$bookingReceiptRow = null;
	$bookingReceiptModalData = [];
	try {
		$pdo = db();
		$bookingReceiptRow = $fetchBookingReceiptData($pdo, $bookingId);
		if (!is_array($bookingReceiptRow)) {
			header('Location: index.php?page=user-booking-history', true, 302);
			exit;
		}

		$sessionUserId = (int) ($sessionUser['id'] ?? 0);
		$bookingUserId = (int) ($bookingReceiptRow['user_id'] ?? 0);
		if ($sessionUserId <= 0 || $bookingUserId !== $sessionUserId) {
			header('Location: index.php?page=user-booking-history', true, 302);
			exit;
		}

		if ($lockedFlowToken === '') {
			if ($requestedFlowToken !== '') {
				$lockedFlowToken = $requestedFlowToken;
			} else {
				try {
					$lockedFlowToken = bin2hex(random_bytes(12));
				} catch (Throwable $randomException) {
					$lockedFlowToken = sha1(uniqid((string) $bookingId, true));
				}
			}
		}

		$_SESSION['booking_flow_lock'] = [
			'booking_id' => $bookingId,
			'token' => $lockedFlowToken,
		];

		$pickupDateTime = $parseDateTimeSafe($bookingReceiptRow['pickup_datetime'] ?? null);
		$returnDateTime = $parseDateTimeSafe($bookingReceiptRow['return_datetime'] ?? null);
		$paymentDateTime = $parseDateTimeSafe($bookingReceiptRow['payment_transaction_time'] ?? null);
		if (!($paymentDateTime instanceof DateTimeImmutable)) {
			$paymentDateTime = $parseDateTimeSafe($bookingReceiptRow['updated_at'] ?? null);
		}

		$priceBreakdown = null;
		if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
			$priceBreakdown = $calculateBookingPriceBreakdown(
				(int) ($bookingReceiptRow['price_per_day'] ?? 0),
				$pickupDateTime,
				$returnDateTime
			);
		}

		$paymentStatus = strtolower(trim((string) ($bookingReceiptRow['payment_status'] ?? 'pending')));
		$bookingNumber = trim((string) ($bookingReceiptRow['booking_number'] ?? ''));
		if ($bookingNumber === '') {
			$bookingNumber = '#RX-' . str_pad((string) $bookingId, 4, '0', STR_PAD_LEFT);
		}

		$statusVariant = $paymentStatus === 'paid' ? 'paid' : 'due';
		$statusLabel = $paymentStatus === 'paid' ? 'Paid' : 'Due';
		$statusDateTime = $paymentStatus === 'paid' ? $paymentDateTime : $pickupDateTime;
		$statusLine = $statusLabel . ' ' . $formatBookingTimeline($statusDateTime);

		$bookingReceiptModalData = [
			'booking_id' => $bookingId,
			'booking_number' => $bookingNumber,
			'status_variant' => $statusVariant,
			'status_line' => $statusLine,
			'price_per_day' => (int) (($priceBreakdown['price_per_day'] ?? 0)),
			'price_for_days' => (int) (($priceBreakdown['price_for_days'] ?? 0)),
			'drop_charge' => (int) (($priceBreakdown['drop_charge'] ?? 0)),
			'taxes_and_fees' => (int) (($priceBreakdown['taxes_and_fees'] ?? 0)),
			'total_amount' => (int) ($bookingReceiptRow['total_amount'] ?? ($priceBreakdown['total_amount'] ?? 0)),
			'download_pdf_url' => 'index.php?' . http_build_query([
				'page' => 'booking-receipt-download',
				'booking_id' => $bookingId,
			]),
		];
	} catch (Throwable $exception) {
		error_log('Booking thank-you data failed: ' . $exception->getMessage());
		header('Location: index.php?page=user-booking-history', true, 302);
		exit;
	}

	$title = 'Ridex | Booking Confirmed';
	$view = 'booking/receipt';
	$viewData = [
		'bookingReceiptModalData' => $bookingReceiptModalData,
		'bookingReceiptRow' => $bookingReceiptRow,
		'bookingFlowHomeUrl' => 'index.php',
	];
} elseif ($page === 'booking-receipt-download') {
	$bookingId = (int) ($_GET['booking_id'] ?? 0);
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');

	if ($bookingId <= 0 || (!$isAdminSession && !$isUserSession)) {
		header('Location: index.php', true, 302);
		exit;
	}

	$receiptRow = null;
	try {
		$receiptRow = $fetchBookingReceiptData(db(), $bookingId);
	} catch (Throwable $exception) {
		error_log('Receipt download lookup failed: ' . $exception->getMessage());
	}

	if (!is_array($receiptRow)) {
		header('Location: index.php?page=user-booking-history', true, 302);
		exit;
	}

	if ($isUserSession) {
		$sessionUserId = (int) ($sessionUser['id'] ?? 0);
		if ($sessionUserId <= 0 || (int) ($receiptRow['user_id'] ?? 0) !== $sessionUserId) {
			header('Location: index.php?page=user-booking-history', true, 302);
			exit;
		}
	}

	$pickupDateTime = $parseDateTimeSafe($receiptRow['pickup_datetime'] ?? null);
	$returnDateTime = $parseDateTimeSafe($receiptRow['return_datetime'] ?? null);
	$paymentDateTime = $parseDateTimeSafe($receiptRow['payment_transaction_time'] ?? null);
	if (!($paymentDateTime instanceof DateTimeImmutable)) {
		$paymentDateTime = $parseDateTimeSafe($receiptRow['updated_at'] ?? null);
	}

	$priceBreakdown = null;
	if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
		$priceBreakdown = $calculateBookingPriceBreakdown(
			(int) ($receiptRow['price_per_day'] ?? 0),
			$pickupDateTime,
			$returnDateTime
		);
	}

	$bookingNumber = trim((string) ($receiptRow['booking_number'] ?? ''));
	if ($bookingNumber === '') {
		$bookingNumber = '#RX-' . str_pad((string) $bookingId, 4, '0', STR_PAD_LEFT);
	}

	$paymentStatus = strtolower(trim((string) ($receiptRow['payment_status'] ?? 'pending')));
	$statusLabel = $paymentStatus === 'paid' ? 'Paid' : 'Due';
	$statusDateTime = $paymentStatus === 'paid' ? $paymentDateTime : $pickupDateTime;
	$statusLine = $statusLabel . ' ' . $formatBookingTimeline($statusDateTime);
	$fileSafeBookingNumber = preg_replace('/[^A-Za-z0-9\-]+/', '-', $bookingNumber);
	$fileSafeBookingNumber = is_string($fileSafeBookingNumber) ? trim($fileSafeBookingNumber, '-') : 'ridex-receipt';
	if ($fileSafeBookingNumber === '') {
		$fileSafeBookingNumber = 'ridex-receipt';
	}

	$pricePerDay = (int) (($priceBreakdown['price_per_day'] ?? 0));
	$priceForDays = (int) (($priceBreakdown['price_for_days'] ?? 0));
	$dropCharge = (int) (($priceBreakdown['drop_charge'] ?? 0));
	$taxesAndFees = (int) (($priceBreakdown['taxes_and_fees'] ?? 0));
	$totalAmount = (int) ($receiptRow['total_amount'] ?? ($priceBreakdown['total_amount'] ?? 0));
	$vehicleLabel = trim((string) ($receiptRow['vehicle_full_name'] ?? $receiptRow['vehicle_short_name'] ?? 'Vehicle'));
	if ($vehicleLabel === '') {
		$vehicleLabel = 'Vehicle';
	}

	$pickupLocationLabel = trim((string) ($receiptRow['pickup_location'] ?? 'N/A'));
	$returnLocationLabel = trim((string) ($receiptRow['return_location'] ?? 'N/A'));
	$pickupLine = trim($pickupLocationLabel . ' ' . $formatBookingTimeline($pickupDateTime));
	$returnLine = trim($returnLocationLabel . ' ' . $formatBookingTimeline($returnDateTime));

	$statusColor = $paymentStatus === 'paid'
		? [0.122, 0.608, 0.224]
		: [0.804, 0.196, 0.153];
	$statusBackgroundColor = $paymentStatus === 'paid'
		? [0.898, 0.965, 0.898]
		: [0.993, 0.914, 0.902];

	$escapePdfText = static function (string $text): string {
		return str_replace(
			['\\', '(', ')'],
			['\\\\', '\\(', '\\)'],
			$text
		);
	};

	$formatPdfColor = static function (array $rgb): string {
		$clamp = static function (float $value): float {
			if ($value < 0) {
				return 0.0;
			}
			if ($value > 1) {
				return 1.0;
			}

			return $value;
		};

		$red = $clamp((float) ($rgb[0] ?? 0));
		$green = $clamp((float) ($rgb[1] ?? 0));
		$blue = $clamp((float) ($rgb[2] ?? 0));

		return sprintf('%.3F %.3F %.3F', $red, $green, $blue);
	};

	$drawFillRect = static function (float $x, float $y, float $width, float $height, array $fillColor) use ($formatPdfColor): string {
		return 'q ' . $formatPdfColor($fillColor) . ' rg '
			. sprintf('%.2F %.2F %.2F %.2F', $x, $y, $width, $height)
			. ' re f Q';
	};

	$drawText = static function (
		float $x,
		float $y,
		string $fontAlias,
		int $fontSize,
		string $text,
		array $textColor = [0, 0, 0]
	) use ($escapePdfText, $formatPdfColor): string {
		return 'BT '
			. '/' . $fontAlias . ' ' . max(1, $fontSize) . ' Tf '
			. $formatPdfColor($textColor) . ' rg '
			. '1 0 0 1 ' . sprintf('%.2F %.2F', $x, $y) . ' Tm '
			. '(' . $escapePdfText($text) . ') Tj ET';
	};

	$drawTextRight = static function (
		float $rightX,
		float $y,
		string $fontAlias,
		int $fontSize,
		string $text,
		array $textColor = [0, 0, 0]
	) use ($drawText): string {
		$approxWidth = strlen($text) * max(1, $fontSize) * 0.49;
		$textStartX = max(36.0, $rightX - $approxWidth);

		return $drawText($textStartX, $y, $fontAlias, $fontSize, $text, $textColor);
	};

	$drawLine = static function (
		float $startX,
		float $startY,
		float $endX,
		float $endY,
		array $strokeColor,
		float $lineWidth = 1.0
	) use ($formatPdfColor): string {
		return $formatPdfColor($strokeColor) . ' RG '
			. sprintf('%.2F', max(0.1, $lineWidth)) . ' w '
			. sprintf('%.2F %.2F m %.2F %.2F l S', $startX, $startY, $endX, $endY);
	};

	$pdfCommands = [];
	$pdfCommands[] = $drawFillRect(0, 0, 595, 842, [0.972, 0.972, 0.972]);
	$pdfCommands[] = $drawFillRect(24, 24, 547, 794, [1, 1, 1]);
	$pdfCommands[] = $drawFillRect(24, 724, 547, 94, [0.278, 0.894, 0.216]);

	$pdfCommands[] = $drawText(46, 772, 'F2', 34, 'RIDEX', [1, 1, 1]);
	$pdfCommands[] = $drawText(46, 748, 'F1', 13, 'Car Rental Service', [1, 1, 1]);

	$generatedLine = 'Generated: ' . (new DateTimeImmutable('now'))->format('M d, Y H:i A');
	$pdfCommands[] = $drawTextRight(548, 780, 'F2', 14, 'Booking Number: ' . $bookingNumber, [1, 1, 1]);
	$pdfCommands[] = $drawTextRight(548, 758, 'F1', 11, $generatedLine, [1, 1, 1]);

	$pdfCommands[] = $drawText(46, 690, 'F2', 24, 'Price details', [0.11, 0.11, 0.11]);
	$pdfCommands[] = $drawLine(46, 680, 548, 680, [0.865, 0.865, 0.865], 1);

	$pdfCommands[] = $drawText(46, 652, 'F1', 13, 'Price per day', [0.18, 0.18, 0.18]);
	$pdfCommands[] = $drawTextRight(548, 652, 'F1', 13, '$' . number_format((float) $pricePerDay, 2), [0.18, 0.18, 0.18]);

	$pdfCommands[] = $drawText(46, 624, 'F1', 13, 'Price for period', [0.18, 0.18, 0.18]);
	$pdfCommands[] = $drawTextRight(548, 624, 'F1', 13, '$' . number_format((float) $priceForDays, 2), [0.18, 0.18, 0.18]);

	$pdfCommands[] = $drawText(46, 596, 'F1', 13, 'Drop charge', [0.18, 0.18, 0.18]);
	$pdfCommands[] = $drawTextRight(548, 596, 'F1', 13, '$' . number_format((float) $dropCharge, 2), [0.18, 0.18, 0.18]);

	$pdfCommands[] = $drawText(46, 568, 'F1', 13, 'Taxes & Fees', [0.18, 0.18, 0.18]);
	$pdfCommands[] = $drawTextRight(548, 568, 'F1', 13, '$' . number_format((float) $taxesAndFees, 2), [0.18, 0.18, 0.18]);

	$pdfCommands[] = $drawLine(428, 546, 548, 546, [0.55, 0.55, 0.55], 1);
	$pdfCommands[] = $drawTextRight(548, 522, 'F2', 20, '$' . number_format((float) $totalAmount, 2), [0.07, 0.07, 0.07]);

	$pdfCommands[] = $drawText(46, 472, 'F2', 15, 'Vehicle', [0.45, 0.45, 0.45]);
	$pdfCommands[] = $drawText(150, 472, 'F1', 13, $vehicleLabel, [0.12, 0.12, 0.12]);
	$pdfCommands[] = $drawText(46, 446, 'F2', 15, 'Pickup', [0.45, 0.45, 0.45]);
	$pdfCommands[] = $drawText(150, 446, 'F1', 13, $pickupLine, [0.12, 0.12, 0.12]);
	$pdfCommands[] = $drawText(46, 420, 'F2', 15, 'Return', [0.45, 0.45, 0.45]);
	$pdfCommands[] = $drawText(150, 420, 'F1', 13, $returnLine, [0.12, 0.12, 0.12]);

	$pdfCommands[] = $drawFillRect(46, 372, 502, 34, $statusBackgroundColor);
	$pdfCommands[] = $drawText(60, 384, 'F2', 12, $statusLine, $statusColor);

	$pdfCommands[] = $drawLine(46, 94, 548, 94, [0.88, 0.88, 0.88], 1);
	$pdfCommands[] = $drawText(46, 76, 'F1', 9, 'RIDEX Car Rental Service', [0.52, 0.52, 0.52]);
	$pdfCommands[] = $drawText(46, 62, 'F1', 9, 'www.ridex.com | info@ridex.com', [0.52, 0.52, 0.52]);

	$pdfStream = implode("\n", $pdfCommands) . "\n";

	$pdfObjects = [];
	$pdfObjects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
	$pdfObjects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
	$pdfObjects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";
	$pdfObjects[] = "4 0 obj\n<< /Length " . strlen($pdfStream) . " >>\nstream\n" . $pdfStream . "endstream\nendobj\n";
	$pdfObjects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
	$pdfObjects[] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";

	$pdfDocument = "%PDF-1.4\n";
	$pdfOffsets = [0];
	foreach ($pdfObjects as $pdfObject) {
		$pdfOffsets[] = strlen($pdfDocument);
		$pdfDocument .= $pdfObject;
	}
	$xrefOffset = strlen($pdfDocument);
	$pdfDocument .= 'xref' . "\n";
	$pdfDocument .= '0 ' . (count($pdfObjects) + 1) . "\n";
	$pdfDocument .= "0000000000 65535 f \n";
	for ($xrefIndex = 1; $xrefIndex <= count($pdfObjects); $xrefIndex += 1) {
		$pdfDocument .= sprintf('%010d 00000 n ', $pdfOffsets[$xrefIndex]) . "\n";
	}
	$pdfDocument .= 'trailer' . "\n";
	$pdfDocument .= '<< /Size ' . (count($pdfObjects) + 1) . ' /Root 1 0 R >>' . "\n";
	$pdfDocument .= 'startxref' . "\n";
	$pdfDocument .= $xrefOffset . "\n";
	$pdfDocument .= "%%EOF";

	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . $fileSafeBookingNumber . '.pdf"');
	echo $pdfDocument;
	exit;
} elseif ($page === 'vehicles') {
	$selectedVehicleType = $sanitizeVehicleType($_GET['vehicle_type'] ?? 'cars');
	$pickupLocation = trim((string) ($_GET['pickup-location'] ?? ''));
	$returnLocation = trim((string) ($_GET['return-location'] ?? ''));
	$pickupDate = trim((string) ($_GET['pickup-date'] ?? ''));
	$returnDate = trim((string) ($_GET['return-date'] ?? ''));
	$pickupTime = trim((string) ($_GET['pickup-time'] ?? ''));
	$returnTime = trim((string) ($_GET['return-time'] ?? ''));

	$vehicles = [];

	try {
		$statement = db()->prepare(
			'SELECT
				v.*,
				c.name AS category_name,
				active_booking.status AS active_booking_status,
				active_booking.pickup_datetime AS active_pickup_datetime,
				active_booking.return_datetime AS active_return_datetime,
				upcoming_booking.pickup_datetime AS upcoming_pickup_datetime
			FROM vehicles v
			INNER JOIN categories c ON c.id = v.category_id
			LEFT JOIN bookings active_booking ON active_booking.id = (
				SELECT b1.id
				FROM bookings b1
				WHERE b1.vehicle_id = v.id
					AND b1.status IN ("reserved", "on_trip", "overdue")
				ORDER BY COALESCE(b1.updated_at, b1.created_at) DESC, b1.id DESC
				LIMIT 1
			)
			LEFT JOIN bookings upcoming_booking ON upcoming_booking.id = (
				SELECT b2.id
				FROM bookings b2
				WHERE b2.vehicle_id = v.id
					AND b2.status = "reserved"
					AND b2.pickup_datetime >= CURRENT_TIMESTAMP
				ORDER BY b2.pickup_datetime ASC, b2.id ASC
				LIMIT 1
			)
			WHERE v.vehicle_type = :vehicle_type AND v.deleted_at IS NULL
			ORDER BY v.created_at DESC'
		);
		$statement->execute([
			'vehicle_type' => $selectedVehicleType,
		]);

		$vehiclesRaw = $statement->fetchAll() ?: [];
		$nowDateTime = new DateTimeImmutable('now');
		foreach ($vehiclesRaw as $vehicleRow) {
			$effectiveStatus = $resolveEffectiveVehicleStatus($vehicleRow, $nowDateTime);
			if ($effectiveStatus !== 'available') {
				continue;
			}

			$vehicleRow['status'] = $effectiveStatus;
			$vehicles[] = $vehicleRow;
		}
	} catch (Throwable $exception) {
		error_log('Vehicle listing query failed: ' . $exception->getMessage());
		$vehicles = [];
	}

	$title = 'Ridex | ' . ucfirst($selectedVehicleType) . ' Listings';
	$view = 'vehicle/list';
	$viewData = [
		'vehicles' => $vehicles,
		'selectedVehicleType' => $selectedVehicleType,
		'pickupLocation' => $pickupLocation,
		'returnLocation' => $returnLocation,
		'pickupDate' => $pickupDate,
		'returnDate' => $returnDate,
		'pickupTime' => $pickupTime,
		'returnTime' => $returnTime,
	];
} elseif ($page === 'vehicle-detail') {
	$vehicleId = (int) ($_GET['id'] ?? 0);
	$requestedVehicleType = $sanitizeVehicleType($_GET['vehicle_type'] ?? 'cars');
	$pickupLocation = trim((string) ($_GET['pickup-location'] ?? ''));
	$returnLocation = trim((string) ($_GET['return-location'] ?? ''));
	$pickupDate = trim((string) ($_GET['pickup-date'] ?? ''));
	$returnDate = trim((string) ($_GET['return-date'] ?? ''));
	$pickupTime = trim((string) ($_GET['pickup-time'] ?? ''));
	$returnTime = trim((string) ($_GET['return-time'] ?? ''));

	$vehicle = null;
	$vehicleImages = [];

	if ($vehicleId > 0) {
		try {
			$statement = db()->prepare(
				'SELECT
					v.*,
					c.name AS category_name,
					active_booking.status AS active_booking_status,
					active_booking.pickup_datetime AS active_pickup_datetime,
					active_booking.return_datetime AS active_return_datetime,
					upcoming_booking.pickup_datetime AS upcoming_pickup_datetime
				FROM vehicles v
				INNER JOIN categories c ON c.id = v.category_id
				LEFT JOIN bookings active_booking ON active_booking.id = (
					SELECT b1.id
					FROM bookings b1
					WHERE b1.vehicle_id = v.id
						AND b1.status IN ("reserved", "on_trip", "overdue")
					ORDER BY COALESCE(b1.updated_at, b1.created_at) DESC, b1.id DESC
					LIMIT 1
				)
				LEFT JOIN bookings upcoming_booking ON upcoming_booking.id = (
					SELECT b2.id
					FROM bookings b2
					WHERE b2.vehicle_id = v.id
						AND b2.status = "reserved"
						AND b2.pickup_datetime >= CURRENT_TIMESTAMP
					ORDER BY b2.pickup_datetime ASC, b2.id ASC
					LIMIT 1
				)
				WHERE v.id = :id AND v.deleted_at IS NULL
				LIMIT 1'
			);
			$statement->execute([
				'id' => $vehicleId,
			]);
			$vehicle = $statement->fetch() ?: null;

			if (is_array($vehicle)) {
				$effectiveStatus = $resolveEffectiveVehicleStatus($vehicle, new DateTimeImmutable('now'));
				if ($effectiveStatus !== 'available') {
					$vehicle = null;
				}
			}
		} catch (Throwable $exception) {
			error_log('Vehicle detail query failed: ' . $exception->getMessage());
			$vehicle = null;
		}
	}

	if (is_array($vehicle) && $vehicleId > 0) {
		try {
			$imageStmt = db()->prepare(
				'SELECT image_path
				 FROM vehicle_images
				 WHERE vehicle_id = :vehicle_id
				 ORDER BY is_primary DESC, sort_order ASC, id ASC'
			);
			$imageStmt->execute([
				'vehicle_id' => $vehicleId,
			]);
			$vehicleImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
		} catch (Throwable $exception) {
			error_log('Vehicle detail image query failed: ' . $exception->getMessage());
			$vehicleImages = [];
		}
	}

	if (!is_array($vehicleImages)) {
		$vehicleImages = [];
	}

	$vehicleImages = array_values(array_filter(array_map(static function ($imagePath) {
		$imagePath = trim((string) $imagePath);
		return $imagePath !== '' ? $imagePath : null;
	}, $vehicleImages)));

	if (is_array($vehicle) && empty($vehicleImages)) {
		$primaryImage = trim((string) ($vehicle['image_path'] ?? ''));
		if ($primaryImage !== '') {
			$vehicleImages = [$primaryImage];
		}
	}

	$backVehicleType = $requestedVehicleType;
	if (is_array($vehicle)) {
		$backVehicleType = $sanitizeVehicleType($vehicle['vehicle_type'] ?? $requestedVehicleType);
	}

	$backQuery = [
		'page' => 'vehicles',
		'vehicle_type' => $backVehicleType,
	];

	if ($pickupLocation !== '') {
		$backQuery['pickup-location'] = $pickupLocation;
	}

	if ($returnLocation !== '') {
		$backQuery['return-location'] = $returnLocation;
	}

	if ($pickupDate !== '') {
		$backQuery['pickup-date'] = $pickupDate;
	}

	if ($returnDate !== '') {
		$backQuery['return-date'] = $returnDate;
	}

	if ($pickupTime !== '') {
		$backQuery['pickup-time'] = $pickupTime;
	}

	if ($returnTime !== '') {
		$backQuery['return-time'] = $returnTime;
	}

	$backUrl = 'index.php?' . http_build_query($backQuery);

	$vehicleTitle = 'Vehicle Details';
	if (is_array($vehicle)) {
		$vehicleTitleRaw = trim((string) ($vehicle['full_name'] ?? ''));
		$vehicleTitle = $vehicleTitleRaw !== '' ? $vehicleTitleRaw : trim((string) ($vehicle['short_name'] ?? 'Vehicle Details'));
	}

	$title = 'Ridex | ' . $vehicleTitle;
	$view = 'vehicle/detail';
	$viewData = [
		'vehicle' => $vehicle,
		'vehicleImages' => $vehicleImages,
		'backUrl' => $backUrl,
	];
} elseif (in_array($page, ['terms-conditions', 'privacy-policy', 'deposit-policy', 'damage-management-policy'], true)) {
	$policyPages = [
		'terms-conditions' => [
			'title' => 'Terms & Conditions',
			'view' => 'policy/terms-conditions',
		],
		'privacy-policy' => [
			'title' => 'Privacy Policy',
			'view' => 'policy/privacy-policy',
		],
		'deposit-policy' => [
			'title' => 'Deposit Policy',
			'view' => 'policy/deposit-policy',
		],
		'damage-management-policy' => [
			'title' => 'Damage Management Policy',
			'view' => 'policy/damage-management-policy',
		],
	];

	$policy = $policyPages[$page];
	$title = 'Ridex | ' . $policy['title'];
	$view = $policy['view'];
	$viewData = [];
} elseif ($page === 'user-booking-history') {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isUserSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user');
	$bookingHistoryNotice = trim((string) ($_GET['booking_notice'] ?? ''));

	$allowedBookingHistoryTabs = ['active', 'pending', 'completed', 'cancelled'];
	$selectedBookingHistoryTab = strtolower(trim((string) ($_GET['tab'] ?? 'active')));
	if (!in_array($selectedBookingHistoryTab, $allowedBookingHistoryTabs, true)) {
		$selectedBookingHistoryTab = 'active';
	}

	if (!$isUserSession) {
		$redirectWithUserLoginFlash(
			'Please log in to view your bookings.',
			'',
			false,
			false,
			'index.php?' . http_build_query([
				'page' => 'user-booking-history',
				'tab' => $selectedBookingHistoryTab,
			])
		);
	}

	$sessionUserId = (int) ($sessionUser['id'] ?? 0);
	$bookingHistoryRows = [];
	$bookingHistoryBuckets = [
		'active' => [],
		'pending' => [],
		'completed' => [],
		'cancelled' => [],
	];
	$bookingHistoryEmptyMessages = [
		'active' => 'You have no active bookings',
		'pending' => 'You have no pending bookings',
		'completed' => 'You have no completed bookings',
		'cancelled' => 'You have no cancelled bookings',
	];

	$formatBookingCardDate = static function (?DateTimeImmutable $dateTime): string {
		if (!($dateTime instanceof DateTimeImmutable)) {
			return 'N/A';
		}

		return $dateTime->format('D, d M H:i A');
	};

	$paymentStatusMetaMap = [
		'paid' => [
			'label' => 'Paid',
			'class' => 'paid',
			'icon' => 'check_circle',
		],
		'pending' => [
			'label' => 'Pending',
			'class' => 'pending',
			'icon' => 'hourglass_top',
		],
		'cancelled' => [
			'label' => 'Cancelled',
			'class' => 'cancelled',
			'icon' => 'schedule',
		],
		'unpaid' => [
			'label' => 'Unpaid',
			'class' => 'unpaid',
			'icon' => 'do_not_disturb_on',
		],
		'refunded' => [
			'label' => 'Refunded',
			'class' => 'refunded',
			'icon' => 'sync',
		],
	];

	if ($sessionUserId > 0) {
		try {
			$nowDateTime = new DateTimeImmutable('now');
			$bookingHistoryRowsRaw = ridex_booking_fetch_user_history_rows(db(), $sessionUserId, 150);

			foreach ($bookingHistoryRowsRaw as $bookingHistoryRow) {
				$bookingId = (int) ($bookingHistoryRow['id'] ?? 0);
				if ($bookingId <= 0) {
					continue;
				}

				$bookingStatusRaw = strtolower(trim((string) ($bookingHistoryRow['status'] ?? 'reserved')));
				$pickupDateTime = $parseDateTimeSafe($bookingHistoryRow['pickup_datetime'] ?? null);
				$returnDateTime = $parseDateTimeSafe($bookingHistoryRow['return_datetime'] ?? null);

				$effectiveBookingStatus = $bookingStatusRaw;
				if (!in_array($bookingStatusRaw, ['completed', 'cancelled'], true)) {
					if ($returnDateTime instanceof DateTimeImmutable && $nowDateTime > $returnDateTime) {
						$effectiveBookingStatus = 'overdue';
					} elseif ($bookingStatusRaw === 'reserved' && $pickupDateTime instanceof DateTimeImmutable && $nowDateTime >= $pickupDateTime) {
						$effectiveBookingStatus = 'on_trip';
					}
				}

				$tabKey = 'pending';
				if ($effectiveBookingStatus === 'cancelled') {
					$tabKey = 'cancelled';
				} elseif ($effectiveBookingStatus === 'completed' || trim((string) ($bookingHistoryRow['return_time'] ?? '')) !== '') {
					$tabKey = 'completed';
				} elseif (in_array($effectiveBookingStatus, ['on_trip', 'overdue'], true)) {
					$tabKey = 'active';
				}

				if ($tabKey === 'active' && !empty($bookingHistoryBuckets['active'])) {
					continue;
				}

				$bookingNumber = trim((string) ($bookingHistoryRow['booking_number'] ?? ''));
				if ($bookingNumber === '') {
					$bookingNumber = '#RX-' . str_pad((string) $bookingId, 4, '0', STR_PAD_LEFT);
				}

				$vehicleType = $sanitizeVehicleType($bookingHistoryRow['vehicle_type'] ?? 'cars');
				$vehicleCategory = ucfirst(rtrim($vehicleType, 's'));
				if ($vehicleCategory === '') {
					$vehicleCategory = 'Car';
				}

				$vehicleName = trim((string) ($bookingHistoryRow['vehicle_full_name'] ?? ''));
				if ($vehicleName === '') {
					$vehicleName = trim((string) ($bookingHistoryRow['vehicle_short_name'] ?? 'Vehicle'));
				}
				if ($vehicleName === '') {
					$vehicleName = 'Vehicle';
				}

				$vehicleImagePath = trim((string) ($bookingHistoryRow['vehicle_image'] ?? ''));
				if ($vehicleImagePath === '') {
					$vehicleImagePath = 'images/vehicle-feature.png';
				}

				$pricePerDay = (int) ($bookingHistoryRow['price_per_day'] ?? 0);
				$priceBreakdown = null;
				if ($pickupDateTime instanceof DateTimeImmutable && $returnDateTime instanceof DateTimeImmutable) {
					$priceBreakdown = $calculateBookingPriceBreakdown($pricePerDay, $pickupDateTime, $returnDateTime);
				}

				$totalDays = (int) ($priceBreakdown['total_days'] ?? 1);
				if ($totalDays <= 0) {
					$totalDays = 1;
				}

				$priceForDays = (int) ($priceBreakdown['price_for_days'] ?? max(0, $pricePerDay * $totalDays));
				$dropCharge = (int) ($priceBreakdown['drop_charge'] ?? 20);
				$totalAmount = (int) ($bookingHistoryRow['total_amount'] ?? ($priceBreakdown['total_amount'] ?? 0));
				$taxesAndFees = (int) ($priceBreakdown['taxes_and_fees'] ?? max(0, $totalAmount - ($priceForDays + $dropCharge)));

				$paymentStatusRaw = strtolower(trim((string) ($bookingHistoryRow['payment_status'] ?? 'pending')));
				$paymentMethod = strtolower(trim((string) ($bookingHistoryRow['payment_method'] ?? '')));
				if (!isset($paymentStatusMetaMap[$paymentStatusRaw])) {
					if ($paymentMethod === 'khalti') {
						$paymentStatusRaw = 'paid';
					} elseif ($paymentMethod === 'pay_on_arrival') {
						$paymentStatusRaw = 'pending';
					} else {
						$paymentStatusRaw = 'unpaid';
					}
				}

				$paymentStatusMeta = $paymentStatusMetaMap[$paymentStatusRaw] ?? [
					'label' => 'Unknown',
					'class' => 'unknown',
					'icon' => 'help',
				];

				$statusDateTime = $parseDateTimeSafe($bookingHistoryRow['payment_transaction_time'] ?? null);
				if (!($statusDateTime instanceof DateTimeImmutable)) {
					$statusDateTime = $parseDateTimeSafe($bookingHistoryRow['updated_at'] ?? null);
				}
				if (in_array($paymentStatusRaw, ['pending', 'unpaid'], true)) {
					$statusDateTime = $pickupDateTime;
				}

				$statusLine = $paymentStatusMeta['label'] . ' ' . $formatBookingTimeline($statusDateTime);
				$mileageRaw = trim((string) ($bookingHistoryRow['mileage_km_per_l'] ?? ''));
				$mileageValue = is_numeric($mileageRaw) ? (float) $mileageRaw : 0.0;
				$mileageLabel = $mileageValue > 0
					? number_format($mileageValue, 2, '.', '') . ' km/L'
					: 'Mileage N/A';

				$bookingHistoryBuckets[$tabKey][] = [
					'id' => $bookingId,
					'booking_number' => $bookingNumber,
					'status_tab' => $tabKey,
					'payment_status_raw' => $paymentStatusRaw,
					'payment_status_label' => $paymentStatusMeta['label'],
					'payment_status_class' => $paymentStatusMeta['class'],
					'payment_status_icon' => $paymentStatusMeta['icon'],
					'status_line' => $statusLine,
					'vehicle_category' => $vehicleCategory,
					'vehicle_name' => $vehicleName,
					'vehicle_image' => $vehicleImagePath,
					'vehicle_seats' => (int) ($bookingHistoryRow['number_of_seats'] ?? 0),
					'vehicle_transmission' => ucfirst(strtolower(trim((string) ($bookingHistoryRow['transmission_type'] ?? 'N/A')))),
					'vehicle_age' => (int) ($bookingHistoryRow['driver_age_requirement'] ?? 0),
					'vehicle_fuel' => ucfirst(strtolower(trim((string) ($bookingHistoryRow['fuel_type'] ?? 'N/A')))),
					'vehicle_mileage' => $mileageLabel,
					'vehicle_plate' => trim((string) ($bookingHistoryRow['license_plate'] ?? 'N/A')),
					'pickup_location' => trim((string) ($bookingHistoryRow['pickup_location'] ?? 'N/A')),
					'return_location' => trim((string) ($bookingHistoryRow['return_location'] ?? 'N/A')),
					'pickup_datetime_label' => $formatBookingCardDate($pickupDateTime),
					'return_datetime_label' => $formatBookingCardDate($returnDateTime),
					'total_days' => $totalDays,
					'price_per_day' => $pricePerDay,
					'price_for_days' => $priceForDays,
					'drop_charge' => $dropCharge,
					'taxes_and_fees' => $taxesAndFees,
					'total_amount' => $totalAmount,
					'download_url' => 'index.php?' . http_build_query([
						'page' => 'booking-receipt-download',
						'booking_id' => $bookingId,
					]),
					'can_cancel' => $tabKey === 'pending',
				];
			}
		} catch (Throwable $exception) {
			error_log('User booking history query failed: ' . $exception->getMessage());
			$bookingHistoryBuckets = [
				'active' => [],
				'pending' => [],
				'completed' => [],
				'cancelled' => [],
			];
		}
	}

	$bookingHistoryRows = $bookingHistoryBuckets[$selectedBookingHistoryTab] ?? [];

	$title = 'Ridex | My Bookings';
	$view = 'booking/history';
	$viewData = [
		'bookingHistoryRows' => $bookingHistoryRows,
		'bookingHistoryBuckets' => $bookingHistoryBuckets,
		'bookingHistorySelectedTab' => $selectedBookingHistoryTab,
		'bookingHistoryNotice' => $bookingHistoryNotice,
		'bookingHistoryEmptyMessages' => $bookingHistoryEmptyMessages,
		'bookingHistoryUserName' => trim((string) ($sessionUser['name'] ?? 'Ridex User')),
	];
} elseif ($page === 'admin-manage-fleet') {
	// manage fleet: admin fleet page with DB-driven type/status filtering.
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$allowedFleetModes = ['type', 'status'];
	$allowedFleetTypes = ['cars', 'bikes', 'luxury'];
	$allowedFleetStatuses = ['reserved', 'on_trip', 'overdue', 'maintenance'];
	$openReadVehicleId = (int) ($_GET['open_read_vehicle_id'] ?? 0);

	$fleetMode = strtolower(trim((string) ($_GET['fleet_mode'] ?? 'type')));
	if (!in_array($fleetMode, $allowedFleetModes, true)) {
		$fleetMode = 'type';
	}

	$selectedFleetType = strtolower(trim((string) ($_GET['fleet_type'] ?? 'cars')));
	if (!in_array($selectedFleetType, $allowedFleetTypes, true)) {
		$selectedFleetType = 'cars';
	}

	$selectedFleetStatus = strtolower(trim((string) ($_GET['fleet_status'] ?? 'reserved')));
	if (!in_array($selectedFleetStatus, $allowedFleetStatuses, true)) {
		$selectedFleetStatus = 'reserved';
	}

	$fleetVehicles = [];
	try {
		$pdo = db();
		$ensureMaintenanceRecordsTable($pdo);

		$fleetBaseSelectSql = "
			SELECT
				v.id,
				v.short_name,
				v.full_name,
				COALESCE(
					(
						SELECT vi.image_path
						FROM vehicle_images vi
						WHERE vi.vehicle_id = v.id
						ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC
						LIMIT 1
					),
					v.image_path
				) AS image_path,
				v.price_per_day,
				v.status,
				v.vehicle_type,
				v.number_of_seats,
				v.transmission_type,
				v.fuel_type,
				v.mileage_km_per_l,
				v.driver_age_requirement,
				v.license_plate,
				v.gps_id,
				v.last_service_date,
				v.description,
				latest_booking.status AS active_booking_status,
				latest_booking.id AS active_booking_id,
				latest_booking.booking_number AS active_booking_number,
				latest_booking.pickup_datetime AS active_pickup_datetime,
				latest_booking.return_datetime AS active_return_datetime,
				latest_booking.payment_status AS active_payment_status,
				latest_booking.late_fee AS active_late_fee,
				booking_user.name AS active_booking_user_name,
				booking_user.phone AS active_booking_user_phone,
				latest_gps.latitude AS gps_latitude,
				latest_gps.longitude AS gps_longitude,
				upcoming_booking.pickup_datetime AS upcoming_pickup_datetime,
				COALESCE(vehicle_stats.total_reservations, 0) AS total_reservations,
				COALESCE(vehicle_stats.total_earnings, 0) AS total_earnings,
				maintenance_open.issue_description AS maintenance_issue_description,
				maintenance_open.workshop_name AS maintenance_workshop_name,
				maintenance_open.estimate_completion_date AS maintenance_estimate_completion_date,
				maintenance_open.service_cost AS maintenance_service_cost
			FROM vehicles v
			LEFT JOIN bookings latest_booking ON latest_booking.id = (
				SELECT b1.id
				FROM bookings b1
				WHERE b1.vehicle_id = v.id
					AND b1.status IN ('reserved', 'on_trip', 'overdue')
				ORDER BY COALESCE(b1.updated_at, b1.created_at) DESC, b1.id DESC
				LIMIT 1
			)
			LEFT JOIN users booking_user ON booking_user.id = latest_booking.user_id
			LEFT JOIN gps_logs latest_gps ON latest_gps.id = (
				SELECT g1.id
				FROM gps_logs g1
				WHERE g1.vehicle_id = v.id
				ORDER BY COALESCE(g1.timestamp, g1.created_at) DESC, g1.id DESC
				LIMIT 1
			)
			LEFT JOIN bookings upcoming_booking ON upcoming_booking.id = (
				SELECT b2.id
				FROM bookings b2
				WHERE b2.vehicle_id = v.id
					AND b2.status IN ('reserved')
					AND b2.pickup_datetime >= CURRENT_TIMESTAMP
				ORDER BY b2.pickup_datetime ASC, b2.id ASC
				LIMIT 1
			)
			LEFT JOIN (
				SELECT
					vehicle_id,
					COUNT(*) AS total_reservations,
					COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN paid_amount ELSE 0 END), 0) AS total_earnings
				FROM bookings
				GROUP BY vehicle_id
			) vehicle_stats ON vehicle_stats.vehicle_id = v.id
			LEFT JOIN vehicle_maintenance_records maintenance_open ON maintenance_open.id = (
				SELECT vmr1.id
				FROM vehicle_maintenance_records vmr1
				WHERE vmr1.vehicle_id = v.id
				ORDER BY
					CASE WHEN vmr1.status = 'open' THEN 0 ELSE 1 END ASC,
					COALESCE(vmr1.updated_at, vmr1.created_at) DESC,
					vmr1.id DESC
				LIMIT 1
			)
		";

		if ($fleetMode === 'status') {
			$fleetStmt = $pdo->query(
				$fleetBaseSelectSql . '
				WHERE v.deleted_at IS NULL
				ORDER BY v.id DESC'
			);
		} else {
			$fleetStmt = $pdo->prepare(
				$fleetBaseSelectSql . '
				WHERE v.vehicle_type = :vehicle_type AND v.deleted_at IS NULL
				ORDER BY v.id DESC'
			);
			$fleetStmt->execute([
				'vehicle_type' => $selectedFleetType,
			]);
		}

		$fleetVehiclesRaw = $fleetStmt->fetchAll() ?: [];
		$nowDateTime = new DateTimeImmutable('now');
		$fleetVehicles = [];

		foreach ($fleetVehiclesRaw as $fleetVehicle) {
			$effectiveStatus = $resolveEffectiveVehicleStatus($fleetVehicle, $nowDateTime);
			$fleetVehicle['status'] = $effectiveStatus;

			if ($fleetMode === 'status' && $effectiveStatus !== $selectedFleetStatus) {
				continue;
			}

			if ($fleetMode !== 'status' && $effectiveStatus !== 'available') {
				continue;
			}

			$fleetVehicles[] = $fleetVehicle;
		}
	} catch (Throwable $exception) {
		error_log('Admin manage fleet data failed: ' . $exception->getMessage());
		$fleetVehicles = [];
	}

	$title = 'Ridex | Manage Fleet';
	$view = 'admin/vehicles/list';
	$viewData = [
		'adminUserName' => (string) ($sessionUser['name'] ?? 'Admin'),
		'fleetMode' => $fleetMode,
		'selectedFleetType' => $selectedFleetType,
		'selectedFleetStatus' => $selectedFleetStatus,
		'openReadVehicleId' => $openReadVehicleId,
		'fleetVehicles' => $fleetVehicles,
	];
} elseif ($page === 'admin-all-bookings') {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$openBookingId = (int) ($_GET['open_booking_id'] ?? 0);

	$adminBookings = [];
	try {
		$bookingStmt = db()->query(
			'SELECT
				b.id,
				b.booking_number,
				b.vehicle_id,
				CASE
					WHEN b.status IN ("completed", "cancelled") THEN b.status
					WHEN b.return_time IS NOT NULL THEN "completed"
					WHEN CURRENT_TIMESTAMP > b.return_datetime THEN "overdue"
					WHEN CURRENT_TIMESTAMP >= b.pickup_datetime THEN "on_trip"
					ELSE "reserved"
				END AS booking_status,
				b.pickup_location,
				b.return_location,
				b.pickup_datetime,
				b.return_datetime,
				b.return_time,
				b.payment_status,
				b.payment_method,
				b.total_amount,
				b.paid_amount,
				b.late_fee,
				b.drivers_id,
				u.name AS customer_name,
				u.phone AS customer_phone,
				u.email AS customer_email,
				v.short_name AS vehicle_short_name,
				v.full_name AS vehicle_full_name,
				v.vehicle_type,
				v.gps_id AS vehicle_gps_id,
				COALESCE(
					(
						SELECT vi.image_path
						FROM vehicle_images vi
						WHERE vi.vehicle_id = v.id
						ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC
						LIMIT 1
					),
					v.image_path
				) AS vehicle_image,
				v.price_per_day,
				v.status AS vehicle_current_status,
				v.deleted_at AS vehicle_deleted_at,
				CASE WHEN v.id IS NULL OR v.deleted_at IS NOT NULL THEN 1 ELSE 0 END AS vehicle_is_unavailable,
				latest_vehicle_gps.latitude AS gps_latitude,
				latest_vehicle_gps.longitude AS gps_longitude,
				latest_vehicle_gps.timestamp AS gps_timestamp,
				active_vehicle_booking.status AS vehicle_active_booking_status,
				active_vehicle_booking.pickup_datetime AS vehicle_active_pickup_datetime,
				active_vehicle_booking.return_datetime AS vehicle_active_return_datetime,
				upcoming_vehicle_booking.pickup_datetime AS vehicle_upcoming_pickup_datetime
			 FROM (
				SELECT
					source_booking.*,
					CASE
						WHEN source_booking.return_time IS NULL
							AND source_booking.status NOT IN ("completed", "cancelled")
							AND CURRENT_TIMESTAMP >= source_booking.pickup_datetime
							AND source_booking.user_id IS NOT NULL
							AND source_booking.user_id > 0
							AND EXISTS (
								SELECT 1
								FROM bookings prior_active
								WHERE prior_active.user_id = source_booking.user_id
									AND prior_active.return_time IS NULL
									AND prior_active.status NOT IN ("completed", "cancelled")
									AND CURRENT_TIMESTAMP >= prior_active.pickup_datetime
									AND (
										prior_active.pickup_datetime < source_booking.pickup_datetime
										OR (
											prior_active.pickup_datetime = source_booking.pickup_datetime
											AND prior_active.id < source_booking.id
										)
									)
							)
						THEN COALESCE(
							(
								SELECT replacement_user.id
								FROM users replacement_user
								WHERE replacement_user.id <> source_booking.user_id
									AND replacement_user.role = "user"
									AND replacement_user.id NOT IN (
										SELECT DISTINCT active_user_booking.user_id
										FROM bookings active_user_booking
										WHERE active_user_booking.return_time IS NULL
											AND active_user_booking.status NOT IN ("completed", "cancelled")
											AND CURRENT_TIMESTAMP >= active_user_booking.pickup_datetime
											AND active_user_booking.user_id IS NOT NULL
											AND active_user_booking.user_id > 0
									)
								ORDER BY replacement_user.id DESC
								LIMIT 1
							),
							(
								SELECT fallback_user.id
								FROM users fallback_user
								WHERE fallback_user.role = "user"
								ORDER BY fallback_user.id DESC
								LIMIT 1
							),
							source_booking.user_id
						)
						ELSE source_booking.user_id
					END AS effective_user_id
				FROM bookings source_booking
			 ) b
			 LEFT JOIN vehicles v ON v.id = b.vehicle_id
			 LEFT JOIN users u ON u.id = b.effective_user_id
			 LEFT JOIN gps_logs latest_vehicle_gps ON latest_vehicle_gps.id = (
				SELECT g1.id
				FROM gps_logs g1
				WHERE g1.vehicle_id = b.vehicle_id
				ORDER BY COALESCE(g1.timestamp, g1.created_at) DESC, g1.id DESC
				LIMIT 1
			 )
			 LEFT JOIN bookings active_vehicle_booking ON active_vehicle_booking.id = (
				SELECT b2.id
				FROM bookings b2
				WHERE b2.vehicle_id = b.vehicle_id
					AND b2.status IN (\'reserved\', \'on_trip\', \'overdue\')
				ORDER BY COALESCE(b2.updated_at, b2.created_at) DESC, b2.id DESC
				LIMIT 1
			 )
			 LEFT JOIN bookings upcoming_vehicle_booking ON upcoming_vehicle_booking.id = (
				SELECT b3.id
				FROM bookings b3
				WHERE b3.vehicle_id = b.vehicle_id
					AND b3.status IN (\'reserved\')
					AND b3.pickup_datetime >= CURRENT_TIMESTAMP
				ORDER BY b3.pickup_datetime ASC, b3.id ASC
				LIMIT 1
			 )
			 ORDER BY b.pickup_datetime DESC, b.return_datetime DESC, b.id DESC'
		);
		$adminBookings = $bookingStmt->fetchAll() ?: [];

		$nowDateTime = new DateTimeImmutable('now');
		foreach ($adminBookings as &$adminBooking) {
			$adminBooking['vehicle_current_status'] = $resolveEffectiveVehicleStatus($adminBooking, $nowDateTime);
		}
		unset($adminBooking);
	} catch (Throwable $exception) {
		error_log('Admin all bookings query failed: ' . $exception->getMessage());
		$adminBookings = [];
	}

	$title = 'Ridex | All Bookings';
	$view = 'admin/bookings/list';
	$viewData = [
		'adminUserName' => (string) ($sessionUser['name'] ?? 'Admin'),
		'openBookingId' => $openBookingId,
		'adminBookings' => $adminBookings,
	];
} elseif ($page === 'admin-live-tracking') {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$liveTrackingPayload = $getAdminLiveTrackingPayload();

	$title = 'Ridex | Live Tracking';
	$view = 'admin/gps/live';
	$viewData = [
		'adminUserName' => (string) ($sessionUser['name'] ?? 'Admin'),
		'liveTrackingKpis' => is_array($liveTrackingPayload['kpis'] ?? null) ? $liveTrackingPayload['kpis'] : [],
		'liveTrackingMarkers' => is_array($liveTrackingPayload['markers'] ?? null) ? $liveTrackingPayload['markers'] : [],
		'liveTrackingGeneratedAt' => trim((string) ($liveTrackingPayload['generatedAt'] ?? '')),
	];
} elseif ($page === 'admin-dashboard-data') {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	header('Content-Type: application/json; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	header('X-Content-Type-Options: nosniff');

	if (!$isAdminSession) {
		http_response_code(401);
		echo '{"ok":false,"message":"Unauthorized"}';
		exit;
	}

	$dashboardPayload = $getAdminDashboardPayload();
	$responseJson = json_encode(
		[
			'ok' => true,
			'payload' => $dashboardPayload,
		],
		JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
	);

	if (!is_string($responseJson)) {
		http_response_code(500);
		echo '{"ok":false,"message":"Unable to encode dashboard data."}';
		exit;
	}

	echo $responseJson;
	exit;
} elseif ($page === 'admin-dashboard') {
	$sessionUser = $_SESSION['auth_user'] ?? [];
	$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');

	if (!$isAdminSession) {
		header('Location: index.php', true, 302);
		exit;
	}

	$dashboardPayload = $getAdminDashboardPayload();
	$dashboardKpis = is_array($dashboardPayload['kpis'] ?? null) ? $dashboardPayload['kpis'] : [];
	$dashboardCharts = is_array($dashboardPayload['charts'] ?? null) ? $dashboardPayload['charts'] : [];

	$title = 'Ridex | Admin Dashboard';
	$view = 'admin/dashboard';
	$viewData = [
		'adminUserName' => (string) ($sessionUser['name'] ?? 'Admin'),
		'dashboardKpis' => $dashboardKpis,
		'dashboardCharts' => $dashboardCharts,
		'dashboardDataEndpoint' => 'index.php?page=admin-dashboard-data',
		'dashboardRefreshIntervalMs' => 30000,
	];
} else {
	$selectedHomeVehicleType = $sanitizeVehicleType($_GET['featured_type'] ?? 'cars');
	$featuredVehicles = [];

	try {
		$statement = db()->prepare(
			'SELECT
				v.*,
				c.name AS category_name,
				active_booking.status AS active_booking_status,
				active_booking.pickup_datetime AS active_pickup_datetime,
				active_booking.return_datetime AS active_return_datetime,
				upcoming_booking.pickup_datetime AS upcoming_pickup_datetime
			FROM vehicles v
			INNER JOIN categories c ON c.id = v.category_id
			LEFT JOIN bookings active_booking ON active_booking.id = (
				SELECT b1.id
				FROM bookings b1
				WHERE b1.vehicle_id = v.id
					AND b1.status IN ("reserved", "on_trip", "overdue")
				ORDER BY COALESCE(b1.updated_at, b1.created_at) DESC, b1.id DESC
				LIMIT 1
			)
			LEFT JOIN bookings upcoming_booking ON upcoming_booking.id = (
				SELECT b2.id
				FROM bookings b2
				WHERE b2.vehicle_id = v.id
					AND b2.status = "reserved"
					AND b2.pickup_datetime >= CURRENT_TIMESTAMP
				ORDER BY b2.pickup_datetime ASC, b2.id ASC
				LIMIT 1
			)
			WHERE v.vehicle_type = :vehicle_type AND v.deleted_at IS NULL
			ORDER BY v.created_at DESC
			LIMIT 24'
		);
		$statement->execute([
			'vehicle_type' => $selectedHomeVehicleType,
		]);

		$featuredVehiclesRaw = $statement->fetchAll() ?: [];
		$nowDateTime = new DateTimeImmutable('now');
		foreach ($featuredVehiclesRaw as $vehicleRow) {
			$effectiveStatus = $resolveEffectiveVehicleStatus($vehicleRow, $nowDateTime);
			if ($effectiveStatus !== 'available') {
				continue;
			}

			$vehicleRow['status'] = $effectiveStatus;
			$featuredVehicles[] = $vehicleRow;
			if (count($featuredVehicles) >= 3) {
				break;
			}
		}
	} catch (Throwable $exception) {
		error_log('Featured vehicle query failed: ' . $exception->getMessage());
		$featuredVehicles = [];
	}

	$title = 'Ridex | Drive Your Way';
	$view = 'home/index';
	$viewData = [
		'featuredVehicles' => $featuredVehicles,
		'selectedHomeVehicleType' => $selectedHomeVehicleType,
	];
}

require __DIR__ . '/../src/Templates/layout.php';
