# Changelog

All notable changes to RideDock are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
