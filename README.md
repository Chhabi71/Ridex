# Rentals App

Rental platform "RIDEX" with public booking flow, user dashboard, admin console, GPS tracking, and payment gateway (Khalti) hooks. UI flows come from the provided Website flow docx/pdf.

## Features

- Public: landing/hero search, vehicle catalog with filters, vehicle detail with status badge and booking CTA, booking form → confirm → receipt/history.
- Auth: login/register/forgot/reset, profile edit.
- Admin: login, dashboard with line (car/bike/luxury) and pie (rental share) charts, fleet CRUD + status changes, all bookings table + detail modal, GPS live tracking/history.
- Payments: Khalti initiation/verification, pay-on-arrival path, receipts.
- APIs: JSON endpoints for vehicles, bookings, auth, payments, GPS.
- Ops: cron jobs for payments verify, GPS cleanup, stats, reminders; migrations/seeds for setup.

## Requirements

- PHP 8.1+
- Composer
- MySQL
- Optional: MQTT/WebSocket broker for live GPS

## Setup

1. Install deps: `composer install`
2. Copy env: `cp config/env.example .env` (fill DB, base URL, Khalti keys, GPS broker)
3. Migrate DB: `php bin/migrate.php`
4. Seed sample data: `php bin/seed.php` (optional)
5. Serve (dev): `php -S localhost:8000 -t public`

## Project Layout (high level)

- `public/` entrypoint and assets (css/js/uploads)
- `public/index.php` front controller and route handling logic
- `src/Views/` blade-less PHP views (public, booking, user, admin, partials)
- `src/Templates/` main layout wrapper
- `src/Models/` data access (users, vehicles, bookings, payments, gps_logs, categories)
- `config/` app/env/db/routes, khalti, gps
- `bin/` CLI (migrate, seed, cron scripts)
- `charts/` Chart.js configs (admin line + pie)
- `migrations/` SQL schema files
- `logs/`, `var/` runtime outputs

## Cron/Background (optional but recommended)

- `php bin/cron_cleanup_gps.php [--days=30] [--dry-run]`
  - Deletes old `gps_logs` rows using `GPS_RETENTION_DAYS` (default 30).
- `php bin/cron_expire_bookings.php`
  - Runs booking lifecycle sync and aligns fleet statuses (`available/reserved/on_trip/overdue/maintenance`).
- `php bin/cron_generate_stats.php [--days=30] [--output=var/cache/stats/daily_stats.json]`
  - Aggregates booking/fleet/payment stats into a JSON cache file for reporting.
- `php bin/cron_send_reminders.php [--pickup-hours=24] [--return-hours=6] [--force]`
  - Queues reminder events for upcoming pickup/return windows and deduplicates via cache state.
- `php bin/cron_verify_payments.php [--dry-run]`
  - Reconciles latest payment rows with booking payment states and optionally verifies pending Khalti `pidx` entries when API env vars are configured.

All cron scripts write structured logs to `var/logs/cron/*.log`.

## Notes

- Routes and controllers are scaffolded; wire `config/routes.php` to the current flows.
- Keep status enums (available/reserved/on trip/maintenance/overdue) in sync across UI, constants, and DB.

## Vehicle JSON Sync

- Vehicles now sync bidirectionally between DB and JSON files under `data/vehicles-json/`:
  - `cars.json`
  - `bikes.json`
  - `luxury.json`
- Public requests trigger sync automatically in `public/index.php` before and after page handling.
- Keep these JSON files committed so deployments use the same vehicle inventory as local.
- Legacy compatibility (local/dev default): when local `var/cache/vehicles-json/*.json` exists, newer files are mirrored into `data/vehicles-json/` before sync, and tracked files are mirrored back after sync.
- In production this mirror is off by default; enable only if required with `ENABLE_LEGACY_JSON_MIRROR=1`.
- In production (`APP_ENV=production`), vehicle sync defaults to DB-first so website create/edit/delete changes persist in DB and are not overwritten by stale JSON.
- To force JSON-first behavior, set `VEHICLE_SYNC_STRATEGY=json-first`.
- Manual/cron sync command:
  - `php bin/sync_vehicles_json.php`
- Default conflict rule: latest update wins across JSON and DB.
- JSON edits are detected even when `updated_at` is not manually changed (row hash + file mtime fallback).
- If a category JSON file is malformed, sync now fails with an explicit error instead of silently reverting file content.
- Optional conflict-bias modes:
  - Force JSON winner: `php bin/sync_vehicles_json.php --prefer-json` (or `--force-json`)
  - `php bin/sync_vehicles_json.php --prefer-db-timestamps`
  - `php bin/sync_vehicles_json.php --prefer-db` (alias)
