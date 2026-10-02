# Production deployment

This document describes the current baseline for running one RideDock installation on infrastructure controlled by its operator. It is an operational checklist, not yet a one-click installer or a guarantee that the application is ready for unrestricted public sign-ups.

For a service operated on behalf of non-technical users, also follow [HOSTING.md](HOSTING.md).

## Recommended topology

A production installation should have:

- an HTTPS reverse proxy or managed application ingress;
- the RideDock PHP application with the web root set to `public/`;
- PostgreSQL for durable multiuser data;
- a supervised queue worker;
- persistent private storage for FIT source files;
- encrypted database and file backups;
- server-side secret management for application, provider and AI credentials.

Redis and object storage are sensible scaling additions, but the current application can start with database-backed cache, sessions and queues. The `activity_sources` disk currently uses local private storage, so that storage path must be on a persistent volume until an object-storage-backed disk is configured and tested.

## Runtime requirements

- PHP 8.3 or later with the extensions required by Laravel and the selected database;
- Composer;
- Node.js for building frontend assets and parsing FIT files;
- PostgreSQL for a hosted or multiuser installation;
- a process manager for the web application and queue worker;
- sufficient private storage for retained FIT files and database backups.

## Production environment

Start from `.env.example`, keep the resulting file out of version control and set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ridedock.example.com
APP_KEY=

DB_CONNECTION=pgsql
DB_HOST=database
DB_PORT=5432
DB_DATABASE=ridedock
DB_USERNAME=ridedock
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ACTIVITY_STORE_SOURCE_FILES=true
ACTIVITY_SOURCE_FILE_DISK=activity_sources
```

Generate `APP_KEY` once with `php artisan key:generate` and back it up securely. Changing or losing this key makes encrypted provider access and refresh tokens unreadable.

Add provider and AI secrets only through the host's secret manager or protected environment configuration. Never bake `.env` into a container image or publish it with a deployment bundle.

## Build and release

A typical release runs:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run the test suite in CI before the release:

```bash
composer check
```

Make `storage/` and `bootstrap/cache/` writable by the application process. Serve only the `public/` directory; the repository root, `.env`, private storage and source FIT files must not be web-accessible.

Deploy application code and migrations as one coordinated release. Back up the database before migrations, and test restoration rather than treating backup creation alone as sufficient.

## Queue worker

With `QUEUE_CONNECTION=database`, provider synchronization, webhook imports and FIT enrichment can run outside the web request. Supervise at least one long-running worker:

```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

Restart workers after every deployment so they load the new code and configuration. Monitor `failed_jobs`, worker restarts, queue age and repeated provider failures. Increase concurrency only after confirming provider rate limits and ensuring unique jobs still behave as expected.

AI activity analyses, goal roadmaps, four-week blocks and weekly plans use the dedicated `ai` database connection and `ai` queue by default, independently of `QUEUE_CONNECTION`. Supervise its worker even when provider imports use `sync`:

```bash
php artisan queue:work ai --queue=ai --sleep=1 --timeout=120
```

Each AI job allows three attempts with 15- and 60-second retry delays. Its timeout is the greater of 120 seconds and `OPENAI_TIMEOUT_SECONDS + 10`; timeouts fail the analysis visibly. The built-in `ai` connection reserves jobs for at least 180 seconds, or `OPENAI_TIMEOUT_SECONDS + 60`, so the reservation outlives the job timeout. For a custom `AI_QUEUE_CONNECTION` (for example `redis`), set that connection's `retry_after` or visibility timeout above the job timeout and run its worker with `--queue=ai`. Do not use `sync`, `deferred`, or a nonpersistent connection in production. `AI_QUEUE_CONNECTION=sync` is available for tests and explicitly synchronous installations.

Requests reserve one outstanding analysis per owner and type (per ride for activity analyses). Completion status is owner-scoped and contains no AI context or provider details. Pages preserve completed plans while replacement jobs run, and show a safe error after final failure. Inspect `php artisan queue:failed` for operational details and use `php artisan queue:retry JOB_ID` after resolving a failure. Restart both AI and provider workers after deployments.

RideDock currently has no mandatory scheduled command. Add a scheduler process only when recurring synchronization, cleanup or retention jobs are introduced.

## Storage and backups

Back up all of the following as one recoverable system:

- PostgreSQL data;
- `storage/app/private/activity-source-files` or its configured replacement;
- the active deployment configuration and secret inventory, stored separately from application data;
- provider registration details and callback URLs.

Backups may contain precise location history, health-adjacent training data and encrypted OAuth tokens. Encrypt them, limit operator access, define retention periods and test a full restore on isolated infrastructure.

## OAuth and public URLs

Provider callback URLs must exactly match the production `APP_URL` and the values registered with each provider:

```text
https://ridedock.example.com/api/v1/oauth/hammerhead/callback
https://ridedock.example.com/api/v1/oauth/wahoo/callback
```

Use HTTPS before enabling either provider. Keep development, staging and production OAuth applications separate where the provider permits it. Confirm the provider's terms and production approval before allowing unrelated users to connect accounts.

## AI operations

Follow the AI setup in [README.md](README.md#ai-coach-configuration). For production:

- use a dedicated OpenAI project and server-side project key;
- never expose the key to the browser;
- configure project budgets and usage alerts;
- record token usage already returned with each completed analysis;
- monitor timeouts, rate limits and provider request IDs without logging athlete prompts;
- keep per-user quotas and abuse controls in front of public access.

RideDock sends requests with `store: false`, but operators must still document OpenAI processing and applicable retention controls in their own privacy information. Consult the official [OpenAI data controls guide](https://developers.openai.com/api/docs/guides/your-data).

## Health, logs and monitoring

- Use `GET /up` for a basic application health check.
- Send application and worker logs to durable centralized logging.
- Alert on HTTP 5xx rates, queue age, failed jobs, storage exhaustion and database capacity.
- Do not log access tokens, refresh tokens, raw FIT contents, complete provider payloads or OpenAI request bodies.
- Retain enough correlation metadata to investigate failures without retaining unnecessary athlete data.

## Release checklist

- [ ] HTTPS is enforced and `APP_DEBUG=false`.
- [ ] `APP_KEY` and all external credentials are stored outside the repository.
- [ ] PostgreSQL migrations and a restoration test have succeeded.
- [ ] Private FIT storage persists across releases and is not publicly served.
- [ ] A supervised queue worker is healthy.
- [ ] OAuth callback URLs match each provider registration exactly.
- [ ] OpenAI and provider usage limits are configured and monitored.
- [ ] Privacy, retention, export and deletion behavior is documented for the deployment.
- [ ] `composer check` passes for the deployed revision.

The final privacy, deletion and public-sign-up items are not fully implemented yet. Do not treat this checklist as approval for a public managed service; use the readiness gates in [HOSTING.md](HOSTING.md).
