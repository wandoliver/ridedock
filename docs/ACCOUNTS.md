# Account administration

RideDock v0.6.0 accepts accounts only through administrators. Public `GET /register` and `POST /register` routes do not exist, including with cached routes. There is no registration toggle. Existing accounts keep their credentials and become active members; no account automatically becomes an administrator.

## First administrator and upgrades

After creating `.env`, generating `APP_KEY` and running migrations:

```bash
php artisan app:create-admin you@example.com --name="Your Name" --locale=de
```

For a new account, the command asks for a password and confirmation with hidden input (at least 12 characters). There is no password command-line option and no default production account. New admin creation requires interactive input.

For an existing active account, the same command grants the `admin` role without changing its password, name, locale or data. It may be used noninteractively for promotion. Pending accounts must first activate their invitation. Additional administrators are granted explicitly via this command; the web interface creates members only. An administrator role permits account/invitation management and does not bypass rider-data ownership.

On upgrades run migrations, clear/rebuild config and route caches and restart long-running servers/workers. A current browser session will show **User management / Benutzerverwaltung** after promotion. The local/testing-only sample seeder still creates a member, never a production administrator.

## Create and invite users

Open **User management / Benutzerverwaltung** in the sidebar as an administrator. Enter a name, email and language, then choose:

- **Create activation link**: create a pending member and show its personal link once in the response. Copy and share it privately; returning to the list does not reveal it again. Links are not flashed into application sessions.
- **Send invitation email**: create a pending member and send its link using the configured mail transport.

The invited person opens the link, chooses a password and then signs in normally. Name, email and role are fixed by the prepared account and cannot be changed through the activation request. Pending accounts have no browser/API access. A manual link does not verify ownership of the email address; acceptance of an email-delivered invitation records `email_verified_at`. General email-verification enforcement is not enabled for existing accounts.

Each link expires after seven days and works once. Opening it does not activate the account or consume the token, including when an email scanner visits it. New activation links and resend actions revoke older invitations. A revoked/expired invitation leaves its account pending; administrators may issue a new link for that account. Invitations never reset an active account's password.

## Email setup

The default `MAIL_MAILER=log` does not deliver messages and is deliberately refused for invitation emails. Use the manual-link option until a real transport is configured. Direct SMTP, sendmail, SES, Postmark, Resend and Mailgun transports are supported; their normal Laravel transport dependencies apply. Composite failover/roundrobin transports are not enabled for invitations because a nested log transport could persist raw links.

Example SMTP configuration (use your provider's credentials):

```dotenv
APP_URL=https://ridedock.example
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.example
MAIL_PORT=465
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=invites@ridedock.example
MAIL_FROM_NAME=RideDock
```

Set the correct `APP_URL`: activation links use it directly rather than the incoming Host header. Run `php artisan config:cache` after production configuration changes. SMTP for port 587 normally uses `MAIL_SCHEME=smtp` with the transport's STARTTLS negotiation; confirm the settings with your provider.

Invitations send synchronously so raw links never enter jobs/failed-job payloads. A successful send means the transport accepted the message, not that it arrived in the inbox. Check your provider's delivery status and configure sender authentication there. Delivery failures show a safe administrator message and leave the account pending; resend or create a fresh manual link. The raw transport exception is not logged because it could contain the message or link.

## Security and operations

Activation links are bearer secrets: possession permits setting the bound account's initial password. Share manual links only with the intended recipient. Tokens contain 32 cryptographically random bytes; only their SHA-256 hashes are stored in the invitation table. Neither audit records nor application sessions contain raw links. Link/admin responses use `Cache-Control: no-store`, `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex, nofollow`.

Configure production HTTPS and `APP_DEBUG=false`. Exclude/redact `/invitations/*` URL paths from web-server/proxy access logs, analytics, exception tracking and request-body capture. Response headers cannot erase server logs, recipient email copies or browser history. Give mail systems and administrator accounts appropriate access protection.

Administration events (`user.created`, `invitation.created`, `invitation.revoked`, `invitation.accepted`, `invitation.emailed`, `invitation.email_failed`, `admin.created`, `admin.promoted`) are recorded in `account_audit_events` with actor, subject and timestamp. CLI administrator events have a null actor. There is no web account deletion, suspension or role editing in this version.

The existing `app:create-owner-token` command only issues API tokens for existing active accounts; it never creates or promotes accounts. Password-reset/account-recovery and administrator MFA remain separate future work. Do not use an invitation as password recovery for an active account.
