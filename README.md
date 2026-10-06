# RideDock

![Release](https://img.shields.io/badge/release-v0.5.2-blue)
[![CI](https://github.com/wandoliver/ridedock/actions/workflows/ci.yml/badge.svg)](https://github.com/wandoliver/ridedock/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

RideDock is an open-source, self-hosted cycling training platform. Import rides from Hammerhead/Karoo or Wahoo, explore measurements and route heatmaps, and plan training with an optional AI coach.

Activities and source files stay private. GPS coordinates, FIT files, provider payloads and activity notes are excluded from AI requests.

## Quick start

Requires PHP 8.4+, Composer, Node.js 22 (22.13+) or 24+ (24 recommended), and SQLite (default) or PostgreSQL/MySQL.

```bash
composer setup
composer run dev
```

Open the local URL printed by the server and register an account. Configure provider connections and the optional AI coach using the [setup guides](docs/README.md).

## Documentation

- [Documentation index](docs/README.md)
- [Local development](docs/DEVELOPMENT.md)
- [Production deployment](docs/DEPLOYMENT.md)
- [Changelog](CHANGELOG.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run `composer check` before submitting changes. Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

Licensed under [MIT](LICENSE); third-party integrations retain their own terms.
