# Changelog

All notable changes to RideDock are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Read-only Wahoo OAuth connection with paginated workout synchronization and private FIT-file import.

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
