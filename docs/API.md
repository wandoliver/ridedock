# API access

## Authentication

Application endpoints under `/api/v1` require a bearer token. Create one for your account using its existing email address:

```bash
php artisan app:create-owner-token you@example.com
```

Copy the printed token; it is shown once. The command requires an existing active account and never creates users or grants administrator privileges. Create the first administrator via CLI or activate an administrator-issued invitation first; see [account administration](ACCOUNTS.md). Pending accounts cannot use API tokens.

The token grants `activities:read`, `activities:write` and `connections:write`. Treat it as a secret.

## Examples

```bash
curl -H "Authorization: Bearer TOKEN" \
    "http://localhost:8000/api/v1/activities?from=2026-08-01&to=2026-08-31"

curl -X PATCH -H "Authorization: Bearer TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"rpe":4,"notes":"Easy endurance ride"}' \
    "http://localhost:8000/api/v1/activities/ACTIVITY_UUID"

curl -H "Authorization: Bearer TOKEN" \
    "http://localhost:8000/api/v1/analysis/export?from=2026-08-01&format=json"
```

Routes are omitted from analysis exports by default. Pass `include_route=1` only when location data is actually needed. Since 0.4.0, explicitly included routes respect your private zones: `route_segments` contains separate visible encoded polylines. Draw each segment independently. `summary_polyline` is null for a split or fully hidden route, and contains the sole visible segment otherwise. Neither route field is included by default. See [data retention and private zones](PRIVACY.md).

Only JSON analysis export is enabled (`format=json`, or omit `format`). CSV export is disabled as of 0.3.0: `format=csv` returns HTTP 422 and no download. There is currently no export control in the web interface.

## Connections

See [provider configuration and disconnect behavior](PROVIDERS.md) for OAuth setup and connection endpoints.

OAuth callbacks require an authenticated RideDock browser session for the user who started the connection. A flow started from the web interface must return to the same session within ten minutes. API clients may start authorization with `connections:write`, but the browser completing it must be signed in to the same RideDock account; callbacks cannot be completed using a bearer token alone.
