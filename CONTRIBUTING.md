# Contributing

Contributions are welcome. Keep provider-specific behavior inside `Infrastructure/Providers`, normalize values to SI units, preserve import idempotency, and add tests for mappings and retry behavior.

Do not submit real athlete payloads or routes. Build small synthetic fixtures and remove coordinates, names, tokens and external IDs. By contributing, you agree that your contribution is licensed under the repository's MIT License.

Run before opening a pull request:

```bash
composer check
```

Use `composer cs-fixer` to apply the project’s PHP formatting rules. Tests mock Vite and block unmocked Laravel HTTP-client requests by default; use explicit provider/AI fakes and synthetic fixtures. See [CI and quality checks](docs/CI.md) for individual commands and GitHub job names.
