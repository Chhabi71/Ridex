<?php
/**
 * Purpose: Ridex chatbot helper for local vehicle intents and optional Groq replies.
 * Website Section: Customer chatbot / Recommendation assistant.
 * Developer Notes: Groq is used only for natural language answers. Vehicle recommendations are generated locally.
 */

if (!function_exists('ridex_chatbot_clean_text')) {
	function ridex_chatbot_clean_text($message): string
	{
		$message = strtolower(trim((string) $message));
		$message = preg_replace('/\s+/', ' ', $message) ?: '';
		return $message;
	}
}


if (!function_exists('ridex_chatbot_is_general_information_request')) {
	/**
	 * Detects questions that should be answered by Groq/fallback, not by vehicle recommendation.
	 * Example: "Give me 3 safety tips before renting a car" contains the word car,
	 * but it is asking for advice, so it must not return vehicle cards.
	 */
	function ridex_chatbot_is_general_information_request(string $message): bool
	{
		$text = ridex_chatbot_clean_text($message);

		$generalPatterns = [
			'/\btip\b|\btips\b|\bsafety\b|\badvice\b|\bguide\b|\bexplain\b|\bmeaning\b/',
			'/\bwhat is\b|\bwhat are\b|\bhow does\b|\bhow do\b|\bhow to\b|\bwhy\b/',
			'/\bpolicy\b|\bpolicies\b|\brule\b|\brules\b|\bterms\b|\bcondition\b|\bconditions\b/',
			'/\binsurance\b|\blicense\b|\blicence\b|\bdocument\b|\bdocuments\b|\bdeposit\b|\brefund\b|\bdamage\b/',
			'/\bpassword\b|\baccount\b|\blogin\b|\bsign up\b|\bregister\b|\bverification\b/',
		];

		foreach ($generalPatterns as $pattern) {
			if (preg_match($pattern, $text)) {
				return true;
			}
		}

		return false;
	}
}


