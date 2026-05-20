<?php
/**
 * Purpose: Admin GPS live tracking screen using Leaflet and OpenStreetMap.
 */

$adminUserName = isset($adminUserName) ? trim((string) $adminUserName) : 'Admin';
$liveTrackingGeneratedAt = isset($liveTrackingGeneratedAt) ? trim((string) $liveTrackingGeneratedAt) : '';

$generatedAtLabel = 'Live data refreshes automatically';
if ($liveTrackingGeneratedAt !== '') {
    try {
        $generatedAtLabel = 'Last server render: ' . (new DateTimeImmutable($liveTrackingGeneratedAt))->format('M d, Y h:i A');
    } catch (Throwable $exception) {
        $generatedAtLabel = 'Live data refreshes automatically';
    }
}
?>

<section class="admin-live-tracking ridex-gps-page" aria-labelledby="admin-live-tracking-title">
    <div class="admin-dashboard__shell">
        <aside class="admin-sidebar" aria-label="Admin panel navigation">
            <nav class="admin-sidebar__nav" aria-label="Admin sections">
                <a class="admin-sidebar__link" href="index.php?page=admin-dashboard">Dashboard</a>
                <a class="admin-sidebar__link" href="index.php?page=admin-manage-fleet">Manage Fleet</a>
                <a class="admin-sidebar__link" href="index.php?page=admin-all-bookings">All Bookings</a>
                <a class="admin-sidebar__link is-active" href="index.php?page=admin-live-tracking" aria-current="page">Live Tracking</a>
                <a class="admin-sidebar__link" href="index.php?page=admin-user-verifications">User Verification</a>
            </nav>
        </aside>

        <div class="admin-dashboard__content admin-live-tracking__content">
            <div class="ridex-gps-hero">
                <h1 class="admin-dashboard__title" id="admin-live-tracking-title">Live Vehicle Tracking</h1>
                <div class="ridex-gps-hero__actions">
                    <button class="ridex-gps-button" type="button" data-gps-refresh>
                        <span class="material-symbols-rounded" aria-hidden="true">refresh</span>
                        Refresh
                    </button>
                    <button class="ridex-gps-button ridex-gps-button--primary" type="button" data-gps-simulate-selected>
                        <span class="material-symbols-rounded" aria-hidden="true">near_me</span>
                        Simulate Next Location
                    </button>
                </div>
            </div>

            <section class="admin-kpi-grid ridex-gps-kpis" aria-label="GPS tracking key metrics">
                <article class="admin-kpi-card">
                    <header class="admin-kpi-card__header">
                        <h2>Active Trips</h2>
                        <span class="material-symbols-rounded admin-kpi-card__icon" aria-hidden="true">route</span>
                    </header>
                    <strong class="admin-kpi-card__value" data-gps-kpi="activeTrips">0</strong>
                </article>
                <article class="admin-kpi-card">
                    <header class="admin-kpi-card__header">
                        <h2>GPS Online</h2>
                        <span class="material-symbols-rounded admin-kpi-card__icon" aria-hidden="true">gps_fixed</span>
                    </header>
                    <strong class="admin-kpi-card__value" data-gps-kpi="gpsOnline">0</strong>
                </article>
                <article class="admin-kpi-card">
                    <header class="admin-kpi-card__header">
                        <h2>GPS Lost</h2>
                        <span class="material-symbols-rounded admin-kpi-card__icon admin-kpi-card__icon--down" aria-hidden="true">gps_off</span>
                    </header>
                    <strong class="admin-kpi-card__value" data-gps-kpi="gpsLost">0</strong>
                </article>
                <article class="admin-kpi-card">
                    <header class="admin-kpi-card__header">
                        <h2>High Risk</h2>
                        <span class="material-symbols-rounded admin-kpi-card__icon admin-kpi-card__icon--down" aria-hidden="true">warning</span>
                    </header>
                    <strong class="admin-kpi-card__value" data-gps-kpi="highRisk">0</strong>
                </article>
            </section>

            <section class="ridex-gps-shell" aria-label="GPS live map and vehicle monitoring panel">
                <div class="ridex-gps-map-card">
                    <div class="ridex-gps-map-card__header">
                        <div>
                            <h2>Live Map</h2>
                            <p><?= htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="ridex-gps-status-pill" data-gps-feed-status>Loading GPS feed...</span>
                    </div>

                    <div
                        id="ridex-live-map"
                        class="ridex-gps-map"
                        data-live-gps-map
                        data-feed-url="ajax/gps-live-feed.php"
                        data-simulate-url="ajax/gps-simulate-location.php"
                    ></div>

                </div>

                <aside class="ridex-gps-panel" aria-label="Tracked vehicles list">
                    <div class="ridex-gps-panel__header">
                        <h2>Active Rental Monitoring</h2>
                        <p>Select a vehicle to view route and risk details.</p>
                    </div>

                    <div class="ridex-gps-selected" data-gps-selected>
                        <p class="ridex-gps-empty">No vehicle selected yet.</p>
                    </div>

                    <div class="ridex-gps-list" data-gps-vehicle-list>
                        <p class="ridex-gps-empty">Loading active rentals...</p>
                    </div>
                </aside>
            </section>
        </div>
    </div>
</section>
