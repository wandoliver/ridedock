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
- [x] versioned JSON and CSV analysis export
- [x] dashboard, activity archive and activity detail UI
- [x] provider-neutral heatmap with privacy trimming
- [x] measurement charts for recorded streams
- [x] connection settings UI

## AI analysis — in progress

- [x] Define the first AI use case and its user-visible output
- [x] Build a compact, schema-versioned training context from normalized data
- [x] Add heart-rate and power reference bands with dated profile history
- [x] Calculate weekly volume and whole-ride RPE effort distribution
- [ ] Add sensor-based intensity distribution and comparable efficiency trends
- [x] Add transparent recorded session-RPE load and completed-week trends
- [ ] Evaluate monotony and freshness estimates with suitable data
- [x] Integrate an AI assistant without exposing provider credentials or raw location data by default
- [x] Generate a seven-day plan from goals, available time and recent training
- [x] Queue activity analyses and all planning levels with progress and safe failures

## Product follow-up

- [x] Add Wahoo as a second activity provider
- [x] Add provider disconnect endpoints and Wahoo deauthorization
- [ ] Add user-controlled retention choices
- [ ] Add rate-limit-aware scheduling, distributed locks and per-user sync status
- [ ] Decode SRAM AXS developer fields into `shift` events using real fixtures
- [ ] Add explicit private-zone masking and opt-in route export
- [ ] Add GDPR export/deletion flows and a deployer privacy-policy template

## Release checklist

- [ ] choose and clear the project name
- [ ] threat model and dependency audit
- [ ] CI for tests, formatting and static analysis
- [ ] fixtures scrubbed of personal/location data
- [ ] confirm API terms for every hosted provider integration
- [ ] tagged `0.1.0` release and container deployment example
