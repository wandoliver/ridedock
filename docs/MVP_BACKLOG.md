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

## Product follow-up

- [x] Add Wahoo as a second activity provider
- [x] Add provider disconnect endpoints and Wahoo deauthorization
- [x] Add separate user-controlled ride/original retention limits with preview and explicitly confirmed cleanup
- [ ] Add rate-limit-aware scheduling, distributed locks and per-user sync status
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
- [ ] confirm API terms for every hosted provider integration — deferred while development remains exclusively local
