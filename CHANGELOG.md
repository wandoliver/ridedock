# Changelog

All notable changes to RideDock are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.0] - 2026-10-06

### Added

- Hosted provider approval/terms confirmation gates and shared-cache requirements, with configurable application-wide API budgets and shared cooldowns for provider limits/outages.
- Cross-server sync/token-refresh locks, resumable detail/overlap checkpoints, queued/waiting connection states and delayed processing of requested sync/webhook work. Automatic periodic synchronization remains deferred.
- Monthly per-user ride/plan quotas with audited administrator overrides, shared pending/concurrent/request-start limits and capacity deferrals that do not spend provider-failure retries.
- Per-attempt token/cache usage and snapshotted estimated USD costs, including invalid responses; unknown costs remain explicit. Anonymous monthly spend totals survive personal data deletion.
- German/English personal AI usage and administrator operations views, configurable budget warnings and a CLI/opt-in scheduled monitor with bounded safe events and log output.
- Synthetic regression coverage for quotas, admission, real queue releases, resumable sync, token rotation, hosted gates, ownership and budget accounting; operational setup and remaining external deployment prerequisites.

### Changed

- Database provider queue reservations last at least 360 seconds for 300-second jobs; provider jobs use bounded deadlines and three actual exceptions instead of spending retries on capacity waits.
- Personal export/deletion and preview-first retention include operational usage/events. New migration backfills quota requests and available historical token usage without inventing past retry costs.

## [0.7.0] - 2026-10-06

### Added

- Password-confirmed personal ZIP export in German/English Data & privacy settings, with structured account/training/activity/source/stream/event/AI data and available original FIT files; precise locations require explicit confirmation and authentication secrets are excluded.
- Password-confirmed complete account deletion, including private source and orphan files, API/OAuth credentials, pending OAuth states, invitations, profiles, routes, analyses/plans, database sessions and owner-scoped queued/failed jobs.
- Configurable inactive/pending member, failed-job, audit, application-log and dedicated private-backup retention; preview-first CLI maintenance and an operator-enabled daily schedule.
- Account-use timestamps with a fresh upgrade window, missing-model job cancellation, late-enqueue/failure guards and synthetic export/deletion/retention regression coverage.

### Changed

- Default and legacy `single` application logging use bounded daily rotation; deployment guides explain managed versus external backup expiry and restore handling.
- Personal archives contain unmasked originals, independently of private-zone masking in the existing JSON analysis export. Temporary export files are private and removed after generation/download.

## [0.6.0] - 2026-10-06

### Added

- Administrator and member roles, explicit CLI administrator creation/promotion, and German/English user management with pending-account status.
- Administrator-created activation links and optional synchronous email invitations, bound to a specific account, hashed at rest, expiring after seven days and consumed once under transaction locks.
- Invitation renewal/revocation, account administration audit events, sensitive-page cache/referrer protection and regressions for authentication, authorization, activation and token handling.
- Account administration and mail-setup documentation; fresh-install verification covers the removal of registration even with cached routes.

### Changed

- Remove public registration routes, controller, request and form; direct GET/POST registration attempts return 404.
- Existing accounts retain their credentials and become active members; administrator access is granted explicitly by CLI.
- API-token creation requires an existing active account and no longer provisions users; pending accounts cannot authenticate or access browser/API endpoints.

## [0.5.2] - 2026-10-06

### Changed

- Consolidate data-retention/private-zone and FIT gear-shifting details in their existing guides under `docs/`, keeping the README focused on the project overview, quick start and documentation links.

## [0.5.1] - 2026-10-05

### Fixed

- Replace the generic empty gear-shifting message with distinct states for absent values, field descriptions without recorded values, unsupported gear-value formats and incomplete shift messages.
- Treat previous analyses without current diagnostics as unverified and offer reprocessing instead of claiming that their original FIT contained no gearing data.
- Persist gear diagnostics with parser metadata and explain that pairing the electronic drivetrain with the recording bike computer is required to capture gearing.

### Added

- Synthetic, CRC-valid description-only FIT coverage and regression checks for diagnostic persistence and localized empty states, without copying rider recordings into fixtures.

## [0.5.0] - 2026-10-05

### Added

- Recorded gear-shifting details under ride Measurements: front/rear counts, timestamped gear positions, tooth counts and ratios when both tooth counts are recorded, with German and English labels.
- Owner-only reprocessing of an existing archived FIT file from the ride page, preserving notes/RPE and respecting purged-original markers without downloading from providers.
- CRC-valid, entirely synthetic native and developer-field FIT fixtures, plus regression checks through the real parser, database and rendered ride page.
- A gear-shifting guide documenting supported encodings, fixture provenance, reprocessing and recording limits; unknown proprietary SRAM AXS formats remain unsupported without an explicit native mapping.

