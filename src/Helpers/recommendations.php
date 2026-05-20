<?php
/**
 * Purpose: API-free vehicle recommendation helper for Ridex.
 * Website Section: Vehicle recommendation / Booking selection.
 * Developer Notes: Uses only local MySQL data and simple scoring rules. No paid APIs are used.
 */

if (!function_exists('ridex_recommendation_normalize_vehicle_type')) {
	function ridex_recommendation_normalize_vehicle_type($rawType, string $fallback = 'cars'): string
	{
		$type = strtolower(trim((string) $rawType));
		return in_array($type, ['cars', 'bikes', 'luxury'], true) ? $type : $fallback;
	}
}

if (!function_exists('ridex_recommendation_get_user_profile')) {
	/**
	 * Builds a small preference profile from the current filters and the user's past bookings.
	 * If the user has no booking history, the profile safely falls back to the selected page filters.
	 */
	function ridex_recommendation_get_user_profile(
		PDO $pdo,
		int $userId = 0,
		string $fallbackVehicleType = 'cars',
		int $budgetMin = 0,
		int $budgetMax = 0,
		array $selectedTypes = []
	): array {
		$fallbackVehicleType = ridex_recommendation_normalize_vehicle_type($fallbackVehicleType, 'cars');
		$cleanSelectedTypes = [];
		foreach ($selectedTypes as $selectedType) {
			$cleanSelectedTypes[] = ridex_recommendation_normalize_vehicle_type($selectedType, $fallbackVehicleType);
		}
		$cleanSelectedTypes = array_values(array_unique($cleanSelectedTypes));
		if (empty($cleanSelectedTypes)) {
			$cleanSelectedTypes = [$fallbackVehicleType];
		}

		$profile = [
			'preferred_type' => $fallbackVehicleType,
			'selected_types' => $cleanSelectedTypes,
			'preferred_transmission' => '',
			'preferred_fuel' => '',
			'budget_min' => max(0, $budgetMin),
			'budget_max' => max(0, $budgetMax),
			'has_history' => false,
			'history_count' => 0,
		];

		if ($userId <= 0) {
			return $profile;
		}

		try {
			$historyStmt = $pdo->prepare(
				'SELECT
					v.vehicle_type,
					v.transmission_type,
					v.fuel_type,
					v.price_per_day,
					COUNT(*) AS used_count
				 FROM bookings b
				 INNER JOIN vehicles v ON v.id = b.vehicle_id
				 WHERE b.user_id = :user_id
					AND b.status IN ("reserved", "on_trip", "overdue", "completed")
				 GROUP BY v.vehicle_type, v.transmission_type, v.fuel_type, v.price_per_day
				 ORDER BY used_count DESC, MAX(b.created_at) DESC'
			);
			$historyStmt->execute(['user_id' => $userId]);
			$historyRows = $historyStmt->fetchAll() ?: [];
		} catch (Throwable $exception) {
			error_log('Recommendation profile history query failed: ' . $exception->getMessage());
			return $profile;
		}

		if (empty($historyRows)) {
			return $profile;
		}

		$typeScores = [];
		$transmissionScores = [];
		$fuelScores = [];
		$totalPrice = 0;
		$totalCount = 0;

		foreach ($historyRows as $historyRow) {
			$count = max(1, (int) ($historyRow['used_count'] ?? 1));
			$type = ridex_recommendation_normalize_vehicle_type($historyRow['vehicle_type'] ?? '', $fallbackVehicleType);
			$transmission = strtolower(trim((string) ($historyRow['transmission_type'] ?? '')));
			$fuel = strtolower(trim((string) ($historyRow['fuel_type'] ?? '')));
			$price = max(0, (int) ($historyRow['price_per_day'] ?? 0));

			$typeScores[$type] = ($typeScores[$type] ?? 0) + $count;
			if ($transmission !== '') {
				$transmissionScores[$transmission] = ($transmissionScores[$transmission] ?? 0) + $count;
			}
			if ($fuel !== '') {
				$fuelScores[$fuel] = ($fuelScores[$fuel] ?? 0) + $count;
			}
			$totalPrice += $price * $count;
			$totalCount += $count;
		}

		arsort($typeScores);
		arsort($transmissionScores);
		arsort($fuelScores);

		$averagePrice = $totalCount > 0 ? (int) round($totalPrice / $totalCount) : 0;
		if ($profile['budget_max'] <= 0 && $averagePrice > 0) {
			// A small range around the user's usual rental price makes the recommendation personal.
			$profile['budget_min'] = max(0, (int) floor($averagePrice * 0.75));
			$profile['budget_max'] = (int) ceil($averagePrice * 1.25);
		}

		$profile['preferred_type'] = (string) array_key_first($typeScores);
		$profile['preferred_transmission'] = (string) (array_key_first($transmissionScores) ?? '');
		$profile['preferred_fuel'] = (string) (array_key_first($fuelScores) ?? '');
		$profile['has_history'] = true;
		$profile['history_count'] = $totalCount;

		return $profile;
	}
}

