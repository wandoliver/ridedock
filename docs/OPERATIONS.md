# Provider and AI operations — v0.8.0

Administrators can open **Operations** for monthly AI costs, usage by user/feature/model, queue capacity, provider waiting/error states and recent safe operational events. Members can open **Settings → AI usage** for their own quotas, tokens and costs. These pages require authentication, enforce administrator/owner boundaries and use no-store responses. They expose neither training contexts nor provider credentials, response bodies or download URLs.

## Upgrade

Run `php artisan migrate`, clear/rebuild configuration and restart workers. The migration adds provider sync checkpoints, user quota overrides and usage/event tables. Existing analyses consume quota in their original calendar month. Existing token records are imported with unknown costs; historical retries and cached-token details cannot be reconstructed.

## Provider approvals and hosted mode

Self-hosting remains the default. Set `HOSTED_SERVICE=true` for an operator-run service with unrelated users. Every enabled integration then requires operator confirmation using `HAMMERHEAD_HOSTED_APPROVED` / `WAHOO_HOSTED_APPROVED` and its `*_TERMS_REVIEWED_AT` value. Missing confirmation blocks authorization and provider HTTP calls. These settings attest a review/approval obtained outside RideDock; they do not request or grant provider permission.

Keep an operator record of the provider application, intended audience, approval correspondence, reviewed agreement/date, allowed use and throughput, callback/webhook domains and a contact for revocation or quota escalation. Revisit this record when the terms, audience or commercial model change. See [provider references](PROVIDERS.md).

Wahoo's [official documentation](https://cloud-api.wahooligan.com/#registration) describes production review and sandbox/production throughput. RideDock uses conservative sandbox defaults: 25 requests per five minutes, 100 per hour and 250 per day. Configure only the limits granted to your application using `WAHOO_REQUESTS_PER_5_MINUTES`, `WAHOO_REQUESTS_PER_HOUR` and `WAHOO_REQUESTS_PER_DAY`. Wahoo token endpoints are not counted against these request counters. File-summary lookups are API calls; CDN downloads are handled separately by the existing guarded downloader.

Hammerhead's `HAMMERHEAD_REQUESTS_PER_MINUTE=60` is an operator-configurable local pacing limit, not a claimed upstream allowance. Review the [API licence](https://support.hammerhead.io/hc/en-us/articles/42738760915099-API-Licence-Document) and confirm actual permitted throughput with the provider.

## Shared coordination and delayed work

All nodes serving one provider application must share the database, application key, cache namespace, queue and private source storage. Use a database, Redis or Memcached cache shared by every node; set `OPERATIONS_CACHE_STORE` to its configured store name if it differs from `CACHE_STORE`. Hosted mode refuses node-local cache drivers. The default database cache must point to the same database from every node. Keep clocks synchronized.

Application-wide request counters use locked fixed windows. `Retry-After` supports seconds and HTTP dates; Wahoo's `X-RateLimit-Reset` is a delay in seconds. Exhausted Wahoo remaining headers pause later calls even after a successful response. HTTP 429 and 5xx responses establish a shared cooldown rather than immediately retrying. Local counters do not replace upstream limits or count traffic from other applications/processes using the same provider credentials.

Each connection's sync and token refresh use shared locks; refresh re-reads tokens after acquiring the lock. Hammerhead checkpoints each detail rather than restarting a page of detail requests. Sync retains both its cursor and overlap boundary across pauses. Bounded execution slices also yield within Wahoo pages; imported sources/files are deduplicated when a partial page resumes.

With `QUEUE_CONNECTION=database`, already-requested sync and webhook imports release themselves until capacity returns, with a two-day deadline and up to three actual exceptions. Waiting for a limit does not consume that exception allowance. Set `DB_QUEUE_RETRY_AFTER` to at least 360 seconds; the database connection enforces that minimum for the 300-second provider jobs. Supervise `php artisan queue:work --sleep=1 --timeout=300` and restart it after upgrades. Redis/other backends also need a reservation interval longer than the job timeout, as well as the separately documented lifecycle support requirements.

With synchronous provider processing, a limited manual sync shows its waiting state and retry time; API requests return HTTP 429 with `Retry-After`. The user must retry. A synchronous limited webhook returns HTTP 503 so its sender can retry. Prefer a persistent provider queue for hosted operation. OAuth remains synchronous; a failed authorization must be restarted.

