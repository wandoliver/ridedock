# Architecture

## Data flow

```text
Hammerhead/Karoo ─ OAuth + webhook + FIT ─┐
Wahoo           ─ OAuth + polling + FIT ──┴─ Provider adapter ─ Normalize
                                                                    │
                                                                    ▼
                                                        activities + sources
                                                        streams + events
                                                                    │
                                                ┌──────────────────┴───────────────┐
                                                ▼                                  ▼
                                        JSON analysis API                    Heatmap module
```

The provider payload remains available in `activity_sources.raw_payload`, while application code reads the normalized `activities` record. Raw source files use the non-serving `activity_sources` disk and are never returned through the API.

## Principles

1. The normalized activity is the product; providers are replaceable input adapters.
2. Every import is idempotent and keeps provenance.
3. Original payloads and source files are retained privately so normalization can improve without re-downloading.
4. Location data and OAuth secrets are private by default.
5. Features such as heatmaps and training load consume internal contracts, never provider payloads.

## Module boundaries

- `Domain/Activities/Contracts`: stable provider and enrichment interfaces.
- `Domain/Activities/Data`: immutable provider-neutral transfer objects.
- `Domain/Activities/Services`: token refresh, normalization, deduplication and sync orchestration.
- `Infrastructure/Providers`: Hammerhead- and Wahoo-specific HTTP and field mapping behind provider contracts.
- `Modules/Heatmap`: provider-neutral route geometry and heatmap rendering.
- `Http`: authenticated API, OAuth callbacks and narrow webhook entry points.

## Import sequence

1. OAuth callback stores the access and refresh tokens using Laravel's encrypted cast.
2. A connection sync refreshes an expiring token.
3. The provider returns one normalized page plus an opaque next cursor.
4. `ActivityImporter` first matches the exact provider/external ID, then a conservative ride fingerprint.
5. A source row is upserted and the normalized activity is merged according to provider priority.
6. When a provider exposes a FIT source file, it is saved to a private disk with a hashed filename and checksum.
7. The cursor is persisted after each page. A completed sync clears it and records `last_synced_at`.

Imports may fill fields that are still null, but RPE and notes are never overwritten.

## Processing and failure behavior

Provider imports run synchronously by default, without a separate queue worker. A failed sync records a bounded error on the connection and retains its page cursor. Re-running the sync is safe because source keys and activity fingerprints are unique. Deployments may opt into an asynchronous provider queue; that requires a worker and does not change the domain services. AI analyses, goal roadmaps, training blocks and weekly plans use the separate persistent `ai` database queue by default. Owner-scoped reservations prevent duplicate outstanding requests; unique jobs retry provider failures and record safe terminal errors. The UI polls an authenticated status-only endpoint and refreshes on completion or failure. `composer run dev` starts the AI worker automatically; production must supervise it independently of provider workers.

## FIT enrichment

`ActivitySourceEnricher` is implemented by the FIT enrichment worker. It:

- validates the checksum and parses the archived file;
- makes FIT session totals authoritative for distance, duration and sensor summaries;
- upserts time/distance series into `activity_streams` (`latlng`, `heart_rate`, `cadence`, `power`, `speed`, `altitude`);
- upserts AXS shifts into `activity_events` with provider-specific details confined to the event's JSON data;
- records parser name/version in source metadata and remains idempotent.

## Heatmap

The heatmap consumes only normalized `latlng` streams through `RouteGeometryProvider`. Ownership filtering, start/end privacy clipping and sampling happen server-side before compact encoded polylines reach the browser. The browser decodes those routes and renders a frequency heatmap with locally bundled Leaflet libraries.

## Scaling path

SQLite is suitable for one rider. PostgreSQL is recommended for a hosted multiuser instance. Large streams can later move from JSON rows to compressed object storage without changing the activity/export contracts. Provider syncs can move to Redis queues independently when asynchronous processing becomes useful.
