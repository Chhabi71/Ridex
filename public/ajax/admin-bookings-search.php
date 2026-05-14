<?php
/**
 * Purpose: Return matching booking IDs for live admin all-bookings table search.
 * Website Section: Admin Bookings AJAX.
*/

require_once __DIR__ . '/../../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	http_response_code(405);
	echo '{"ok":false,"message":"Method not allowed"}';
	exit;
}

$sessionUser = $_SESSION['auth_user'] ?? [];
$isAdminSession = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'admin');
if (!$isAdminSession) {
	http_response_code(401);
	echo '{"ok":false,"message":"Unauthorized"}';
	exit;
}

$rawQuery = trim((string) ($_GET['q'] ?? ''));
if (strlen($rawQuery) > 120) {
	$rawQuery = substr($rawQuery, 0, 120);
}

try {
	$pdo = db();

	if ($rawQuery === '') {
		$allBookingIds = $pdo->query(
			'SELECT id
			 FROM bookings
			 ORDER BY pickup_datetime DESC, return_datetime DESC, id DESC'
		)->fetchAll(PDO::FETCH_COLUMN) ?: [];

		$responseJson = json_encode([
			'ok' => true,
			'bookingIds' => array_values(array_map('intval', $allBookingIds)),
		], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

		if (!is_string($responseJson)) {
			http_response_code(500);
			echo '{"ok":false,"message":"Unable to encode response."}';
			exit;
		}

		echo $responseJson;
		exit;
	}

	$normalizedQuery = preg_replace('/\s+/', ' ', $rawQuery);
	if (!is_string($normalizedQuery)) {
		$normalizedQuery = $rawQuery;
	}
	$normalizedQuery = trim($normalizedQuery);
	$searchTerm = '%' . $normalizedQuery . '%';

	$searchStmt = $pdo->prepare(
		'SELECT DISTINCT b.id
		 FROM bookings b
		 LEFT JOIN users u ON u.id = b.user_id
		 LEFT JOIN vehicles v ON v.id = b.vehicle_id
		 WHERE
			b.booking_number LIKE :term
			OR CONCAT("#BK-", LPAD(b.id, 4, "0")) LIKE :term
			OR u.name LIKE :term
			OR u.phone LIKE :term
			OR u.email LIKE :term
			OR COALESCE(v.full_name, v.short_name, v.vehicle_type) LIKE :term
			OR b.pickup_location LIKE :term
			OR b.return_location LIKE :term
			OR b.status LIKE :term
			OR b.payment_status LIKE :term
		 ORDER BY b.pickup_datetime DESC, b.return_datetime DESC, b.id DESC
		 LIMIT 400'
	);
	$searchStmt->execute([
		'term' => $searchTerm,
	]);
	$bookingIds = $searchStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

	$responseJson = json_encode([
		'ok' => true,
		'bookingIds' => array_values(array_map('intval', $bookingIds)),
	], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

	if (!is_string($responseJson)) {
		http_response_code(500);
		echo '{"ok":false,"message":"Unable to encode response."}';
		exit;
	}

	echo $responseJson;
} catch (Throwable $exception) {
	http_response_code(500);
	error_log('Admin bookings search failed: ' . $exception->getMessage());
	echo '{"ok":false,"message":"Search request failed."}';
}
