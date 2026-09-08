# Provider implementation notes

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

Disconnect clears local credentials and marks the retained connection `revoked`. Existing activity sources remain attached for deduplication after reconnect. Wahoo implements the optional `RevocableActivityProvider` contract using [`DELETE /v1/permissions`](https://cloud-api.wahooligan.com/#deauthorize). Failures never prevent local cleanup and are shown to the user without storing provider exception details. No verified revocation endpoint is available in the current Hammerhead adapter, so it uses local cleanup.

## Adding a provider

1. Implement `ActivityProvider` and optionally `OAuthActivityProvider` / `ActivityFileProvider`.
2. Map provider units to SI units in `ProviderActivity`.
3. Register the adapter in `AppServiceProvider`.
4. Add environment-only credentials and provider priority.
5. Add mapping, pagination, retry and idempotency tests.
