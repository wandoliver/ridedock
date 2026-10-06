# Data retention and private zones

Per-user data retention and private zones are available since 0.4.0 at **Settings → Data & privacy** (`/settings/privacy`), in German and English. Retention limits default to unlimited; existing data is removed only through explicitly confirmed cleanup. These controls are intended for local development and use. Ride/source cleanup still requires confirmation; version 0.7.0 adds separate operational retention, personal archives and account deletion below.

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

JSON routes remain opt-in through `include_route=1`. `route_segments` contains individually encoded visible polylines. `summary_polyline` is provided only for one contiguous visible segment; it is null when the route is split or completely hidden. Clients must draw segments separately. Without explicit inclusion, neither field is returned. CSV stays disabled with HTTP 422. The separate personal ZIP download described below is not a replacement for this analysis endpoint.

Apply the additive migration with `php artisan migrate`. It adds nullable retention fields, source purge markers and the `privacy_zones` table without changing existing limits or removing existing data. Run `composer check` for the owner, cutoff, retry, reimport and route-gap regression checks.

Map tiles still come from OpenStreetMap, including the zone-selection map. Tile requests expose the viewed map area to that service. Google Fonts still supplies browser fonts. These settings do not change those third-party requests. Operators must supply deployment-specific privacy information.


## Personal archive — v0.7.0

At **Settings → Data & privacy**, enter your current password and explicitly confirm inclusion of sensitive original data to download `ridedock-personal-data.zip`. Requires PHP's ZIP extension. The download is authenticated, CSRF protected, throttled and marked `no-store`; submitted passwords are never flashed into the session.

`manifest.json` documents schema version 1 and the archive boundary. Each `.ndjson` dataset contains one JSON object per line, with database JSON fields decoded into structured values. Datasets cover the account, athlete and historical training-zone profiles, private zones, provider connections, activities (including notes/RPE and unmasked routes), raw source payloads/metadata, measurement streams, events, AI contexts/outputs and plans, invitation metadata, API-token metadata, database-session metadata and account audit events involving the owner. Available original FIT files are copied into `files/`, with each source row referencing its archive filename. Previously purged originals cannot be recovered; a referenced but unreadable file fails the download instead of silently producing an incomplete archive.

Password hashes, remember/session tokens, OAuth credentials, API-token hashes, invitation token hashes and session payloads are excluded. Operator logs, operational job payloads and backups are not portable personal datasets. Other users' data is excluded. This archive contains exact GPS positions and private-zone centers after explicit confirmation; the analysis API remains masked. Keep downloaded archives private; account deletion cannot erase a copy already saved to your device.

Generation holds the account lock so imports/file cleanup cannot change the archive mid-generation, uses private temporary files rather than a publicly accessible archive directory, and closes/removes temporary files on completion or failure. Large archives may take time; the current flow is synchronous and does not send download links by email.

## Complete account deletion — v0.7.0

In the same settings, enter your current password and confirm permanent deletion. The account lock serializes deletion with imports and source-file writes. Deletion removes referenced original files plus orphan files inside the account's private archive directory before removing database rows. A file-removal failure keeps the account and source references for retry; some files may already be gone and are not recoverable. Unsafe/shared file references stop deletion rather than affect another user's files.

Deletion removes all account-owned activities, streams, events, notes, analyses/plans, profiles, private zones, invitations, provider connections and their encrypted credentials. It also explicitly removes polymorphic API tokens, database sessions, password-reset tokens, related account audit events, indexed pending OAuth states and owner-scoped pending/failed jobs. Existing untagged job payloads are inspected without deserialization while their source rows still exist. Jobs referencing missing models are discarded; late database enqueues and failures for deleted accounts are removed. A retry of completed deletion is harmless.

This flow supports `sync`/`database` provider and AI queues and `database-uuids`/`null` failed-job storage. It fails before file deletion for unsupported backends. Custom Redis/SQS/file queue storage needs a corresponding owner-aware cleanup integration first. Restart all workers after upgrading so new jobs receive ownership metadata. Pending OAuth states created by older code expire within ten minutes and cannot authenticate after the account is removed.

