# Fixture policy and provenance

The 0.3.0 review covers the current tracked repository tree. Test data must be invented or generated from a documented public algorithm example; never copy a rider's activities, account, provider response, FIT file, screenshots or production database.

## Current fixtures

| Source | Provenance |
| --- | --- |
| `database/factories` | Faker-generated identities/measurements, random synthetic IDs/tokens and empty raw payloads. No production records are loaded. |
| PHP tests | Small arrays/models constructed in the test; route coordinates are synthetic points near latitude/longitude zero. Labels such as `PRIVATE_*` are deliberate fake leakage markers. |
| FIT normalization tests | Invented messages and coordinates; the CRC-valid binary is generated in memory by `FitEncoder`, not copied from a rider's recording. |
| `tests/Fixtures/shifting-fit.js` | Invented four-shift ride encoded from the public FIT Profile. The developer variant uses an invented application ID/name and an explicit native field mapping; SRAM manufacturer code 268 is a simulated identity, not evidence of a real SRAM recording. Native/developer definitions and checksums are generated in memory. See [gear-shifting tests](SHIFTING.md#synthetic-test-data). |
| Polyline codec tests | The three-point example published in Google's [encoded polyline algorithm documentation](https://developers.google.com/maps/documentation/utilities/polylinealgorithm), not a recorded route. |
| Provider and AI responses | Handwritten minimal simulated responses with fake credentials and IDs. Default tests block unmocked Laravel HTTP-client requests. Download-security tests stub DNS and never query real hosts. |
| Parser wrapper tests | Temporary synthetic text/scripts, removed after each test. |
| Public images/icons | Product branding and licensed UI icons. They are not test screenshots or activity exports. |

Names/emails in authentication examples are invented. Fake OAuth values must remain visibly synthetic; do not paste even expired real tokens. The local/testing seeder creates a sample account and does not run its account creation in production.

## Repository guard

Run `composer check:repository`, or the full `composer check`. The guard examines tracked files and non-ignored untracked files. CI includes it in Code quality.

It rejects environment/credential files except `.env.example`, FIT/GPX/TCX/KML/KMZ files, database/key files, binary/image/archive fixtures and selected recognizable secret patterns. Findings contain only a path and rule name, never a matched value. `.gitignore` also excludes common sensitive data formats and local environment variants.

The guard does not prove that arbitrary inline JSON, free text, numeric IDs or coordinates are anonymous, and does not scan Git history. Contributors must review provenance and keep the smallest synthetic example required for a test. Gear-shifting tests use synthetic protocol fixtures. Any future manufacturer encoding must be documented and reproduced synthetically; raw rider recordings stay outside the repository.
