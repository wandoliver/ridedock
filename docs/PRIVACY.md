# Data retention and private zones

Per-user data retention and private zones are available since 0.4.0 at **Settings → Data & privacy** (`/settings/privacy`), in German and English. Retention limits default to unlimited; existing data is removed only through explicitly confirmed cleanup. These controls are intended for local development and use. There is no scheduled cleanup or automatic synchronization.

## Retention

Two independent limits cover rides and original files/raw provider payloads. Each defaults to unlimited; available limits are 30, 90, 180, 365 and 730 days. Age is measured from the ride's start timestamp, not its import timestamp. A ride exactly on the cutoff is retained.

Saving changes does not delete existing data. The cleanup section previews affected ride, original-source and AI-record counts using the saved settings. Cleanup requires an explicit checkbox confirmation; changed settings/counts invalidate an old preview. RideDock never deletes data from the provider.

- **Originals only:** remove archived FIT files, checksums, raw provider payloads and source metadata. Keep normalized rides, notes, streams, shift events and AI analyses. Minimal source identities remain for deduplication. A purge marker prevents later imports restoring these removed originals, even if the original-data limit is expanded. New sources outside the current original-data window import normalized summaries without archiving FIT files; measurement streams are therefore unavailable for those new sources.
- **Whole rides:** delete expired activities and their dependent sources, streams and events, including private FIT files. Also delete all the user's AI analyses and plans because aggregate contexts/outputs may reference removed rides. Training profile, zone history, private zones and provider connections remain. Subsequent imports skip rides outside the selected ride window. Expanding that window later may allow previously deleted rides to be imported again.

Original-data cleanup alone does not remove GPS streams or summary routes. Use whole-ride cleanup when those records should also be removed. User settings, imports, file archiving and FIT enrichment coordinate through database locks; missing/purged enrichment sources and deleted AI jobs are skipped. An AI request already in flight cannot be recalled; saving its result does not recreate a deleted analysis.

File deletion failures stop cleanup, retaining database references for a retry. Filesystem deletion is not transactional: files already removed before a later failure cannot be recovered by a database rollback. Retry cleanup to finish. Backups, browser downloads, application/failed-job logs and externally processed data are outside this control.

## Private zones

Create up to 20 named circular zones by selecting a center on the map, or entering coordinates, and a radius between 100 and 5000 meters. Remove a zone to reveal its route sections again. Centers are displayed only in the owning user's settings, never as part of activity or route exports.

Masking runs on the server before route coordinates reach the browser or API. It applies to ride detail maps, heatmaps and explicitly requested JSON routes. Stored original data and sensor charts remain unchanged; this is display/export masking, not deletion or anonymization of the database.

Hidden points split a route into separate segments. Edges crossing a zone are removed even if both recorded endpoints are outside it. The implementation checks great-circle arcs and conservative projected bounds for straight map lines, with a 10-meter safety margin for rounding. Some nearby visible sections may therefore also be suppressed. Sampling is disabled for masked heatmap routes, and browser line simplification is disabled, to avoid introducing shortcuts through hidden sections. Invalid input also breaks a route; short fragments are omitted.

The existing heatmap start/end distance trim remains active in addition to private zones. Routes too short to survive that trim are omitted. Detail maps and API routes apply explicit zones without the heatmap-only distance trim.

## API and local operation

JSON routes remain opt-in through `include_route=1`. `route_segments` contains individually encoded visible polylines. `summary_polyline` is provided only for one contiguous visible segment; it is null when the route is split or completely hidden. Clients must draw segments separately. Without explicit inclusion, neither field is returned. CSV stays disabled with HTTP 422; there is no new export button.

Apply the additive migration with `php artisan migrate`. It adds nullable retention fields, source purge markers and the `privacy_zones` table without changing existing limits or removing existing data. Run `composer check` for the owner, cutoff, retry, reimport and route-gap regression checks.

Map tiles still come from OpenStreetMap, including the zone-selection map. Tile requests expose the viewed map area to that service. Google Fonts still supplies browser fonts. These settings do not change those third-party requests and do not implement full account deletion, backup retention or a hosted-service privacy policy.
