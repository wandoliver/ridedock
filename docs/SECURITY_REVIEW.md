# Security review — 0.3.0

Reviewed on 2026-10-05. Scope: the current application and repository tree, authorization boundaries, OAuth linking, external file intake, error handling, dependency lockfiles and test fixtures. This is a documented engineering review, not an independent penetration test or approval for public managed hosting.

## Assets and trust boundaries

- Rider identity, password hashes, sessions and API tokens.
- Encrypted provider access/refresh tokens and deployment secrets.
- Activities, notes, training profiles, AI contexts and plans, precise routes and private FIT files.
- External provider responses and download URLs enter application code, private storage and a Node parser. They are untrusted input.
- The browser receives owner-scoped views and route geometry; API tokens additionally require the relevant Sanctum abilities. OAuth responses arrive through a browser session.
- AI receives the normalized training context, including free-text goals/constraints and ride names, but excluding raw GPS streams, notes and provider payloads. Free text can itself contain personal information; the operator and rider control it.
- Operators, deployment environment and server-side endpoint configuration remain trusted. A compromised operator account, application key or database is outside protections afforded by owner filtering.

Threat actors considered: unauthenticated callers, one authenticated rider accessing another rider's data, malicious upstream response content, unintended repository disclosures and inputs exhausting worker resources.

## Findings and controls

| Boundary | Result |
| --- | --- |
| Activity, plan, feedback, connection and status access | Reviewed owner-scoped queries, validated write allowlists and API abilities; existing authorization tests retained. |
| Browser authentication | Reviewed password hashing, login/registration throttling, session regeneration/logout invalidation and standard web CSRF middleware. |
| OAuth linking | Fixed: callbacks require the initiating account's authenticated browser. Web-initiated state also checks a session hash. Ten-minute state is consumed under a cache lock; replay, foreign owners and different sessions are rejected before token exchange. API-initiated state requires the same account's browser login. |
| Download URL supplied by an external response | Fixed: HTTPS on port 443, no URL credentials, only globally routable unicast IP addresses across the DNS answer; cURL pins the validated address, verifies TLS normally and disables redirects and proxies. Reserved, private, loopback and link-local targets are rejected. |
| FIT intake/storage | Fixed: external downloads enforce the configured size limit at headers, transfer progress and completed body; archiving rejects empty/oversized files and public/serving disks. Parser timeout, size checks and checksum verification remain in place. |
| Provider and AI failures | Fixed: OAuth, sync, queued activity import, public file download and AI HTTP failures emit bounded application messages instead of remote bodies or signed URLs. The connection API also masks legacy stored error text. AI failure/status views retain their existing safe messages. |
| CSV analysis export | Disabled at the user's request. CSV code was removed; `format=csv` fails validation with HTTP 422. Only the documented JSON export remains. |
| Default seeded account | Fixed: the sample account with the factory password can only be seeded in local/testing environments. |
| Rendering and redirects | Reviewed escaped Blade fields, script-safe JSON encoding and the allowlist for local activity return links. |
| Webhook input | Reviewed HMAC verification before validation/dispatch and exclusion of revoked connections. No live connector service was contacted. |
| Repository fixtures | Replaced place-specific coordinate literals with synthetic points near zero. Reviewed in-memory/generated fixtures and tracked assets; added provenance rules and an offline CI guard. See [fixture policy](FIXTURES.md). |

OAuth state binding and file-target checks follow the principles in the [OWASP OAuth guidance](https://cheatsheetseries.owasp.org/cheatsheets/OAuth2_Cheat_Sheet.html) and [OWASP SSRF guidance](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html).

## Dependency audit

`composer audit --locked --format=json` reported no advisories or abandoned packages. The npm audit initially identified a vulnerable transitive development dependency, `brace-expansion` 5.0.9. The lockfile now uses 5.0.12; the subsequent npm audit reported zero vulnerabilities. These are dated registry results, not a promise about future advisories.

Repeat audits after dependency updates and before a release:

```bash
composer audit --locked
npm audit
```

`composer check` includes the offline repository guard. CI runs it within the existing Code quality job on PHP 8.4. Tests use synthetic data, simulated HTTP responses and stubbed DNS answers; they verify RideDock's boundaries rather than third-party service behavior.

## Remaining deployment and product work

This is the dated 0.3.0 review. Version 0.4.0 adds [local retention controls and private-zone masking](PRIVACY.md); hosted-service readiness and operational retention remain separate.

- Public managed hosting still needs the identity/account lifecycle, quotas, retention, export/deletion and operational controls listed in [hosting readiness](HOSTING.md). This release does not close those backlog items.
- Use a shared lock-capable cache for OAuth state across application nodes. A login/logout/session renewal during a web OAuth flow requires restarting that flow.
- FIT download hosts must resolve to public addresses and return an uncompressed file directly over HTTPS. Automatic HTTP decompression is disabled to prevent inflated responses bypassing transfer limits. Redirecting CDN responses and private-network/proxy downloads now fail closed. cURL is required; the guard must not be bypassed for compatibility.
- General infrastructure egress restrictions, TLS, debug settings, backups, access-log query/header redaction and log/failed-job access remain deployment responsibilities. Old database errors or logs are not erased by this release; the API hides legacy connection errors and new failures use safe messages.
- Browser fonts still load from Google Fonts and map tiles from OpenStreetMap. The browser therefore contacts those third parties; self-hosting assets/tiles and explicit private-zone masking remain separate product decisions.
- Existing API tokens are not automatically expired by this review. Operators must restrict abilities, rotate/revoke tokens and keep the CLI-only creation flow private.
- No real provider OAuth/FIT integration, real rider data, external penetration test, full Git-history scan or deployed infrastructure audit was performed. The repository guard detects selected file types and secret patterns; human review is still required for inline data and unknown token formats.
