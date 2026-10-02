# Changelog

All notable changes to RideDock are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
