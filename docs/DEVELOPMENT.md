# Local development

## Setup

Requirements: PHP 8.4+, Composer, Node.js 22 (22.13+) or 24+ (24 recommended), and SQLite (default) or PostgreSQL/MySQL.

```bash
composer setup
composer run dev
```

Open the local URL printed by the server and register an account. For API authentication, see [API access](API.md).

For an existing checkout where frontend assets are not needed:

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Provider imports run synchronously by default (`QUEUE_CONNECTION=sync`). AI activity analyses and all training plans use a separate persistent database queue by default (`AI_QUEUE_CONNECTION=ai`). `composer run dev` starts its worker automatically. When starting the web server separately, also run `php artisan queue:work ai --queue=ai --sleep=1 --timeout=120`. Provider imports may independently use an asynchronous connection such as `database`, with their own worker. See [DEPLOYMENT.md](DEPLOYMENT.md) for supervision and retry settings.

### Upgrading installations from before v0.0.8

AI generation now requires a persistent worker by default, including installations that keep provider imports synchronous. Clear cached configuration with `php artisan config:clear` after updating. Restart `composer run dev` locally; in production, supervise `php artisan queue:work ai --queue=ai --sleep=1 --timeout=120` and restart existing workers. The v0.0.8 queue change itself introduced no database migration; run current migrations when upgrading. `AI_QUEUE_CONNECTION=sync` remains available for explicitly synchronous installations.

### Local HTTPS

OAuth providers may require an HTTPS callback during local development. Create a trusted certificate with `mkcert`:

```bash
mkdir -p .certs
mkcert -install
mkcert -cert-file .certs/localhost.pem -key-file .certs/localhost-key.pem 127.0.0.1 localhost ::1
```

When these certificate files exist, `composer run dev` starts Laravel internally on port `8002` and exposes RideDock at `https://127.0.0.1:8001` through the bundled local HTTPS proxy. Stop all development processes together with `Ctrl+C`.

## Tests and quality checks

```bash
composer test
composer check
```

`composer check` also validates the Composer manifest/lockfile, checks PHP formatting, runs static analysis and JavaScript tests, builds production assets and verifies a fresh cached installation in a temporary directory. See [CI and quality checks](CI.md).
