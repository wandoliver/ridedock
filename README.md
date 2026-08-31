# RideDock

![Release](https://img.shields.io/badge/release-v0.0.2-blue)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

RideDock is an open-source, self-hosted cycling training platform. It imports raw activity data directly from supported bike computers, normalizes FIT recordings, and turns rides into a private activity archive with detailed measurements, route heatmaps, and training insights.

Its integrated AI coach can analyze individual rides and build a three-level training plan: a long-term roadmap to the target date, a current four-week block, and a concrete seven-day schedule. GPS coordinates, FIT files, provider payloads, and activity notes remain inside RideDock and are not sent to the AI provider.

## Architecture

```text
Hammerhead/Karoo ─ OAuth + webhook + FIT ─┐
Wahoo           ─ OAuth + polling + FIT ──┴─ Provider adapter ─ Normalize
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

See [Architecture](docs/ARCHITECTURE.md), [Data model](docs/DATA_MODEL.md), [Provider notes](docs/PROVIDERS.md), [production deployment](DEPLOYMENT.md), [managed hosting](HOSTING.md), [MVP backlog](docs/MVP_BACKLOG.md), and the [Changelog](CHANGELOG.md).

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

RideDock processes provider imports synchronously by default (`QUEUE_CONNECTION=sync`), so no queue worker is required for local use. A hosted installation may select an asynchronous queue connection such as `database`; only then must it also run `php artisan queue:work`. AI activity analyses and training plans currently execute synchronously even when another queue connection is selected; moving these flows fully behind the queue is a managed-hosting readiness item.

## AI coach configuration

The AI coach is optional. Without an AI provider, RideDock remains usable for importing and viewing activities. Normalized activity summaries can also be exported through the authenticated JSON/CSV API. The current implementation uses the OpenAI [Responses API](https://developers.openai.com/api/reference/responses) with strict structured outputs.

### 1. Create server-side credentials

Create or select an OpenAI project in the [API Platform](https://platform.openai.com/settings/organization/projects), then create a project API key. Use a separate project and key for each deployment environment so usage, limits and key rotation remain isolated.

Never expose the key in browser code, a mobile application, screenshots or the repository. OpenAI recommends loading API credentials from a server-side environment variable or key-management service; RideDock reads the key only from server configuration. See the official [OpenAI API authentication guidance](https://developers.openai.com/api/reference/overview#authentication).

### 2. Configure RideDock

Set these values in the deployment's `.env` file or secret manager:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=replace-with-a-project-api-key
OPENAI_MODEL=replace-with-a-responses-api-model
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT_SECONDS=60
```

`OPENAI_MODEL` has no default so enabling AI is always deliberate. Choose a model available to the project that supports the Responses API and structured outputs, then validate it against representative activities and training plans. Consult the current [OpenAI model catalog](https://developers.openai.com/api/docs/models) instead of treating an example model ID as a permanent recommendation.

After changing environment configuration, clear Laravel's cached configuration:

```bash
php artisan config:clear
```

### 3. Verify the integration

1. Sign in and open **Settings → Training & AI**.
2. Save a training profile with at least a goal, a future target date and available training days.
3. Import an activity and select **Analyze ride** on its detail page.
4. Open **Coach** and create the planning levels in order:
   - **Goal roadmap:** phases from tomorrow through the configured target date;
   - **Four-week block:** the current roadmap phase translated into four progressive weeks;
   - **Seven-day plan:** concrete sessions matched to available days and recent workload.

The seven-day plan remains useful on its own, but it uses the current roadmap and matching four-week block automatically when they exist. Creating a new roadmap makes an older block stale, so RideDock asks for a new block before using it for subsequent weekly plans.

Configuration or provider failures are shown without replacing the athlete's stored activity data. AI requests currently run synchronously, so public hosting should move them behind a persistent worker before allowing unrestricted use. The current worker setup and this limitation are described in [DEPLOYMENT.md](DEPLOYMENT.md).

### Data sent to OpenAI

RideDock sends only the normalized context needed for the requested feature:

- activity summaries and available sensor aggregates;
- subjective RPE and derived session-RPE load;
- aggregate training history for the previous six weeks;
- the athlete's configured goal, availability, constraints and optional performance values;
- the current generated roadmap and matching four-week block when producing a seven-day plan.

RideDock does not send OAuth credentials, provider payloads, FIT files, GPS coordinates, route geometry or activity notes. Requests set `store: false`, so RideDock does not create retrievable Response objects for later application use. This setting is not the same as contractual Zero Data Retention: standard API abuse-monitoring retention and project-level data controls are documented separately in OpenAI's [data controls guide](https://developers.openai.com/api/docs/guides/your-data).

### Cost and hosted use

API usage is billed to the OpenAI project behind `OPENAI_API_KEY`. A personal installation is controlled by its operator. A managed multiuser service must add per-user quotas, rate limits, usage accounting and operator spend alerts before public access. End users of a managed service should not be asked to paste the operator's API key into the browser. See [HOSTING.md](HOSTING.md) for the managed-service roadmap.

## Deployment options

- **Local development:** SQLite and synchronous jobs are sufficient for evaluating RideDock.
- **Production self-hosting:** use HTTPS, PostgreSQL, persistent private storage, backups and a supervised queue worker. Follow [DEPLOYMENT.md](DEPLOYMENT.md).
- **Managed RideDock:** a public multiuser service requires additional identity, privacy, lifecycle, billing and operational controls. Follow [HOSTING.md](HOSTING.md).

## Provider configuration

All credentials belong in `.env`; `.env` and source activity files are ignored by Git.

### Hammerhead

Set `HAMMERHEAD_ENABLED=true`, the client credentials, redirect URL and webhook secret. Then open the authenticated endpoint:

```text
GET /api/v1/connections/hammerhead/connect
```

Hammerhead's current official process requires a developer account, acceptance of its API agreement, and client credentials from the Dashboard. Review the license before publishing or hosting a connector for others. The current agreement contains restrictions that may require written permission for a public/community service; this repository is not legal advice.

Hammerhead activity distances are provided in metres and durations in milliseconds. The explicit `HAMMERHEAD_DISTANCE_MULTIPLIER=1` and `HAMMERHEAD_DURATION_MULTIPLIER=0.001` settings normalize both values to SI units; FIT-derived values are authoritative after enrichment.

Official references: [Developer Platform](https://www.hammerhead.io/pages/developer-platform), [Public API documentation](https://api.hammerhead.io/v1/docs), [developer account setup](https://support.hammerhead.io/hc/en-us/articles/43558376710683-Creating-a-Developer-Account), and [API license](https://support.hammerhead.io/hc/en-us/articles/42738760915099-API-Licence-Document).

### Wahoo

Set `WAHOO_ENABLED=true`, the client credentials and the exact redirect URL registered in the Wahoo Developer Portal. The connector uses the confidential OAuth authorization-code flow and requests only `user_read`, `workouts_read` and `offline_data`. Open the authenticated endpoint:

```text
GET /api/v1/connections/wahoo/connect
```

RideDock imports completed cycling workouts and their FIT files. Planned workouts, non-cycling workouts and write endpoints are not used. Wahoo webhooks remain disabled until a public webhook URL and token are configured. See the [Wahoo Cloud API documentation](https://cloud-api.wahooligan.com/).

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
