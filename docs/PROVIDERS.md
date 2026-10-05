# Provider implementation notes

## Configuration

All credentials belong in `.env`; `.env` and source activity files are ignored by Git.

### Hammerhead configuration

Set `HAMMERHEAD_ENABLED=true`, the client credentials, redirect URL and webhook secret. Then open the authenticated endpoint:

```text
GET /api/v1/connections/hammerhead/connect
```

Hammerhead's current official process requires a developer account, acceptance of its API agreement, and client credentials from the Dashboard. Review the license before publishing or hosting a connector for others. The current agreement contains restrictions that may require written permission for a public/community service; this repository is not legal advice.

Hammerhead activity distances are provided in metres and durations in milliseconds. The explicit `HAMMERHEAD_DISTANCE_MULTIPLIER=1` and `HAMMERHEAD_DURATION_MULTIPLIER=0.001` settings normalize both values to SI units; FIT-derived values are authoritative after enrichment.

Official references: [Developer Platform](https://www.hammerhead.io/pages/developer-platform), [Public API documentation](https://api.hammerhead.io/v1/docs), [developer account setup](https://support.hammerhead.io/hc/en-us/articles/43558376710683-Creating-a-Developer-Account), and [API license](https://support.hammerhead.io/hc/en-us/articles/42738760915099-API-Licence-Document).

### Wahoo configuration

Set `WAHOO_ENABLED=true`, the client credentials and the exact redirect URL registered in the Wahoo Developer Portal. The connector uses the confidential OAuth authorization-code flow and requests only `user_read`, `workouts_read` and `offline_data`. Open the authenticated endpoint:

```text
GET /api/v1/connections/wahoo/connect
```

RideDock imports completed cycling workouts and their FIT files. Planned workouts, non-cycling workouts and write endpoints are not used. Wahoo webhooks remain disabled until a public webhook URL and token are configured. See the [Wahoo Cloud API documentation](https://cloud-api.wahooligan.com/).

## Contract

Every provider returns `ProviderActivity` and `ProviderPage`. OAuth and source-file support are separate optional interfaces, so a future file upload or Garmin adapter need not fake OAuth methods.

## Hammerhead/Karoo

Implemented against the official v1 OpenAPI surface:

- OAuth authorization-code and refresh-token flow;
- `GET /activities` pagination;
- `GET /activities/{activityId}` detail;
- `GET /activities/{activityId}/file` FIT download;
- activity webhook verification using `X-Hmac-Signature` HMAC-SHA256.

The public summary schema includes identity, timestamps, duration, distance, activity type, description and polyline. Sensor details require FIT enrichment. The connector is disabled by default because credentials and accepted API terms are required.

## Wahoo

Implemented as a read-only Wahoo Cloud API adapter:

- confidential OAuth authorization-code and refresh-token flow;
- authenticated Wahoo user lookup;
- paginated import of completed cycling workouts;
- SI-unit mapping from workout summaries;
- private FIT-file download and enrichment;
- incremental polling that stops once the overlap boundary is reached.

The connector does not request write scopes and ignores planned, incomplete and non-cycling workouts. Webhooks remain disabled until a public webhook endpoint is configured.

## Disconnect

In Settings → Connections, use Disconnect to remove all locally stored provider credentials. Imported rides and FIT files remain available. Wahoo also receives a permission revocation request; if it fails, RideDock confirms local disconnection and asks you to remove access in your Wahoo account. Hammerhead is disconnected locally. Use Connect to authorize again. Disconnected connections cannot be synchronized. API clients can call `DELETE /api/v1/connections/{provider}` with the `connections:write` ability.

Disconnect clears local credentials and marks the retained connection `revoked`. Existing activity sources remain attached for deduplication after reconnect. Wahoo implements the optional `RevocableActivityProvider` contract using [`DELETE /v1/permissions`](https://cloud-api.wahooligan.com/#deauthorize). Failures never prevent local cleanup and are shown to the user without storing provider exception details. No verified revocation endpoint is available in the current Hammerhead adapter, so it uses local cleanup.

## Adding a provider

1. Implement `ActivityProvider` and optionally `OAuthActivityProvider` / `ActivityFileProvider`.
2. Map provider units to SI units in `ProviderActivity`.
3. Register the adapter in `AppServiceProvider`.
4. Add environment-only credentials and provider priority.
5. Add mapping, pagination, retry and idempotency tests.

## Licensing

RideDock's application core is licensed under MIT. Third-party APIs, trademarks, user data and imported code retain their own terms. In particular, the MIT license does not grant rights to use Hammerhead/SRAM APIs.
