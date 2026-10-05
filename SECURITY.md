# Security policy

Please do not open a public issue for a vulnerability or accidentally exposed athlete data. Contact the maintainers privately through the security contact published with the repository.

Never commit `.env`, OAuth client secrets, access/refresh tokens, webhook secrets, database snapshots, FIT/GPX files, raw provider payload fixtures or screenshots containing location data.

Production deployments should use HTTPS, a persistent queue worker, encrypted backups, a non-default `APP_KEY`, least-privilege provider scopes, short-lived API tokens and explicit data-retention rules. Rotate affected secrets immediately if exposure is suspected.

The operational baseline is documented in [production deployment](docs/DEPLOYMENT.md). A public multiuser service must also satisfy the readiness gates in [managed hosting](docs/HOSTING.md).

The dated [0.3.0 security review](docs/SECURITY_REVIEW.md) records the threat model, verified boundaries, dependency audit and remaining work. Follow the [fixture policy](docs/FIXTURES.md); `composer check:repository` checks selected sensitive file types and recognizable secret patterns without printing their contents.