if (!function_exists('ridex_chatbot_is_booking_guidance_request')) {
	/**
	 * Detects booking/trip planning questions that should be answered with Ridex guidance,
	 * not by Groq and not by invented vehicle names.
	 */
	function ridex_chatbot_is_booking_guidance_request(string $message): bool
	{
		$text = ridex_chatbot_clean_text($message);
		$patterns = [
			'/\b(available date|available dates|availability|pickup|pick up|return date|return time|trip planning|plan a trip|checkout|booking flow)\b/',
			'/\bhow\b.*\b(book|booking|rent|reserve|checkout)\b/',
			'/\bwhat\b.*\b(date|time|pickup|return)\b.*\b(book|booking|rent|reserve)\b/',
		];
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $text)) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('ridex_chatbot_is_vehicle_recommendation_request')) {
	/**
	 * Detects clear vehicle-search/recommendation intent.
	 * Important: this should be stricter than checking only for words like "car" or "bike".
	 */
	function ridex_chatbot_is_vehicle_recommendation_request(string $message): bool
	{
		$text = ridex_chatbot_clean_text($message);

		// Single-word / short commands users commonly type in the chat widget.
		$shortCommands = [
			'car', 'cars', 'bike', 'bikes', '2 wheeler', '2-wheeler', 'two wheeler', 'two-wheeler',
			'luxury', 'suv', 'cheap', 'budget', 'affordable', 'electric', 'petrol', 'diesel',
			'automatic', 'manual', 'family', '2 seater', '4 seater', '5 seater', '7 seater'
		];
		if (in_array($text, $shortCommands, true)) {
			return true;
		}

		$vehicleWords = 'car|cars|bike|bikes|vehicle|vehicles|suv|luxury|ride|rides|2[- ]?wheeler|two[- ]?wheeler|motorbike|scooter';

		$recommendationPatterns = [
			'/\b(show|find|list|recommend|suggest)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(' . $vehicleWords . ')\b.*\b(show|find|list|recommend|suggest)\b/',
			'/\b(show|find|list|recommend|suggest)\b.*\b[1-9][0-9]?\s*[- ]?(seater|seat|seats|passenger|passengers|people)\b/',
			'/\b(i want|i need|need|looking for|searching for)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(cheap|budget|affordable|low price|low cost)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(' . $vehicleWords . ')\b.*\b(cheap|budget|affordable|low price|low cost)\b/',
			'/\bavailable\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(' . $vehicleWords . ')\b.*\bavailable\b/',
			'/\b(best|good|suitable|perfect)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(family|kids|children|group|solo)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(' . $vehicleWords . ')\b.*\b(family|kids|children|group|solo)\b/',
			'/\b(electric|ev|petrol|diesel|automatic|manual|hybrid)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(' . $vehicleWords . ')\b.*\b(electric|ev|petrol|diesel|automatic|manual|hybrid)\b/',
			'/\b(under|below|less than|max|maximum|budget|up to)\b\s*(?:rs\.?|npr|\$)?\s*[0-9][0-9,]*/',
			'/\bbook\b.*\b(' . $vehicleWords . ')\b/',
			'/\b[1-9][0-9]?\s*[- ]?(seater|seat|seats|passenger|passengers|people)\b.*\b(' . $vehicleWords . ')\b/',
			'/\b(' . $vehicleWords . ')\b.*\b[1-9][0-9]?\s*[- ]?(seater|seat|seats|passenger|passengers|people)\b/',
		];
		foreach ($recommendationPatterns as $pattern) {
			if (preg_match($pattern, $text)) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('ridex_chatbot_extract_preferences')) {
	/**
	 * Extracts simple user preferences without calling any paid AI service.
	 * This keeps recommendation requests cheap and explainable for viva.
	 */
	function ridex_chatbot_extract_preferences(string $message): array
	{
		$text = ridex_chatbot_clean_text($message);
		$preferences = [
			'is_vehicle_query' => false,
			'vehicle_type' => '',
			'budget_max' => 0,
			'budget_min' => 0,
			'seats_min' => 0,
			'seats_exact' => 0,
			'transmission' => '',
			'fuel_type' => '',
			'category_keyword' => '',
			'purpose' => '',
			'booking_guidance' => false,
		];

		$preferences['booking_guidance'] = ridex_chatbot_is_booking_guidance_request($message);

		$preferences['is_vehicle_query'] = ridex_chatbot_is_vehicle_recommendation_request($message)
			&& !ridex_chatbot_is_general_information_request($message);

		if (preg_match('/\b(bike|bikes|motorbike|scooter|2[- ]?wheeler|two[- ]?wheeler)\b/', $text)) {
			$preferences['vehicle_type'] = 'bikes';
		} elseif (preg_match('/\b(luxury|premium|expensive|vip)\b/', $text)) {
			$preferences['vehicle_type'] = 'luxury';
		} elseif (preg_match('/\b(car|cars|suv|sedan|family)\b/', $text)) {
			$preferences['vehicle_type'] = 'cars';
		}

		if (preg_match('/\b(automatic|auto)\b/', $text)) {
			$preferences['transmission'] = 'automatic';
		} elseif (preg_match('/\bmanual\b/', $text)) {
			$preferences['transmission'] = 'manual';
		} elseif (preg_match('/\bhybrid\b/', $text)) {
			$preferences['transmission'] = 'hybrid';
		}

		if (preg_match('/\b(electric|ev)\b/', $text)) {
			$preferences['fuel_type'] = 'electric';
		} elseif (preg_match('/\b(petrol|gasoline)\b/', $text)) {
			$preferences['fuel_type'] = 'petrol';
		} elseif (preg_match('/\bdiesel\b/', $text)) {
			$preferences['fuel_type'] = 'diesel';
		}

		if (preg_match('/\b(suv|sedan|hatchback|sports|cruiser|commuter)\b/', $text, $categoryMatch)) {
			$preferences['category_keyword'] = $categoryMatch[1];
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
		}

		if (preg_match('/\b(family|children|kids|group)\b/', $text)) {
			$preferences['purpose'] = 'family';
			$preferences['seats_min'] = 5;
			if ($preferences['vehicle_type'] === '') {
				$preferences['vehicle_type'] = 'cars';
			}
		} elseif (preg_match('/\b(solo|alone|single)\b/', $text)) {
			$preferences['purpose'] = 'solo';
			$preferences['seats_min'] = 1;
		}

		// Detect seat requests.
		// "2 seater cars" usually means exactly 2 seats.
		// "for 4 passengers/people" means at least 4 seats because the vehicle must accommodate them.
		if (preg_match('/\b([1-9][0-9]?)\s*[- ]?seater\b/', $text, $seatMatch)) {
			$preferences['seats_exact'] = max(1, (int) $seatMatch[1]);
			$preferences['seats_min'] = 0;
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
			if ($preferences['vehicle_type'] === '' && $preferences['is_vehicle_query']) {
				$preferences['vehicle_type'] = 'cars';
			}
		} elseif (preg_match('/\b([1-9][0-9]?)\s*[- ]?(?:seat|seats)\b/', $text, $seatMatch)) {
			$preferences['seats_exact'] = max(1, (int) $seatMatch[1]);
			$preferences['seats_min'] = 0;
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
			if ($preferences['vehicle_type'] === '' && $preferences['is_vehicle_query']) {
				$preferences['vehicle_type'] = 'cars';
			}
		} elseif (preg_match('/\b([1-9][0-9]?)\s*[- ]?(?:passenger|passengers|people)\b/', $text, $seatMatch)) {
			$preferences['seats_min'] = max(1, (int) $seatMatch[1]);
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
			if ($preferences['vehicle_type'] === '' && $preferences['is_vehicle_query']) {
				$preferences['vehicle_type'] = 'cars';
			}
		}

		// A 2-wheeler with 3+ seats is not a valid recommendation request in this project,
		// so send it to Groq/fallback for an explanatory answer instead of showing wrong database cars.
		if (preg_match('/\b(2[- ]?wheeler|two[- ]?wheeler|bike|bikes|motorbike|scooter)\b/', $text)
			&& (($preferences['seats_min'] ?? 0) >= 3 || ($preferences['seats_exact'] ?? 0) >= 3)) {
			$preferences['is_vehicle_query'] = false;
		}

		if (preg_match('/\b(cheap|budget|affordable|low price)\b/', $text)) {
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
			// If the user does not give a number, this is used as a soft budget in local scoring.
			$preferences['budget_max'] = 60;
		}

		if (preg_match('/(?:under|below|less than|max|maximum|budget|up to)\s*(?:rs\.?|npr|\$)?\s*([0-9][0-9,]*)/i', $message, $budgetMatch)) {
			$preferences['budget_max'] = (int) str_replace(',', '', $budgetMatch[1]);
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
		}

		if (preg_match('/(?:above|over|more than|min|minimum)\s*(?:rs\.?|npr|\$)?\s*([0-9][0-9,]*)/i', $message, $budgetMinMatch)) {
			$preferences['budget_min'] = (int) str_replace(',', '', $budgetMinMatch[1]);
			if (!ridex_chatbot_is_general_information_request($message)) {
				$preferences['is_vehicle_query'] = true;
			}
		}

		return $preferences;
	}
}

if (!function_exists('ridex_chatbot_fetch_recommendations')) {
	/**
	 * Gets available vehicles from MySQL and ranks them with the existing recommendation system.
	 */
	function ridex_chatbot_fetch_recommendations(PDO $pdo, array $preferences, int $userId = 0, int $limit = 5): array
	{
		$vehicleType = trim((string) ($preferences['vehicle_type'] ?? ''));
		$budgetMin = max(0, (int) ($preferences['budget_min'] ?? 0));
		$budgetMax = max(0, (int) ($preferences['budget_max'] ?? 0));
		$seatsMin = max(0, (int) ($preferences['seats_min'] ?? 0));
		$seatsExact = max(0, (int) ($preferences['seats_exact'] ?? 0));
		$transmission = strtolower(trim((string) ($preferences['transmission'] ?? '')));
		$fuelType = strtolower(trim((string) ($preferences['fuel_type'] ?? '')));
		$categoryKeyword = strtolower(trim((string) ($preferences['category_keyword'] ?? '')));

		$params = [];
		$sql = 'SELECT
				v.*,
				c.name AS category_name,
				COALESCE(vehicle_popularity.booking_count, 0) AS booking_count
			FROM vehicles v
			INNER JOIN categories c ON c.id = v.category_id
			LEFT JOIN (
				SELECT vehicle_id, COUNT(*) AS booking_count
				FROM bookings
				WHERE status IN ("reserved", "on_trip", "overdue", "completed")
				GROUP BY vehicle_id
			) vehicle_popularity ON vehicle_popularity.vehicle_id = v.id
			WHERE v.deleted_at IS NULL
				AND v.status = "available"
				AND NOT EXISTS (
					SELECT 1
					FROM bookings b
					WHERE b.vehicle_id = v.id
						AND b.status IN ("reserved", "on_trip", "overdue")
				)';

		if (in_array($vehicleType, ['cars', 'bikes', 'luxury'], true)) {
			$sql .= ' AND v.vehicle_type = :vehicle_type';
			$params['vehicle_type'] = $vehicleType;
		}

		if ($budgetMin > 0) {
			$sql .= ' AND v.price_per_day >= :budget_min';
			$params['budget_min'] = $budgetMin;
		}

		if ($budgetMax > 0) {
			$sql .= ' AND v.price_per_day <= :budget_max';
			$params['budget_max'] = $budgetMax;
		}

		if ($seatsExact > 0) {
			$sql .= ' AND COALESCE(v.number_of_seats, 0) = :seats_exact';
			$params['seats_exact'] = $seatsExact;
		} elseif ($seatsMin > 0) {
			$sql .= ' AND COALESCE(v.number_of_seats, 0) >= :seats_min';
			$params['seats_min'] = $seatsMin;
		}

		if (in_array($transmission, ['manual', 'automatic', 'hybrid'], true)) {
			$sql .= ' AND LOWER(v.transmission_type) = :transmission';
			$params['transmission'] = $transmission;
		}

		if (in_array($fuelType, ['petrol', 'diesel', 'electric'], true)) {
			$sql .= ' AND LOWER(v.fuel_type) = :fuel_type';
			$params['fuel_type'] = $fuelType;
		}

		if ($categoryKeyword !== '') {
			$sql .= ' AND (LOWER(c.name) LIKE :category_keyword OR LOWER(v.full_name) LIKE :category_keyword OR LOWER(v.description) LIKE :category_keyword)';
			$params['category_keyword'] = '%' . $categoryKeyword . '%';
		}

		$sql .= ' ORDER BY COALESCE(vehicle_popularity.booking_count, 0) DESC, v.price_per_day ASC LIMIT 20';

		try {
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$vehicles = $stmt->fetchAll() ?: [];
		} catch (Throwable $exception) {
			error_log('Chatbot vehicle query failed: ' . $exception->getMessage());
			return [];
		}

		// If the user gave strict filters, do NOT fall back to random vehicles.
		// Example: "recommend 2 seater cars" should not invent/show unrelated cars if no 2-seater exists.
		$hasStrictFilters = $vehicleType !== '' || $budgetMin > 0 || $budgetMax > 0 || $seatsMin > 0
			|| $seatsExact > 0 || $transmission !== '' || $fuelType !== '' || $categoryKeyword !== '';
		if (empty($vehicles) && $hasStrictFilters) {
			return [];
		}

		// If the user only asked generally, safely fall back to available vehicles.
		if (empty($vehicles)) {
			try {
				$fallbackStmt = $pdo->query(
					'SELECT v.*, c.name AS category_name, COALESCE(vehicle_popularity.booking_count, 0) AS booking_count
					 FROM vehicles v
					 INNER JOIN categories c ON c.id = v.category_id
					 LEFT JOIN (
						 SELECT vehicle_id, COUNT(*) AS booking_count
						 FROM bookings
						 WHERE status IN ("reserved", "on_trip", "overdue", "completed")
						 GROUP BY vehicle_id
					 ) vehicle_popularity ON vehicle_popularity.vehicle_id = v.id
					 WHERE v.deleted_at IS NULL AND v.status = "available"
					 ORDER BY COALESCE(vehicle_popularity.booking_count, 0) DESC, v.price_per_day ASC
					 LIMIT 20'
				);
				$vehicles = $fallbackStmt->fetchAll() ?: [];
			} catch (Throwable $exception) {
				error_log('Chatbot fallback vehicle query failed: ' . $exception->getMessage());
				return [];
			}
		}

		$fallbackType = in_array($vehicleType, ['cars', 'bikes', 'luxury'], true) ? $vehicleType : 'cars';
		$selectedTypes = in_array($vehicleType, ['cars', 'bikes', 'luxury'], true) ? [$vehicleType] : ['cars', 'bikes', 'luxury'];
		$profile = ridex_recommendation_get_user_profile($pdo, $userId, $fallbackType, $budgetMin, $budgetMax, $selectedTypes);

		if ($transmission !== '') {
			$profile['preferred_transmission'] = $transmission;
		}
		if ($fuelType !== '') {
			$profile['preferred_fuel'] = $fuelType;
		}

		return ridex_recommendation_rank_vehicles($vehicles, $profile, $limit);
	}
}

if (!function_exists('ridex_chatbot_vehicle_payload')) {
	function ridex_chatbot_vehicle_payload(array $vehicle): array
	{
		$vehicleType = strtolower(trim((string) ($vehicle['vehicle_type'] ?? 'cars')));
		if (!in_array($vehicleType, ['cars', 'bikes', 'luxury'], true)) {
			$vehicleType = 'cars';
		}

		$vehicleId = (int) ($vehicle['id'] ?? 0);
		return [
			'id' => $vehicleId,
			'name' => (string) ($vehicle['short_name'] ?: ($vehicle['full_name'] ?? 'Vehicle')),
			'full_name' => (string) ($vehicle['full_name'] ?? 'Vehicle'),
			'type' => $vehicleType,
			'category' => (string) ($vehicle['category_name'] ?? ''),
			'price_per_day' => (int) ($vehicle['price_per_day'] ?? 0),
			'seats' => (int) ($vehicle['number_of_seats'] ?? 0),
			'transmission' => (string) ($vehicle['transmission_type'] ?? ''),
			'fuel' => (string) ($vehicle['fuel_type'] ?? ''),
			'mileage' => (string) ($vehicle['mileage_km_per_l'] ?? ''),
			'image_path' => (string) (($vehicle['image_path'] ?? '') ?: 'images/vehicle-feature.png'),
			'score' => (int) ($vehicle['recommendation_score'] ?? 0),
			'reason' => (string) ($vehicle['recommendation_reason'] ?? ''),
			'detail_url' => 'index.php?' . http_build_query([
				'page' => 'vehicle-detail',
				'vehicle_type' => $vehicleType,
				'id' => $vehicleId,
			]),
		];
	}
}

if (!function_exists('ridex_chatbot_format_vehicle_reply')) {
	function ridex_chatbot_format_vehicle_reply(array $vehicles, array $preferences): string
	{
		if (empty($vehicles)) {
			if (!empty($preferences['seats_exact'])) {
				$typeText = !empty($preferences['vehicle_type']) ? (string) $preferences['vehicle_type'] : 'vehicles';
				return 'Sorry, I could not find any available ' . $typeText . ' with exactly ' . (int) $preferences['seats_exact'] . ' seats in the Ridex database. Please try a different seat count or vehicle type.';
			}

			return 'Sorry, I could not find any available vehicles in the Ridex database that match those requirements. Please try a different seat count, budget, or vehicle type.';
		}

		$parts = [];
		if (!empty($preferences['vehicle_type'])) {
			$parts[] = $preferences['vehicle_type'];
		}
		if (!empty($preferences['budget_max'])) {
			$parts[] = 'under $' . (int) $preferences['budget_max'] . ' per day';
		}
		if (!empty($preferences['seats_exact'])) {
			$parts[] = 'exactly ' . (int) $preferences['seats_exact'] . ' seats';
		} elseif (!empty($preferences['seats_min'])) {
			$parts[] = (int) $preferences['seats_min'] . '+ seats';
		}
		if (!empty($preferences['purpose'])) {
			$parts[] = 'for ' . $preferences['purpose'];
		}

		$intro = empty($parts)
			? 'Recommended vehicles for you:'
			: 'Recommended vehicles for ' . implode(', ', $parts) . ':';

		return $intro . ' These are ranked using your local Ridex recommendation score, not by Groq.';
	}
}

if (!function_exists('ridex_chatbot_call_groq')) {
	/**
	 * Calls Groq for general text answers. The API key stays on the backend only.
	 */
	function ridex_chatbot_call_groq(string $message): ?string
	{
		$apiKey = trim((string) env('GROQ_API_KEY', ''));
		if ($apiKey === '') {
			return null;
		}

		$model = trim((string) env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
		if ($model === '') {
			$model = 'llama-3.3-70b-versatile';
		}

		$payload = [
			'model' => $model,
			'messages' => [
				[
					'role' => 'system',
					'content' => 'You are the Ridex vehicle rental chatbot. Answer briefly and helpfully. Do not claim to book vehicles directly. Never recommend or name specific vehicle models unless they are supplied by Ridex database data. If the user asks to show, find, list, suggest, or recommend vehicles, say that Ridex will use its local database recommendation system instead of making up vehicle names.',
				],
				[
					'role' => 'user',
					'content' => $message,
				],
			],
			'temperature' => 0.3,
			'max_tokens' => 180,
		];

		$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
		if ($ch === false) {
			return null;
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $apiKey,
			],
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_CONNECTTIMEOUT => 8,
			CURLOPT_TIMEOUT => 18,
		]);

		$response = curl_exec($ch);
		$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($response === false || $statusCode < 200 || $statusCode >= 300) {
			error_log('Groq chatbot call failed. Status: ' . $statusCode . ' Error: ' . $curlError . ' Response: ' . (string) $response);
			return null;
		}

		$data = json_decode((string) $response, true);
		$content = $data['choices'][0]['message']['content'] ?? null;
		if (!is_string($content) || trim($content) === '') {
			return null;
		}

		return trim($content);
	}
}


if (!function_exists('ridex_chatbot_has_date_range')) {
	function ridex_chatbot_has_date_range(string $message): bool
	{
		$text = ridex_chatbot_clean_text($message);
		$datePattern = '/\b(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}|\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})\b/';
		return preg_match_all($datePattern, $text) >= 2;
	}
}

if (!function_exists('ridex_chatbot_availability_reply')) {
	function ridex_chatbot_availability_reply(string $message): string
	{
		if (!ridex_chatbot_has_date_range($message)) {
			return 'I can help you check availability, but I need a pickup date, return date, and vehicle type. Example: “Show available cars from 20/05/2026 to 23/05/2026.”';
		}

		return 'Thanks. To check exact availability, please use the booking form with your pickup date, return date, pickup location, and return location. The system will then show vehicles that are available for that selected period.';
	}
}

if (!function_exists('ridex_chatbot_booking_guidance_reply')) {
	function ridex_chatbot_booking_guidance_reply(): string
	{
		return 'For booking, choose a vehicle, select pickup and return location, choose pickup and return date/time, then continue to the booking engine. I can also suggest vehicles from the Ridex database if you ask things like “recommend a cheap car”, “show me 5 seat cars”, or “show me electric cars”.';
	}
}

if (!function_exists('ridex_chatbot_fallback_reply')) {
	function ridex_chatbot_fallback_reply(string $message): string
	{
		$text = ridex_chatbot_clean_text($message);
		if (str_contains($text, 'hello') || str_contains($text, 'hi')) {
			return 'Hi! I can help you find cars, bikes, luxury vehicles, cheap options, electric vehicles, or family-friendly rides.';
		}

		if (str_contains($text, 'book')) {
			return 'To book a vehicle, open the vehicle details or use the Book Now button. I can also recommend vehicles if you tell me your budget or vehicle type.';
		}

		return 'I can help with Ridex vehicle recommendations. Try asking: “I want a cheap car”, “show me electric cars”, or “best car for family”.';
	}
}
