# Data model

## `provider_connections`

One row per user/provider. Tokens use Laravel encrypted casts. It owns sync state, OAuth scopes, provider capabilities and external user identity.

## `activities`

Canonical ride record. SI units are used internally:

- distance in metres;
- duration in seconds;
- speed in metres per second;
- elevation in metres;
- heart rate in bpm;
- cadence in rpm;
- power in watts and energy in kJ.

It also owns user-entered `rpe` (1–10) and `notes`, which imports never replace.

## `activity_sources`

Provenance and idempotency link between an activity and an external provider ID. It retains the original JSON payload, source timestamps, private source-file location and checksum. Re-importing the same Hammerhead ride updates its existing source row instead of creating another activity.

## `activity_streams`

Provider-neutral sampled series. `type` identifies values such as `latlng`, `heart_rate`, `cadence`, `power`, `speed` or `altitude`; `series_type` identifies time or distance alignment. JSON is an MVP storage choice, not an API commitment.

## `activity_events`

Sparse occurrences such as `shift`, `lap`, `pause` and `resume`. AXS front/rear gear, component ID or battery facts can live in `data` without polluting the canonical activity schema.

## Deduplication

Exact idempotency uses `(provider_connection_id, external_id)`. The model also supports conservative cross-provider matching by user, UTC start minute, distance rounded to 100 m and moving time rounded to one minute when another provider is added. Ambiguous matches should be reviewed before this heuristic is made more permissive.
