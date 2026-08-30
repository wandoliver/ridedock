# Integrating the existing `opens` heatmap

The heatmap consumes normalized route geometry and remains independent of any activity provider.

## Implemented foundation

1. FIT imports normalize GPS records into `latlng` activity streams.
2. `StreamRouteGeometryProvider` restricts streams to the current user and optional date bounds.
3. The first and last 400 metres are removed server-side by default, matching the original `opens` behavior.
4. Routes are sampled and encoded as compact provider-neutral polylines before entering the page.
5. Leaflet and Leaflet.heat are locally bundled; OpenStreetMap supplies the visible base-map tiles.
6. PHP tests cover ownership, date filtering, privacy clipping and encoding. JavaScript tests cover decoding and heat-point generation.

`HEATMAP_PRIVACY_TRIM_M` and `HEATMAP_SAMPLE_EVERY` control the two processing defaults. Base-map tile requests go to OpenStreetMap, but FIT files, provider identifiers and route payloads are never sent there.

## Later scaling path

For larger archives, add date filters, cached geometry keyed by user and filter hash, and eventually a versioned vector-tile endpoint. These are intentionally deferred until the JSON-stream MVP needs them.

No provider token, external athlete ID or raw provider payload should enter the heatmap module. Private-zone masking and optional start/end clipping belong before geometry is returned to clients.
