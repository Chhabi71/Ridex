<?php
/**
 * Purpose: AJAX endpoint for Ridex chatbot messages.
 * Website Section: Customer chatbot.
 * Developer Notes: Keeps Groq API key on backend and returns JSON to the frontend widget.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Helpers/auth.php';
require_once __DIR__ . '/../../src/Helpers/recommendations.php';
require_once __DIR__ . '/../../src/Helpers/chatbot.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode([
		'ok' => false,
		'reply' => 'Invalid request method.',
	]);
	exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
	$payload = $_POST;
}

$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
	echo json_encode([
		'ok' => false,
		'reply' => 'Please type a message first.',
	]);
	exit;
}

if (mb_strlen($message) > 500) {
	$message = mb_substr($message, 0, 500);
}

$sessionUser = $_SESSION['auth_user'] ?? [];
$userId = is_array($sessionUser) && (($sessionUser['role'] ?? '') === 'user')
	? (int) ($sessionUser['id'] ?? 0)
	: 0;

$preferences = ridex_chatbot_extract_preferences($message);
$vehicles = [];
$vehiclePayloads = [];
$usedGroq = false;

try {
	if (!empty($preferences['is_vehicle_query'])) {
		$vehicles = ridex_chatbot_fetch_recommendations(db(), $preferences, $userId, 5);
		foreach ($vehicles as $vehicle) {
			if (is_array($vehicle)) {
				$vehiclePayloads[] = ridex_chatbot_vehicle_payload($vehicle);
			}
		}

		$reply = ridex_chatbot_format_vehicle_reply($vehicles, $preferences);
	} elseif (!empty($preferences['booking_guidance'])) {
		$cleanMessage = ridex_chatbot_clean_text($message);
		if (preg_match('/\b(available date|available dates|availability|pickup|pick up|return date|return time)\b/', $cleanMessage)) {
			$reply = ridex_chatbot_availability_reply($message);
		} else {
			$reply = ridex_chatbot_booking_guidance_reply();
		}
	} else {
		$groqReply = ridex_chatbot_call_groq($message);
		if (is_string($groqReply) && $groqReply !== '') {
			$reply = $groqReply;
			$usedGroq = true;
		} else {
			$reply = ridex_chatbot_fallback_reply($message);
		}
	}
} catch (Throwable $exception) {
	error_log('Chatbot endpoint failed: ' . $exception->getMessage());
	$reply = ridex_chatbot_fallback_reply($message);
}

echo json_encode([
	'ok' => true,
	'reply' => $reply,
	'recommendations' => $vehiclePayloads,
	'preferences' => $preferences,
	'used_groq' => $usedGroq,
]);
