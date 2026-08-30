# RideDock

![Release](https://img.shields.io/badge/release-v0.0.2-blue)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

RideDock is an open-source, self-hosted cycling training platform. It imports raw activity data directly from supported bike computers, normalizes FIT recordings, and turns rides into a private activity archive with detailed measurements, route heatmaps, and training insights.

Its integrated AI coach can analyze individual rides and create seven-day training plans from personal goals, available training days, and recent workload. GPS coordinates, FIT files, provider payloads, and activity notes remain inside RideDock and are not sent to the AI provider.

## Architecture

```text
Hammerhead/Karoo ─ OAuth + webhook + FIT ─ Provider adapter ─ Normalize
                                                                  │
                                                                  ▼
                                                        activities + sources
                                                        streams + events
                                                                  │
                                               ┌──────────────────┴───────────────┐
                                               ▼                                  ▼
                                       JSON/CSV analysis API                Heatmap module
```

The provider payload remains available in `activity_sources.raw_payload`, while application code reads the normalized `activities` record. Raw source files use the non-serving `activity_sources` disk and are never returned through the API.

See [Architecture](docs/ARCHITECTURE.md), [Data model](docs/DATA_MODEL.md), [Provider notes](docs/PROVIDERS.md), [MVP backlog](docs/MVP_BACKLOG.md), and the [Changelog](CHANGELOG.md).

## Local setup

Requirements: PHP 8.3+, Composer, and SQLite (default) or PostgreSQL/MySQL.

```bash
composer setup
php artisan app:create-owner-token you@example.com
composer run dev
```

Copy the token printed by the second command; it is shown once. Use it as a bearer token for all `/api/v1` application endpoints.

For an existing checkout where frontend assets are not needed:

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-owner-token you@example.com
```

RideDock processes imports and requested AI analyses synchronously by default (`QUEUE_CONNECTION=sync`), so no queue worker is required. A hosted installation may explicitly select an asynchronous queue connection such as `database`; only then must it also run `php artisan queue:work`.

## Provider configuration

All credentials belong in `.env`; `.env` and source activity files are ignored by Git.

### Hammerhead

Set `HAMMERHEAD_ENABLED=true`, the client credentials, redirect URL and webhook secret. Then open the authenticated endpoint:

```text
GET /api/v1/connections/hammerhead/connect
```

Hammerhead's current official process requires a developer account, acceptance of its API agreement, and client credentials from the Dashboard. Review the license before publishing or hosting a connector for others. The current agreement contains restrictions that may require written permission for a public/community service; this repository is not legal advice.

The public OpenAPI schema currently omits units for activity `distance` and `duration`. Conversion is therefore explicit through `HAMMERHEAD_DISTANCE_MULTIPLIER` and `HAMMERHEAD_DURATION_MULTIPLIER`; FIT-derived values are authoritative after enrichment.

Official references: [Developer Platform](https://www.hammerhead.io/pages/developer-platform), [Public API documentation](https://api.hammerhead.io/v1/docs), [developer account setup](https://support.hammerhead.io/hc/en-us/articles/43558376710683-Creating-a-Developer-Account), and [API license](https://support.hammerhead.io/hc/en-us/articles/42738760915099-API-Licence-Document).

## API examples

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost:8000/api/v1/activities?from=2026-08-01&to=2026-08-31"

curl -X PATCH -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"rpe":4,"notes":"Easy endurance ride"}' \
  "http://localhost:8000/api/v1/activities/ACTIVITY_UUID"

curl -H "Authorization: Bearer TOKEN" \
  "http://localhost:8000/api/v1/analysis/export?from=2026-08-01&format=json"
```

Routes are omitted from analysis exports by default. Pass `include_route=1` only when location data is actually needed.

## Open-source policy

MIT is recommended for the application core because it is simple and permissive. Third-party APIs, trademarks, user data and imported code retain their own terms. In particular, the MIT license does not grant rights to use Hammerhead/SRAM APIs.

Security issues should be reported privately as described in [SECURITY.md](SECURITY.md). Contributions are welcome under [CONTRIBUTING.md](CONTRIBUTING.md).

## Tests

```bash
composer test
```
