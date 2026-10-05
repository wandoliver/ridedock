# RideDock

![Release](https://img.shields.io/badge/release-v0.1.0-blue)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

RideDock is an open-source, self-hosted cycling training platform. It imports raw activity data directly from supported bike computers, normalizes FIT recordings, and turns rides into a private activity archive with detailed measurements, route heatmaps, and training insights.

Its integrated AI coach can analyze individual rides and build a three-level training plan: a long-term roadmap to the target date, a current four-week block, and a concrete seven-day schedule. GPS coordinates, FIT files, provider payloads, and activity notes remain inside RideDock and are not sent to the AI provider.

## Managing connections

In Settings → Connections, use Disconnect to remove all locally stored provider credentials. Imported rides and FIT files remain available. Wahoo also receives a permission revocation request; if it fails, RideDock confirms local disconnection and asks you to remove access in your Wahoo account. Hammerhead is disconnected locally. Use Connect to authorize again. Disconnected connections cannot be synchronized. API clients can call `DELETE /api/v1/connections/{provider}` with the `connections:write` ability.

After updating, run `php artisan migrate --force` to allow cleared access tokens.

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

Provider imports run synchronously by default (`QUEUE_CONNECTION=sync`). AI activity analyses and all training plans use a separate persistent database queue by default (`AI_QUEUE_CONNECTION=ai`). `composer run dev` starts its worker automatically. When starting the web server separately, also run `php artisan queue:work ai --queue=ai --sleep=1 --timeout=120`. Provider imports may independently use an asynchronous connection such as `database`, with their own worker. See [DEPLOYMENT.md](DEPLOYMENT.md) for supervision and retry settings.

### Updating to v0.0.8

AI generation now requires a persistent worker by default, including installations that keep provider imports synchronous. Clear cached configuration with `php artisan config:clear` after updating. Restart `composer run dev` locally; in production, supervise `php artisan queue:work ai --queue=ai --sleep=1 --timeout=120` and restart existing workers. No new database migration is required for this release. `AI_QUEUE_CONNECTION=sync` remains available for explicitly synchronous installations.

### Local HTTPS

OAuth providers may require an HTTPS callback during local development. Create a trusted certificate with `mkcert`:

```bash
mkdir -p .certs
mkcert -install
mkcert -cert-file .certs/localhost.pem -key-file .certs/localhost-key.pem 127.0.0.1 localhost ::1
```

When these certificate files exist, `composer run dev` starts Laravel internally on port `8002` and exposes RideDock at `https://127.0.0.1:8001` through the bundled local HTTPS proxy. Stop all development processes together with `Ctrl+C`.

## AI coach configuration

The AI coach is optional. Without an AI provider, RideDock remains usable for importing and viewing activities. Normalized activity summaries can also be exported through the authenticated JSON/CSV API. The current implementation uses the OpenAI [Responses API](https://developers.openai.com/api/reference/responses) with strict structured outputs.

### 1. Create server-side credentials

Create or select an OpenAI project in the [API Platform](https://platform.openai.com/settings/organization/projects), then create a project API key. Use a separate project and key for each deployment environment so usage, limits and key rotation remain isolated.

Never expose the key in browser code, a mobile application, screenshots or the repository. OpenAI recommends loading API credentials from a server-side environment variable or key-management service; RideDock reads the key only from server configuration. See the official [OpenAI API authentication guidance](https://developers.openai.com/api/reference/overview#authentication).

### 2. Configure RideDock

Set these values in the deployment's `.env` file or secret manager:

```dotenv
AI_QUEUE_CONNECTION=ai
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

Requests return immediately with a queued status. Ride and Coach pages check progress automatically and reload when the job completes or fails. Duplicate requests reuse the outstanding analysis; provider failures allow up to three attempts and then show a safe error without replacing stored activities or completed plans. The worker setup is described in [DEPLOYMENT.md](DEPLOYMENT.md).

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

## Training overview

Insights → Volume & load includes the last six calendar weeks of recorded training, independently of the archive period selector. It shows weekly hours and distance, recorded RPE load, and the share of known ride time with a usable rating. Weeks start on Monday; rides are grouped by their recording timezone, with the application timezone as a fallback.

RideDock calculates a session-RPE-style load as **moving minutes × whole-ride RPE (1–10)**. This uses the approach of combining duration and perceived effort described by [Foster et al.](https://pubmed.ncbi.nlm.nih.gov/8693756/), with RideDock's moving time as the duration measure. Missing ratings or durations remain unknown and are explicitly marked; they do not imply zero load. Weeks with no recorded rides retain zero recorded volume and load.

Effort distribution assigns each ride's entire duration to a fixed RPE group: easy (1–3), moderate (4–6), hard (7–10), or unrated. These groups do not measure time in heart-rate or power zones and do not separate intervals within a ride.

Volume and load changes compare the last completed week with the average of the preceding four weeks, retaining empty weeks. The current week is excluded from this comparison. Percentage changes require earlier recorded history, known durations and a nonzero baseline; load comparisons also require complete ratings. These describe the recorded archive, rather than fitness or recovery. The methodology and a weekly table are available directly in Insights.

The AI coach uses the same summaries and rating coverage. Analyses already stored are preserved; newly requested analyses and plans use the updated context and prompt versions.

## Deployment options

- **Local development:** SQLite with the AI worker started by `composer run dev` is sufficient for evaluating RideDock.
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

## Dated training zones

Training settings show dated FTP and maximum-heart-rate references. Normal threshold edits append a snapshot effective when saved. A separate dated-entry form supports explicit historical measurements; backdating does not replace later current references. Each entry supplies both values, with blanks meaning unknown. History is append-only, and ties at the same instant resolve to the latest entry. Existing values migrate as known at migration time; earlier references remain unknown until supplied.

Power uses six continuous percentage-of-FTP bands with transitions at 55, 75, 90, 105 and 120 percent, adapted from [Coggan's power levels](https://www.trainingpeaks.com/blog/power-training-levels/). A separate neuromuscular band is not inferred from FTP. Heart rate uses [five HRmax percentage bands](https://www.polar.com/en/guide/heart-rate-zones) from 50–100%, with separate below/above-range buckets. Integer boundaries are rounded upward; each displayed integer range includes both endpoints, with the next range starting one unit higher. Exactly HRmax remains in H5. These are reference bands, rather than measured individual physiological thresholds. Ride analysis uses the snapshot effective at the recording's start, while planning uses the current snapshot.

## Measured time in zones

Activity details and Insights show recorded power and heart-rate time by the dated reference bands. Timestamped samples are weighted by time until the next sample, with gaps over 30 seconds, invalid values and the final sample without a successor left unknown. Zero watts count as coasting; zero heart rate is invalid. Scalar series require an explicit sampling interval. Time is clipped to recorded elapsed duration. Zone percentages describe classified time, while coverage and unknown minutes describe the complete elapsed recording. Insights totals cover the same six calendar weeks as the training statistics and use each ride’s historical references. AI contexts receive aggregates only, with coverage, never raw sensor samples.

## Comparable efficiency

Assign a comparison group on each activity detail (for example, the same indoor setup or repeated endurance route). An empty value removes it. The six-week Insights view compares only your rides of the same type and group, taking the latest eligible predecessor in the preceding six weeks. Windows must match within 20% duration and 10% mean absolute power.

The descriptive W/bpm proxy uses time-weighted mean power divided by mean heart rate during the longest qualifying continuous window: at least 20 minutes, after the first 10 minutes, entirely within 55–75% of the dated FTP, with power coefficient of variation no greater than 10%. Both sensors must overlap throughout; gaps break a window. These eligibility cutoffs are product choices, not validated physiological thresholds. Unlike [TrainingPeaks Efficiency Factor](https://help.trainingpeaks.com/hc/en-us/articles/204071724-Aerobic-Decoupling-Pw-Hr-and-Pa-HR-and-Efficiency-Factor-EF), this uses mean power rather than normalized power. Group labels stay out of AI contexts, which receive only numerical aggregates. Differences are descriptive and can reflect temperature, fatigue, device setup or other conditions; they are not a fitness or recovery score.

## Recorded load, monotony and balance

Insights uses the seven and 42 complete calendar days before today; activity AI analysis cuts history off before the activity’s local day and never uses subsequent rides. Session load is moving minutes × valid RPE (1–10), summed per recorded ride day. Within established recording history, days without rides contribute zero recorded cycling load. Before the first recorded ride they remain unknown. Missing duration/RPE makes a day and its period unknown, rather than artificially zero. Metrics require recording history reaching the period start and all recorded rides in the period having known load. Other sports, missing imports and subjective recovery are not captured.

Seven-day monotony is daily mean divided by the population standard deviation of all seven days; strain is total seven-day load × monotony, following [Foster’s recorded-load method](https://pubmed.ncbi.nlm.nih.gov/9662690/). Zero load and zero variation have explicit unavailable states. The descriptive load balance is the arithmetic 42-day daily mean minus the seven-day daily mean, in RPE-minutes/day. This is a product-defined load comparison, not a CTL/ATL/TSB, fitness or freshness model. Neither metric has a risk colour, recommended threshold or injury/recovery prediction. Actual freshness remains unavailable because direct recovery information is not collected. UI and AI contexts expose coverage, reasons, formulas and this limitation.