if (!function_exists('ridex_recommendation_score_vehicle')) {
	/**
	 * Calculates one vehicle's recommendation score.
	 * Score idea for viva:
	 * - Type match: max 35
	 * - Budget match: max 25
	 * - Popularity from bookings: max 20
	 * - Similar transmission/fuel: max 10
	 * - Practical mileage bonus: max 5
	 * - Available vehicle bonus: max 5
	 */
	function ridex_recommendation_score_vehicle(array $vehicle, array $profile): array
	{
		$score = 0;
		$reasons = [];

		$vehicleType = ridex_recommendation_normalize_vehicle_type($vehicle['vehicle_type'] ?? '', 'cars');
		$preferredType = ridex_recommendation_normalize_vehicle_type($profile['preferred_type'] ?? '', 'cars');
		$selectedTypes = isset($profile['selected_types']) && is_array($profile['selected_types']) ? $profile['selected_types'] : [$preferredType];
		$price = max(0, (int) ($vehicle['price_per_day'] ?? 0));
		$bookingCount = max(0, (int) ($vehicle['booking_count'] ?? 0));
		$status = strtolower(trim((string) ($vehicle['status'] ?? 'available')));
		$mileage = is_numeric($vehicle['mileage_km_per_l'] ?? null) ? (float) $vehicle['mileage_km_per_l'] : 0.0;
		$budgetMin = max(0, (int) ($profile['budget_min'] ?? 0));
		$budgetMax = max(0, (int) ($profile['budget_max'] ?? 0));
		$preferredTransmission = strtolower(trim((string) ($profile['preferred_transmission'] ?? '')));
		$preferredFuel = strtolower(trim((string) ($profile['preferred_fuel'] ?? '')));
		$vehicleTransmission = strtolower(trim((string) ($vehicle['transmission_type'] ?? '')));
		$vehicleFuel = strtolower(trim((string) ($vehicle['fuel_type'] ?? '')));

		if ($vehicleType === $preferredType) {
			$score += 35;
			$reasons[] = 'matches preferred type';
		} elseif (in_array($vehicleType, $selectedTypes, true)) {
			$score += 20;
			$reasons[] = 'matches selected filter';
		}

		if ($budgetMax > 0) {
			if ($price >= $budgetMin && $price <= $budgetMax) {
				$score += 25;
				$reasons[] = 'fits budget';
			} elseif ($price < $budgetMin) {
				$score += 18;
				$reasons[] = 'below budget';
			} else {
				$overBudget = $price - $budgetMax;
				$allowedGap = max(1, (int) round($budgetMax * 0.5));
				$score += max(0, 15 - (int) round(($overBudget / $allowedGap) * 15));
			}
		} else {
			// Guest/default case: give a small value score to cheaper vehicles without hiding premium cars.
			$score += max(0, 20 - (int) floor($price / 25));
		}

		$popularityScore = min(20, $bookingCount * 5);
		if ($popularityScore > 0) {
			$score += $popularityScore;
			$reasons[] = 'popular with renters';
		}

		if ($preferredTransmission !== '' && $vehicleTransmission === $preferredTransmission) {
			$score += 5;
			$reasons[] = 'same transmission style';
		}

		if ($preferredFuel !== '' && $vehicleFuel === $preferredFuel) {
			$score += 5;
			$reasons[] = 'same fuel preference';
		}

		if ($mileage > 0) {
			$score += min(5, (int) floor($mileage / 5));
		}

		if ($status === 'available') {
			$score += 5;
		}

		$vehicle['recommendation_score'] = $score;
		$vehicle['recommendation_reason'] = implode(', ', array_slice(array_unique($reasons), 0, 3));
		return $vehicle;
	}
}

if (!function_exists('ridex_recommendation_rank_vehicles')) {
	/**
	 * Adds a recommendation score to each vehicle and sorts by highest score.
	 */
	function ridex_recommendation_rank_vehicles(array $vehicles, array $profile, int $limit = 0): array
	{
		$rankedVehicles = [];
		foreach ($vehicles as $vehicle) {
			if (!is_array($vehicle)) {
				continue;
			}
			$rankedVehicles[] = ridex_recommendation_score_vehicle($vehicle, $profile);
		}

		usort($rankedVehicles, static function (array $left, array $right): int {
			$scoreCompare = ((int) ($right['recommendation_score'] ?? 0)) <=> ((int) ($left['recommendation_score'] ?? 0));
			if ($scoreCompare !== 0) {
				return $scoreCompare;
			}

			$bookingCompare = ((int) ($right['booking_count'] ?? 0)) <=> ((int) ($left['booking_count'] ?? 0));
			if ($bookingCompare !== 0) {
				return $bookingCompare;
			}

			return ((int) ($left['price_per_day'] ?? 0)) <=> ((int) ($right['price_per_day'] ?? 0));
		});

		if ($limit > 0) {
			return array_slice($rankedVehicles, 0, $limit);
		}

		return $rankedVehicles;
	}
}
