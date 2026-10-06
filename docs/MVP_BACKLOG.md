# MVP backlog

## Foundation — done

- [x] Laravel 13 API project and SQLite default
- [x] Sanctum bearer-token authentication
- [x] provider interfaces and registry
- [x] canonical activity/source/stream/event schema
- [x] encrypted provider tokens and OAuth state protection
- [x] Hammerhead OAuth, webhook, activity and FIT adapter
- [x] idempotent sync with cursor resume and overlap window
- [x] Hammerhead FIT source-file archive
- [x] idempotent FIT enrichment for summary metrics and streams
- [x] RPE and notes API
- [x] versioned JSON analysis export — API only; CSV disabled as of 0.3.0
- [x] dashboard, activity archive and activity detail UI
- [x] provider-neutral heatmap with privacy trimming
- [x] measurement charts for recorded streams
- [x] connection settings UI

## AI analysis — done

- [x] Define the first AI use case and its user-visible output
- [x] Build a compact, schema-versioned training context from normalized data
- [x] Add heart-rate and power reference bands with dated profile history
- [x] Calculate weekly volume and whole-ride RPE effort distribution
- [x] Add measured sensor-based intensity distribution
- [x] Add comparable efficiency trends
- [x] Add transparent recorded session-RPE load and completed-week trends
- [x] Evaluate monotony and freshness: gated recorded-load metrics; no inferred readiness score without recovery data
- [x] Integrate an AI assistant without exposing provider credentials or raw location data by default
- [x] Generate a seven-day plan from goals, available time and recent training
- [x] Queue activity analyses and all planning levels with progress and safe failures

## v0.8.0 — provider operations and AI costs/capacity

### Provider operations

- [x] Add hosted approval/terms confirmation gates and an operator review procedure — actual provider approval remains an external prerequisite
- [x] Make user-triggered sync and existing webhook processing respect configurable provider API budgets and retry delays
- [x] Coordinate sync and token refresh across application servers with shared locks, preserving idempotent imports and cursor/overlap resume
- [x] Add centralized provider failure monitoring and per-user queued/waiting sync status without exposing credentials or provider payloads

Automatic periodic synchronization (Auto-Sync) remains deferred and is outside v0.8.0.

### AI costs and capacity

- [x] Add enforceable per-user analysis and training-plan quotas, administrator overrides and shared capacity limits
- [x] Add usage and estimated cost summaries by user, feature and model with explicit unknown costs and per-attempt records
- [x] Add configurable budget warnings for the operator
- [x] Add operational monitoring for queue backlog, latency, failures and capacity

The persistent AI queue, retries, input/output token recording and prompt versioning already existed. This release builds on those controls; see [operations setup](OPERATIONS.md). Broader hosted-service requirements and actual provider approvals remain in [HOSTING.md](HOSTING.md).

## Product follow-up

- [x] Add Wahoo as a second activity provider
- [x] Add provider disconnect endpoints and Wahoo deauthorization
- [x] Add separate user-controlled ride/original retention limits with preview and explicitly confirmed cleanup
- [x] Decode recorded SRAM AXS/FIT gear changes using synthetic protocol fixtures: native packed/components and explicitly equivalent developer fields, with visible counts and timeline
- [ ] Extend support for proprietary gear-field encodings if a documented schema and synthetic reproduction become available
- [x] Add explicit private-zone masking for detail maps, heatmaps and opt-in JSON routes
- [x] Add personal complete export, confirmed account deletion and operational retention — v0.7.0
- [ ] Add a deployment-specific privacy-policy template

## Project maintenance

- [ ] choose and clear the project name
- [x] baseline threat model and dependency audit — see the dated 0.3.0 security review and its remaining deployment/product work
- [x] CI for tests, formatting, static analysis, frontend builds and isolated installation checks
- [x] current fixtures reviewed, coordinate literals made synthetic and repository asset/secret-pattern guard added