### Fixed

- Decode packed FIT gear-change data into rear/front positions and tooth counts; accept self-described developer fields only when their metadata identifies equivalent native event fields.
- Skip incomplete, ambiguous or untimestamped shifts, preserve unknown 1x front values, sort chronologically and deduplicate equivalent timestamped events.
- Use the same ride start for the normalized summary and elapsed event times when session start is absent.

## [0.4.0] - 2026-10-05

### Added

- German and English Data & privacy settings with independent ride and original-data retention limits, unlimited by default.
- Existing-data cleanup with affected-record preview, explicit confirmation, stale-preview rejection and private-file deletion; whole-ride cleanup also removes user AI analyses/plans that may retain ride summaries.
- Named private zones selected on a map or by coordinates, applied on the server to ride details, heatmaps and explicitly included JSON routes.
- Segmented route output that removes crossing edges and preserves gaps, including dateline and straight projected map segments.
- Documentation and synthetic regression checks for ownership, retention cutoffs, cleanup retries, reimports and route masking.

### Changed

- Import and file-processing paths honor retention limits and removed-original markers; deleted enrichment/AI work is skipped.
- JSON routes expose masked `route_segments`; `summary_polyline` is null when split or fully hidden. Routes remain opt-in and CSV remains disabled.
- Short heatmap routes that cannot survive endpoint trimming are omitted.
- Remove tagged releases and container deployment from the current backlog while development remains exclusively local.

## [0.3.0] - 2026-10-05

### Added

- A dated security review and threat model, fixture provenance policy and documented remaining deployment/product work.
- An offline repository asset/secret-pattern guard in local quality checks and the existing PHP 8.4 CI quality job.

### Changed

- Disable the CSV API export for now; `format=csv` returns HTTP 422 without a download. JSON export remains documented in the API guide.
- Use clearly synthetic coordinates for location fixtures and ignore common private activity/database/environment files.
- Require cURL, public HTTPS targets on port 443 and direct responses for external FIT downloads; redirects and proxies are disabled and the validated address is pinned.

### Security

- Bind OAuth callbacks to the initiating account and web session, consume state under a lock and reject replay before exchanging authorization codes.
- Bound FIT download and archive size, and refuse public or serving archive disks.
- Replace external response details in OAuth, sync, queued import, file-download and AI HTTP failures with safe application messages; mask legacy connection errors in the API.
- Prevent the sample account from being seeded outside local/testing environments.
- Update the transitive npm dependency `brace-expansion` from 5.0.9 to 5.0.12; Composer and npm audits report no known vulnerabilities at review time.

## [0.2.1] - 2026-10-05

### Changed

- Standardize the PHP baseline and all CI jobs on PHP 8.4; remove the backend version matrix and align Composer and setup documentation.

### Fixed

- Give the Wahoo reconnect test explicit synthetic OAuth configuration so it passes on a fresh CI checkout without local provider credentials.
- Disable providers and clear OAuth credentials in PHPUnit regardless of inherited environment values, preventing local configuration from hiding incomplete test fixtures.

## [0.2.0] - 2026-10-05

### Added

- GitHub Actions CI for pushes and pull requests to main, plus manual runs: backend tests on PHP 8.3/8.4, code quality, Node 24 frontend tests/build and a fresh-installation check.
- Isolated installation verification for SQLite migrations, Laravel configuration/route/view caches and login rendering with production assets; temporary database, environment and cache files are cleaned up.
- A CI status badge and a guide to local checks and GitHub job logs.

### Changed

- Align `composer check` with CI, including manifest/lockfile validation and a read-only PHP-CS-Fixer check covering application, tests and PHP scripts.
- Mock Vite in backend tests and reject unmocked Laravel HTTP-client requests by default.
- Standardize supported Node versions at 22.13+ within Node 22, or Node 24+; recommend Node 24 and align contribution instructions with PHP-CS-Fixer.

### Fixed

- Resolve Composer dependencies against PHP 8.3.0 and use compatible Symfony 7.4 components; the previous lockfile required PHP 8.4.1 despite the documented PHP 8.3 minimum.
- Make the weekly-plan precedence test deterministic by setting its clock and an explicitly historical ride date.

### Security

- Update Laravel to 13.34.0 and league/commonmark to 2.10.3 to address the dependency advisories found during the lockfile review.

## [0.1.1] - 2026-10-05

### Changed

- Reduce the README to a project overview, quick start and documentation links.
- Move setup, AI configuration, API examples and training methodology into focused guides under `docs/`; consolidate provider and architecture documentation.
- Move deployment and managed-hosting guides into `docs/` and update their references.

## [0.1.0] - 2026-10-05

### Added

- A shared responsive sidebar, contextual page headers, accessible section navigation, and a consistent typography, spacing and button system.
- A dedicated Insights page for volume/load, sensor zones, efficiency and route heatmaps.

