# FIT gear shifting / SRAM AXS

Available since **0.5.0** under **Ride → Measurements → Gear shifting** (**Fahrt → Messdaten → Schaltinformationen** in German). The section shows recorded front/rear shift counts and a collapsible timeline with ride time, changed component, gear positions, tooth counts and gear ratio. The timeline displays the first 200 changes; counters include all normalized changes.

SRAM AXS can supply gearing information to a recording head unit, but the head unit determines which information reaches the activity file. A paired drivetrain alone does not guarantee that a FIT contains shift events. See [SRAM's head-unit information guide](https://support.sram.com/hc/en-us/articles/6225479178395-What-eTap-AXS-system-information-is-made-available-to-be-displayed-on-ANT-or-BLE-compatible-GPS-head-units).

## Supported FIT encodings

RideDock handles standard FIT event messages (global message 21), identified as `front_gear_change` (42) or `rear_gear_change` (43), with a valid timestamp and at least one valid position/tooth count for the changed component:

- Native component fields 9–12: front gear position, front teeth, rear gear position and rear teeth.
- Packed `data` / `gear_change_data`: four unsigned bytes, from least to most significant: rear position, rear teeth, front position, front teeth. Explicit valid native components take precedence over packed values.
- Developer fields on these event messages whose `field_description` declares `native_mesg_num=21` and native field 3 or 9–12. Their developer index must have a matching `developer_data_id`, names/target fields must be unambiguous, and their type and scale must preserve the native values. This supports packed uint32 data and individual uint8/uint8z components.

The field order comes from the [official Garmin FIT Profile](https://github.com/garmin/fit-javascript-sdk/blob/main/src/profile.js). Developer mapping follows the [FIT developer-data format](https://developer.garmin.com/fit/cookbook/developer-data) and [FIT protocol definitions](https://developer.garmin.com/fit/articles/fit-protocol/fit_protocol.html).

Manufacturer names, application IDs or arbitrary field names such as `axs_rear_gear` are insufficient to establish a mapping. There is no hardcoded proprietary SRAM application schema. Unknown proprietary encodings and record-only gear samples do not become shift events. The upstream parser flattens developer names into message properties; files with developer names that collide with native shift/timestamp fields are conservatively omitted because those values cannot be safely distinguished.

## Interpretation and missing data

- Values describe the reported gear after an event. Positions are the recorded indices, not tooth counts; ratio is front teeth divided by rear teeth and appears only when both are present.
- Zero/missing components remain unknown. In particular, a missing front position on a 1x drivetrain is not replaced with an invented chainring or tooth count.
- Incomplete or ambiguous shift messages, missing/invalid timestamps and events before ride start are omitted and reported separately. Unrelated FIT events are not counted as ignored shifts.
- Equivalent events with the same timestamp, changed component and gearing are deduplicated. Distinct events at the same timestamp remain separate. Without a known ride start, a valid event timestamp is retained but elapsed time remains unknown.
- Counts describe the recorded events, which may be incomplete. RideDock does not infer missing shifts from power/cadence or estimate time spent in a gear from sparse events.
- No SRAM network service, credentials or additional integration is used. Gearing does not enter AI contexts through this change; JSON exports keep their existing normalized-event structure and CSV remains disabled.

### Empty states since 0.5.1

The view no longer uses a single empty message for all files. Parser metadata records `shift_diagnostics` with a status, standard shift-message count, recognizable gear-value-message count and whether gear fields were described:

- **Not evaluated with current diagnostics:** older saved analyses do not establish what their original contained; reprocess the archived original if available.
- **Absent:** no recognizable recorded gear values or standard shift messages were found.
- **Descriptions only:** fields such as `front_gear` / `rear_gear` were declared, but no recognizable gearing values or shift messages were recorded. A head unit can include optional field descriptions even when that sensor supplied no data.
- **Unsupported:** recognizable gear values exist outside usable standard shift messages. Those values are not converted into invented shift events.
- **Incomplete:** standard shift messages exist, but missing/ambiguous gear values or timestamps prevented normalization.

These statuses describe what the parser recognizes, not the absence of every possible proprietary encoding. A drivetrain mounted on the bike does not establish sensor connectivity during a recording. For future rides, verify sensor pairing and live gear display on the bike computer; Hammerhead documents this under [SRAM AXS groupset integration](https://support.hammerhead.io/hc/en-us/articles/25671266567195-Karoo-OS-SRAM-AXS-Groupset-Integration). RideDock does not infer a particular device setting or hardware fault from an empty file.

## Existing rides

New FIT imports use the updated normalizer automatically. Previously enriched rides are not silently rewritten. Open the ride's Measurements section and choose **Reprocess FIT file** to update metrics, streams and shift events from its existing archived original. Notes and RPE remain unchanged.

The authenticated, CSRF-protected action only uses a source belonging to the ride owner. It checks the stored checksum and existing parser size/time limits, performs an idempotent database update and preserves previous results if parsing fails. It retains an approved archive/Coach return link and returns to Measurements. It never requests a file from a provider. When [original-data cleanup](PRIVACY.md) has purged the source, reprocessing is unavailable. No migration or additional dependency is required for 0.5.0.

## Synthetic test data

`tests/Fixtures/shifting-fit.js` generates a CRC-valid two-minute FIT entirely in memory: invented timestamps, two power samples and four shifts (three rear, one front). A simulated device uses the published SRAM manufacturer code 268. The developer variant uses an invented 16-byte application ID, the name `synthetic_shift_state`, an explicit native event/data mapping and a generated protocol-v2 developer definition. A third variant declares `front_gear` and `rear_gear` as uint8z developer fields with index 1 but writes no gearing values or shifts, testing that a field description is not a recording. These fixtures establish protocol support; they are not captured SRAM recordings or a claim that SRAM uses the invented application schema.

JavaScript tests parse both binary variants with the real `fit-file-parser` package and cover unpacking, precedence, developer identity/type/scale ambiguity, unsigned values, 1x missing data, timestamp handling, sorting and deduplication. PHP feature tests exercise the same generated files through the real Node parser, persistence, reprocessing and the rendered page, including ownership, purged sources, corrupt-file preservation and localized output. HTTP requests remain blocked in the tests.

Run `composer check` for the full checks, or `npm run test:js` and `php artisan test --filter=GearShiftingTest` for the targeted tests. Tests use generated files; rider originals remain private and are not committed. Arbitrary proprietary encodings require a documented schema and a synthetic reproduction before support can be claimed.