Deletion logs the current browser out, and other database sessions and API credentials cease to authenticate. No web endpoint accepts another user's account ID for export or deletion. Administrators can also delete their own account; provision another administrator beforehand if continued administration is needed. Scheduled inactivity pruning exempts all administrators.

Provider-hosted recordings and remote authorization grants, third-party processing, browser downloads and backup snapshots are separate from local account deletion. Disconnect providers first if you also want RideDock to attempt supported remote deauthorization. Already running external requests cannot be recalled; their results cannot recreate deleted database rows or private files.

## Operational retention

The operator configures these independent windows in `.env`:

| Data | Setting | Default | Expiry reference |
| --- | --- | --- | --- |
| Inactive active member accounts | `INACTIVE_ACCOUNT_DAYS` | 730 days | Last authenticated browser/API use, otherwise creation |
| Never-activated member accounts | `PENDING_ACCOUNT_DAYS` | 30 days | Account creation |
| Failed jobs | `FAILED_JOB_DAYS` | 7 days | Failure timestamp |
| Account audit events | `ACCOUNT_AUDIT_DAYS` | 90 days | Event creation |
| Local application logs | `LOG_DAILY_DAYS` | 14 days | Daily rotation; old file modification time during maintenance |
| Managed backup files | `BACKUP_RETENTION_DAYS` | 30 days | File modification time |

Windows must be positive whole days. Entries exactly on the cutoff remain until the next run. Member inactivity deletion uses the same complete deletion path; provider sync and background AI work do not count as user activity. Authenticated use refreshes `last_active_at` at most once per five minutes. Migration starts existing accounts' inactivity windows at upgrade time. Pending accounts always use their creation timestamp; review the preview before enabling maintenance on an old installation.

```bash
php artisan app:prune-data
php artisan app:prune-data --execute
```

The first command previews counts and expired files without deleting anything. The second applies account, audit, failed-job, local-log and managed-backup retention. Set `DATA_LIFECYCLE_SCHEDULED=true` and run Laravel's scheduler for daily maintenance at 03:00 in the application's configured timezone. The schedule uses an overlap lock. Automatic cleanup is disabled by default; configured windows are not enforced without manual execution or a working scheduler. Check command failures and rerun after fixing storage/backend problems. Ride/original retention settings continue to use their separate confirmed cleanup flow.

The `daily` and backward-compatible `single` logging channels now both rotate daily with `LOG_DAILY_DAYS`; maintenance also removes stale `storage/logs/laravel*.log` files, including the old single log. Clear cached configuration and restart workers to apply rotation. Custom file paths, centralized logging, mail transports and infrastructure logs require their own expiry policy. Never log source payloads, precise routes, credentials or invitation URLs in production.

Only the dedicated private `lifecycle_backups` disk (`storage/app/private/lifecycle-backups`) is automatically pruned. RideDock does not create backups or encrypt them. Put only backup artifacts governed by this policy there, encrypt them before storage and retain restrictive permissions. File modification time must reflect backup creation; copying/touching an old backup may extend its lifetime. Remote disks with object versioning require expiry of non-current versions too.

## Backup deletion and restoration

Account deletion does not rewrite historical database/FIT snapshots. Deleted data may remain in an encrypted backup for up to the configured window **when maintenance runs successfully**. External snapshot services, replicas, off-site copies and exported operator backups need equivalent expiry rules; the app cannot verify or enforce those providers' policies. Publish the actual retention period for every backup destination.

Keep a restricted operational erasure register outside the recoverable backup set, containing only identifiers needed to reapply deletion and an expiry aligned with the oldest remaining backup. Before putting a restored database online, isolate it from all outbound sync/AI workers, reconcile deletions that happened after the snapshot, purge the restored accounts/files and queue work, then invalidate all restored sessions and API/OAuth credentials. Verify that erased accounts cannot log in and their source files no longer exist. Only resume service after those checks. Remove erasure-register entries after all affected snapshots expire. Do not restore expired backups into normal service.
