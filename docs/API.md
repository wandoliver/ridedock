# API access

## Authentication

Application endpoints under `/api/v1` require a bearer token. Create one for your account using its registered email address:

```bash
php artisan app:create-owner-token you@example.com
```

Copy the printed token; it is shown once. The command creates an owner account with a random password if the email does not already exist. Register through the web interface first if you also need browser access.

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

Routes are omitted from analysis exports by default. Pass `include_route=1` only when location data is actually needed.

## Connections

See [provider configuration and disconnect behavior](PROVIDERS.md) for OAuth setup and connection endpoints.