**Auto-Sync remains deferred.** There is no recurring provider polling schedule. Resuming work already requested by a user or webhook is part of that request.

## AI quotas and capacity

The defaults are 100 ride analyses and 30 training plans per user/calendar month, in the application server time zone. Goal roadmaps, training blocks and weekly plans share the plan quota. Administrators can override both quotas in user management; blank restores the deployment default and zero prevents new requests in that category. Updates are audited. Existing pending work remains eligible when a quota is lowered.

Reservation locks serialize duplicate detection, quotas and shared outstanding capacity. A new request consumes quota even if it later fails; retries and duplicates do not consume another request. Removing an activity or analysis cannot reset the quota. The default pending limits are two requests per user and 100 across the installation. Configure `AI_MONTHLY_ACTIVITY_QUOTA`, `AI_MONTHLY_PLAN_QUOTA`, `AI_USER_PENDING_LIMIT` and `AI_PENDING_LIMIT` as needed.

Worker admission limits concurrent attempts (`AI_CONCURRENCY=2`) and starts per minute (`AI_REQUESTS_PER_MINUTE=20`). These limits apply across all four generation jobs and all workers sharing the database. Capacity waits release queued jobs without spending provider-failure retries; requests have a one-day deadline and allow up to three actual exceptions. A timed-out attempt's capacity lease expires after the configured provider timeout plus a margin; the monitor reports old outstanding work. Explicit synchronous AI deployments report capacity failures through the existing safe generation-failure flow.

`AI_MAX_OUTPUT_TOKENS=12000` caps generated output, including reasoning tokens. Too small a cap can produce incomplete results. The [official token-counting guide](https://developers.openai.com/api/docs/guides/token-counting) explains the output-token boundary. Queue supervision remains required; capacity limits do not start workers or prove worker health.

## Costs and budget warnings

Set `AI_PRICING_MODEL` to the exact model ID returned by your provider and supply all three USD prices per million tokens: `AI_INPUT_USD_PER_MILLION`, `AI_CACHED_INPUT_USD_PER_MILLION` and `AI_OUTPUT_USD_PER_MILLION`. RideDock deliberately ships no assumed current model prices. Verify rates against the [official pricing page](https://developers.openai.com/api/docs/pricing) and your project agreement. A model change requires another price review; a mismatched model remains unpriced.

Each attempt captures returned input, cached-input and output usage before JSON/schema/plan validation. Prices are snapshotted with the attempt and rounded upward to USD micro-units. Invalid results with reported usage still count. A missing usage report, missing cache split when rates differ, missing price or transport failure appears as unknown cost, rather than free usage. Summaries are estimates, not an invoice; reconcile them with the provider's billing data and other traffic using the project key. Budget warnings cannot bound unreported spend.

Set `AI_MONTHLY_BUDGET_USD` above zero and `AI_BUDGET_WARNING_PERCENT` (default 80) to enable an administrator warning. This is an alert threshold, not a hard spending stop. Anonymous monthly cost and unknown-attempt totals survive account deletion and retention so these operations cannot reset the operator's spend warning. User/feature/model breakdowns contain only retained personal usage records.

## Monitoring and retention

Run `php artisan app:monitor-operations --json` for a snapshot with queue age, running/outstanding counts, recent failures, average attempt duration, provider error/wait states and budget/cost completeness. Exit status is nonzero when attention is needed, suitable for an external monitor. Output contains no contexts or exception bodies.

`--record` additionally emits safe warning events to the database and normal application log. Budget warnings are deduplicated per month; other warnings per hour. To run every five minutes, enable `OPERATIONS_MONITOR_SCHEDULED=true` and supervise Laravel's scheduler. It uses one-server/overlap guards. This schedule performs monitoring only. No email or other external notification channel is configured automatically; route application logs or command exit status to the operator's monitoring system.

Events expire after 30 days. Personal usage/request records expire after 400 days, preserving the current quota month, through the existing preview-first `app:prune-data` maintenance command. The monitor's `--record` also expires old events. If neither command runs, records remain stored. Personal exports include usage and owner-scoped operational events; account deletion cascades those records, including guards against late in-flight writes. Anonymous monthly sums contain no account, activity or model identifiers.

Verify actual provider approvals, shared-cache topology, worker/scheduler supervision, log delivery and real provider quotas in deployment. Synthetic regression coverage does not establish those external conditions. The other [hosted-service launch requirements](HOSTING.md#public-launch-gate) remain open.
