<?php
/**
 * Purpose: Admin dashboard view showing KPIs and chart frames.
 * Website Section: Admin dashboard.
 * Developer Notes: The page reads precomputed KPI/chart data from index.php and renders safe fallbacks.
 */

$adminUserName = isset($adminUserName) ? trim((string) $adminUserName) : 'Admin';
$dashboardKpis = isset($dashboardKpis) && is_array($dashboardKpis) ? $dashboardKpis : [];
$dashboardCharts = isset($dashboardCharts) && is_array($dashboardCharts) ? $dashboardCharts : [];
$dashboardDataEndpoint = isset($dashboardDataEndpoint) ? trim((string) $dashboardDataEndpoint) : 'index.php?page=admin-dashboard-data';
$dashboardRefreshIntervalMs = isset($dashboardRefreshIntervalMs) && is_numeric($dashboardRefreshIntervalMs)
	? (int) $dashboardRefreshIntervalMs
	: 30000;

if ($dashboardRefreshIntervalMs < 10000) {
	$dashboardRefreshIntervalMs = 10000;
} elseif ($dashboardRefreshIntervalMs > 300000) {
	$dashboardRefreshIntervalMs = 300000;
}

$resolveMetric = static function (array $kpis, string $key): array {
	$metric = $kpis[$key] ?? [];

	return [
		'value' => $metric['value'] ?? null,
		'trend' => $metric['trend'] ?? null,
	];
};

$resolveTrend = static function ($trend): array {
	if (!is_numeric($trend)) {
		return [
			'label' => 'New',
			'class' => 'admin-kpi-card__trend--up',
		];
	}

	$trendValue = round((float) $trend, 1);
	if (abs($trendValue) < 0.05) {
		return [
			'label' => '0.0%',
			'class' => 'admin-kpi-card__trend--flat',
		];
	}

	if ($trendValue > 0) {
		return [
			'label' => '+' . number_format($trendValue, 1) . '%',
			'class' => 'admin-kpi-card__trend--up',
		];
	}

	return [
		'label' => number_format($trendValue, 1) . '%',
		'class' => 'admin-kpi-card__trend--down',
	];
};

$formatCurrency = static function ($value): string {
	if (!is_numeric($value)) {
		return 'Unavailable';
	}

	return 'NRs ' . number_format((float) $value, 0);
};

$formatInteger = static function ($value): string {
	if (!is_numeric($value)) {
		return 'Unavailable';
	}

	return number_format((int) $value);
};

$formatPercent = static function ($value): string {
	if (!is_numeric($value)) {
		return 'Unavailable';
	}

	return number_format((float) $value, 1) . '%';
};

$totalRevenue = $resolveMetric($dashboardKpis, 'totalRevenue');
$activeRentals = $resolveMetric($dashboardKpis, 'activeRentals');
$totalFleet = $resolveMetric($dashboardKpis, 'totalFleet');
$fleetAvailability = $resolveMetric($dashboardKpis, 'fleetAvailability');

$totalRevenueTrend = $resolveTrend($totalRevenue['trend']);
$activeRentalsTrend = $resolveTrend($activeRentals['trend']);
$totalFleetTrend = $resolveTrend($totalFleet['trend']);
$fleetAvailabilityTrend = $resolveTrend($fleetAvailability['trend']);

$fleetAvailabilityIcon = 'trending_up';
$fleetAvailabilityIconClass = 'admin-kpi-card__icon--up';
if (($fleetAvailabilityTrend['class'] ?? '') === 'admin-kpi-card__trend--down') {
	$fleetAvailabilityIcon = 'trending_down';
	$fleetAvailabilityIconClass = 'admin-kpi-card__icon--down';
}

$dashboardPayloadJson = json_encode(
	[
		'kpis' => $dashboardKpis,
		'charts' => $dashboardCharts,
	],
	JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);

if (!is_string($dashboardPayloadJson)) {
	$dashboardPayloadJson = '{}';
}
?>

