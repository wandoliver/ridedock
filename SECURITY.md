# Security policy

Please do not open a public issue for a vulnerability or accidentally exposed athlete data. Contact the maintainers privately through the security contact published with the repository.

Never commit `.env`, OAuth client secrets, access/refresh tokens, webhook secrets, database snapshots, FIT/GPX files, raw provider payload fixtures or screenshots containing location data.

Production deployments should use HTTPS, a persistent queue worker, encrypted backups, a non-default `APP_KEY`, least-privilege provider scopes, short-lived API tokens and explicit data-retention rules. Rotate affected secrets immediately if exposure is suspected.

The operational baseline is documented in [DEPLOYMENT.md](DEPLOYMENT.md). A public multiuser service must also satisfy the readiness gates in [HOSTING.md](HOSTING.md).