### Changed

- Reduce the overview to the next session, current-week totals and three recent activities.
- Restructure ride details around a compact elevation profile and rating, with separate Measurements, Coach and Notes sections; save ratings and notes independently.
- Open Coach on the weekly plan and replace cramped day columns with a responsive agenda and selected-day details.
- Group training settings into Profile & goal, Availability, Measurements & zones and AI & privacy; align Connections and authentication with the shared interface.

### Fixed

- Preserve archive filters, pagination and Coach context when returning from ride details, including scroll position when session storage is available.
- Restore section/day selections with browser Back/Forward, retain the active section after saving or requesting an analysis, and expose hidden form sections when validation fails.
- Restrict contextual return links to approved local destinations.

## [0.0.13] - 2026-10-02

### Added

- Seven-day recorded-load monotony and strain, and descriptive 42-day versus seven-day load balance on the dashboard.
- Daily values, rating coverage, formulas and explicit unavailable states for short history, missing ratings, no load or zero variation.
- Complete-day load aggregates in activity analyses and all planning levels, excluding subsequent recordings.

### Changed

- Complete the monotony/freshness evaluation: recovery and training readiness remain explicitly unmeasured, with no inferred freshness score.
- Bump AI context and prompt versions to preserve the data limitations.

## [0.0.12] - 2026-10-02

### Added

- Private, user-controlled activity comparison groups and a six-week efficiency comparison table.
- Time-weighted W/bpm measurements from continuous, steady power/heart-rate windows, with explicit eligibility and comparable-workload checks.
- Qualified numerical efficiency aggregates in activity AI contexts; group labels are excluded.

### Changed

- Bump AI context and prompt versions for the descriptive efficiency metric and its limitations.

## [0.0.11] - 2026-10-02

### Added

- Time-weighted power and heart-rate zone distributions on activity details and the six-week dashboard, including sensor coverage and unknown time.
- Aggregate sensor distributions in activity analysis and planning contexts, using each ride’s dated reference profile.

### Changed

- Keep exactly maximum heart rate inside H5 and expose above-reference measurements separately.
- Bump AI context and prompt versions for measured sensor aggregates.

## [0.0.10] - 2026-10-02

### Added

- Dated FTP and maximum-heart-rate snapshots, append-only measurement history, and continuous power/heart-rate reference bands in training settings.
- Owner-scoped historical measurements with validation and current-reference preservation when backdating.

### Changed

- Activity reference ratios use the values effective at ride start; normal profile edits affect subsequent rides. Existing values are migrated as known from the migration time, without inventing earlier measurement dates.
- AI contexts and prompts are versioned for dated reference profiles. Run `php artisan migrate` after updating.

## [0.0.9] - 2026-10-02

### Added

- A six-week training overview with weekly volume, duration-weighted whole-ride RPE distribution, recorded load, rating coverage and accessible weekly details in English and German.
- Completed-week volume and load comparisons against the previous four weeks, with explicit handling of partial data and zero baselines.

### Changed

- Activity analyses and all planning levels share the same training statistics and expose missing load as unknown, with updated context and prompt versions.
- Future training plans use actual recent history, rather than an empty history window relative to a distant planning date.

## [0.0.8] - 2026-10-02

### Changed

- AI activity analyses, roadmaps, four-week blocks and weekly plans run on a dedicated persistent database queue by default; local development starts its worker automatically.
- Ride and Coach pages track outstanding requests and refresh automatically when results or final errors are available, while retaining completed plans during regeneration.

### Fixed

- Duplicate submissions reuse outstanding AI analyses, and completed jobs do not call the provider again on redelivery.
- AI jobs allow three attempts for provider failures, record safe final errors, and use reservation and worker timeouts consistent with the configured provider timeout.

## [0.0.7] - 2026-09-08

### Added

- Disconnect controls for Hammerhead and Wahoo, with authenticated web and API endpoints, disconnected status and reconnect support.
- Wahoo permission revocation with local cleanup even when the provider is unavailable.
- RideDock SVG, ICO and Apple touch icons derived from the existing green logo.

### Security

- Disconnect clears stored access/refresh tokens, account identity, scopes and connection metadata while preserving imported rides.
- Queued imports and syncs skip disconnected connections; conditional updates prevent stale syncs and token refreshes from restoring credentials or connection status.

## [0.0.6] - 2026-09-07

### Added

- Calendar-based Coach with roadmap, block and week zoom levels, selectable detail panels and navigation through saved weekly plans.
- Planned-versus-recorded training views with automatic date matching and manual activity assignment.
- Persistent plan-day completion with undo, plus session swaps between days without changing the planned total duration.
- Weekly volume charts with planned targets on the dashboard and combined speed, heart-rate and elevation charts on activity details.