<section class="admin-dashboard" aria-labelledby="admin-dashboard-title">
	<div class="admin-dashboard__shell">
		<aside class="admin-sidebar" aria-label="Admin panel navigation">
			<nav class="admin-sidebar__nav" aria-label="Admin sections">
				<a class="admin-sidebar__link is-active" href="index.php?page=admin-dashboard" aria-current="page">Dashboard</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-manage-fleet">Manage Fleet</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-all-bookings">All Bookings</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-live-tracking">Live Tracking</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-user-verifications">User Verification</a>
			</nav>
		</aside>

		<div class="admin-dashboard__content">
			<h1 class="admin-dashboard__title" id="admin-dashboard-title">Dashboard</h1>

			<section class="admin-kpi-grid" aria-label="Dashboard key metrics">
				<article class="admin-kpi-card">
					<header class="admin-kpi-card__header">
						<h2>Total Revenue</h2>
						<span class="material-symbols-rounded admin-kpi-card__icon admin-kpi-card__icon--up" aria-hidden="true">trending_up</span>
					</header>
					<p class="admin-kpi-card__value" data-kpi-value="totalRevenue" data-kpi-format="currency"><?= htmlspecialchars($formatCurrency($totalRevenue['value']), ENT_QUOTES, 'UTF-8') ?></p>
					<p class="admin-kpi-card__trend <?= htmlspecialchars($totalRevenueTrend['class'], ENT_QUOTES, 'UTF-8') ?>" data-kpi-trend="totalRevenue">
						<?= htmlspecialchars($totalRevenueTrend['label'], ENT_QUOTES, 'UTF-8') ?>
					</p>
				</article>

				<article class="admin-kpi-card">
					<header class="admin-kpi-card__header">
						<h2>Active Rentals</h2>
						<span class="material-symbols-rounded admin-kpi-card__icon admin-kpi-card__icon--up" aria-hidden="true">shopping_cart</span>
					</header>
					<p class="admin-kpi-card__value" data-kpi-value="activeRentals" data-kpi-format="integer"><?= htmlspecialchars($formatInteger($activeRentals['value']), ENT_QUOTES, 'UTF-8') ?></p>
					<p class="admin-kpi-card__trend <?= htmlspecialchars($activeRentalsTrend['class'], ENT_QUOTES, 'UTF-8') ?>" data-kpi-trend="activeRentals">
						<?= htmlspecialchars($activeRentalsTrend['label'], ENT_QUOTES, 'UTF-8') ?>
					</p>
				</article>

				<article class="admin-kpi-card">
					<header class="admin-kpi-card__header">
						<h2>Total Fleet</h2>
						<span class="material-symbols-rounded admin-kpi-card__icon admin-kpi-card__icon--up" aria-hidden="true">trending_up</span>
					</header>
					<p class="admin-kpi-card__value" data-kpi-value="totalFleet" data-kpi-format="integer"><?= htmlspecialchars($formatInteger($totalFleet['value']), ENT_QUOTES, 'UTF-8') ?></p>
					<p class="admin-kpi-card__trend <?= htmlspecialchars($totalFleetTrend['class'], ENT_QUOTES, 'UTF-8') ?>" data-kpi-trend="totalFleet">
						<?= htmlspecialchars($totalFleetTrend['label'], ENT_QUOTES, 'UTF-8') ?>
					</p>
				</article>

				<article class="admin-kpi-card">
					<header class="admin-kpi-card__header">
						<h2>Fleet Availability</h2>
						<span class="material-symbols-rounded admin-kpi-card__icon <?= htmlspecialchars($fleetAvailabilityIconClass, ENT_QUOTES, 'UTF-8') ?>" data-kpi-icon="fleetAvailability" aria-hidden="true"><?= htmlspecialchars($fleetAvailabilityIcon, ENT_QUOTES, 'UTF-8') ?></span>
					</header>
					<p class="admin-kpi-card__value" data-kpi-value="fleetAvailability" data-kpi-format="percent"><?= htmlspecialchars($formatPercent($fleetAvailability['value']), ENT_QUOTES, 'UTF-8') ?></p>
					<p class="admin-kpi-card__trend <?= htmlspecialchars($fleetAvailabilityTrend['class'], ENT_QUOTES, 'UTF-8') ?>" data-kpi-trend="fleetAvailability">
						<?= htmlspecialchars($fleetAvailabilityTrend['label'], ENT_QUOTES, 'UTF-8') ?>
					</p>
				</article>
			</section>

			<section class="admin-charts-grid" aria-label="Dashboard chart summaries">
				<article class="admin-chart-card" data-chart-panel="salesVehicleCategory">
					<header class="admin-chart-card__header">
						<h2>Sales Vehicle Category</h2>
					</header>
					<div class="admin-chart-card__canvas-wrap">
						<canvas id="admin-sales-vehicle-category-chart" aria-label="Sales by vehicle category chart"></canvas>
					</div>
					<p class="admin-chart-card__fallback" data-chart-fallback hidden>Unavailable</p>
				</article>

				<article class="admin-chart-card" data-chart-panel="mostRentedVehicleCategory">
					<header class="admin-chart-card__header">
						<h2>Most Rented Vehicle Category</h2>
					</header>
					<div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--donut">
						<canvas id="admin-most-rented-category-chart" aria-label="Most rented vehicle category chart"></canvas>
					</div>
					<p class="admin-chart-card__fallback" data-chart-fallback hidden>Unavailable</p>
				</article>
			</section>
		</div>
	</div>

	<script
		id="admin-dashboard-data"
		type="application/json"
		data-refresh-url="<?= htmlspecialchars($dashboardDataEndpoint, ENT_QUOTES, 'UTF-8') ?>"
		data-refresh-interval-ms="<?= (int) $dashboardRefreshIntervalMs ?>"
	><?= $dashboardPayloadJson ?></script>
</section>