### Changed

- Dashboard, activity archive and details, Coach, settings, login and registration now share the light calendar design with Hanken Grotesk, consistent components and responsive detail panels.
- Activity details show linked plan sessions and a segmented RPE control that saves rating changes immediately.
- Weekly plans can be generated for a selected start date.

### Fixed

- Invalid imported activity timezones fall back to the application timezone instead of breaking calendar and activity views.
- Sessions explicitly marked as completed are skipped when resolving the next planned session.

### Security

- Plan-day changes and manual activity assignments are validated and restricted to the owning user.

## [0.0.5] - 2026-09-02

### Fixed

- Activity analyses now receive the recorded average speed, preventing available pace data from being reported as missing.
- Activity details now skip an already completed current-day session when showing the next day from the weekly plan.

## [0.0.4] - 2026-09-01

### Added

- English and German interfaces with a persistent display-language preference in the training profile settings.
- Locale-aware dates, numbers, status messages and AI-generated training guidance.

### Changed

- Fresh installations and newly registered accounts now use English by default, while existing accounts retain the previous German experience.

## [0.0.3] - 2026-08-31

### Added

- Read-only Wahoo OAuth connection with paginated workout synchronization and private FIT-file import.
- Trusted local HTTPS development proxy for testing OAuth callbacks, including HTTPS support for the Vite development server.
- Editable 1–10 RPE and private activity notes on the ride detail page.
- OpenAI setup guidance plus production deployment and managed-hosting readiness documentation.
- Long-term AI training roadmaps split into contiguous phases through the athlete's target date.
- Four-week training blocks with weekly load levels, target volume, key sessions and adaptation rules.

### Changed

- Connection settings now expose live connection and synchronization controls for both Hammerhead and Wahoo.
- Provider documentation now covers the implemented Wahoo OAuth, polling and FIT workflow.
- Seven-day plans now use the current goal roadmap and its matching four-week block as strategic context.
- The Coach presents planning as a hierarchy from target roadmap to training block to concrete week.
- The Coach separates roadmap, four-week block, weekly plan and ride analyses into compact tabs instead of one long page.

### Fixed

- Synchronous AI requests extend PHP's execution limit to the configured provider timeout plus a small grace period instead of failing at a shorter 30-second web limit.

### Security

- Web-based RPE and note updates are restricted to the owning user and validated server-side.
- Wahoo requests only read access and keeps webhook handling disabled until explicitly configured.

## [0.0.2] - 2026-08-30

### Added

- Training profiles with goals, availability, time budget, experience and optional performance values.
- Privacy-conscious OpenAI activity analyses with structured training effects, observations and recommendations.
- AI-generated seven-day training plans based on the athlete profile and the previous six weeks of normalized activities.
- Visible progress indicators for activity analyses and weekly-plan generation.
- Manual Hammerhead synchronization with visible progress feedback from the connection settings.

### Changed

- AI analyses and provider imports run synchronously by default without requiring a queue worker.
- FTP, heart rate, power and cadence are treated as optional signals rather than required analysis inputs.
- The Coach page provides direct access to activity analyses, training profiles and generated weekly plans.
- An active seven-day plan takes precedence over a conflicting next-session recommendation from an individual activity analysis.

### Fixed

- Hammerhead distances and durations use the confirmed API units, preventing synchronized rides from being inflated by a factor of 1,000.

### Security

- AI requests exclude GPS coordinates, FIT files, provider payloads and activity notes.
- OpenAI Responses are requested without server-side response storage.

## [0.0.1] - 2026-08-30

### Added

- Hammerhead/Karoo OAuth connection, webhook handling and queued activity synchronization.
- Idempotent activity imports with resumable pagination and conservative duplicate detection.
- Private FIT-file archival with checksum validation and idempotent enrichment.
- Normalized activities, source provenance, measurement streams and FIT events.
- Extraction of route, heart-rate, cadence, power, speed, altitude and SRAM AXS shift data from FIT files when available.
- Private user accounts with registration, login and isolated activity data.
- Dashboard with selectable time periods, aggregate metrics and recent activities.
- Provider-neutral route heatmap with mouse-wheel zoom, privacy trimming and an enlarged inline view.
- Paginated activity archive and activity details with route and measurement charts.
- RPE and notes support plus versioned JSON and CSV analysis exports.
- Connection settings with Hammerhead status and a Wahoo placeholder.
- PHPUnit, PHPStan, PHP_CodeSniffer, PHP-CS-Fixer, EditorConfig and ESLint quality checks.

### Security

- OAuth tokens are encrypted at rest.
- Raw activity files are stored on a private, non-serving disk.
- Activity pages, exports and route geometry are restricted to their owner.
- Heatmap routes trim their recorded start and end by default.
