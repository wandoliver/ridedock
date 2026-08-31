# Managed hosting roadmap

This document separates RideDock's current self-hosted application from a future managed service for cyclists who do not want to operate servers or developer accounts.

The target experience is simple: a user creates an account, connects a supported cycling provider, imports rides and optionally uses the AI coach. Provider credentials, infrastructure and the operator's AI credentials remain server-side.

## Current foundation

RideDock already provides several useful multiuser boundaries:

- account-scoped activities, sources, streams, events and analyses;
- ownership checks on web pages, APIs, exports and route geometry;
- encrypted OAuth access and refresh tokens;
- private FIT storage with hashed filenames and checksums;
- idempotent imports and unique queue jobs;
- normalized, data-minimized AI contexts;
- optional PostgreSQL and asynchronous queue operation.

These are foundations, not a complete hosted product. Public registration should remain restricted until the following readiness work is complete.

## Hosting model

The recommended first managed architecture is one application deployment with a shared PostgreSQL database and strict `user_id` ownership boundaries. RideDock does not currently model teams or organizations, so adding a more complex tenancy layer would create operational cost without a present product requirement.

Use centralized provider applications and one operator-controlled OpenAI project. End users should authorize their own Wahoo or Hammerhead accounts through OAuth, but they should never receive provider client secrets or the operator's OpenAI key.

Bring-your-own OpenAI keys may remain an option for self-hosted installations. It is not recommended as the default managed-service experience because it exposes non-technical users to developer billing and key-management work.

## Required product work

### Identity and onboarding

- verified email addresses;
- password-reset and account-recovery flows;
- optional invite-only registration for early releases;
- acceptance and versioning of terms and privacy information;
- administrative account suspension and support tooling;
- login throttling, bot protection and session management review.

### Account and data lifecycle

- disconnect and deauthorize provider connections;
- user-controlled data export;
- account deletion with background deletion of activities, source files, tokens and analyses;
- explicit retention windows for inactive accounts, failed jobs, logs and backups;
- auditable deletion status and retry behavior;
- a policy for provider-originated deletions and revoked permissions.

### Provider operations

- production approval for every supported provider application;
- terms review for offering each integration to unrelated users;
- stable production callback and webhook domains;
- rate-limit-aware queues, retries and circuit breakers;
- per-provider operational dashboards and user-visible connection errors;
- a support path for revoked, expired and duplicate provider connections.

### AI service controls

- asynchronous queue execution for activity analyses, goal roadmaps, training blocks and weekly plans;
- a dedicated server-side OpenAI project or service-account key;
- per-user analysis and training-plan quotas;
- token and cost accounting by user, feature and model;
- project budgets and operator alerts;
- request throttling and graceful handling of rate limits or outages;
- a privacy-preserving `safety_identifier` before unrestricted public access;
- model and prompt versioning with regression evaluations;
- explicit user consent and a non-medical-advice presentation.

The OpenAI Responses API supports a stable safety identifier and recommends hashing a username or email instead of sending identifying information directly. See the official [Responses API reference](https://developers.openai.com/api/reference/responses#create).

### Infrastructure and operations

- PostgreSQL with tested point-in-time recovery or equivalent managed backups;
- supervised web and queue processes;
- persistent private FIT storage, eventually backed by object storage;
- centralized logs, metrics, tracing and alerting;
- separate development, staging and production environments;
- secret rotation and an incident-response procedure;
- capacity limits for database rows, FIT storage, queues and outbound APIs;
- a status page and a documented support process.

### Privacy and security

- a threat model and independent security review;
- encrypted backups and least-privilege operator access;
- clear disclosure of location, training and AI processing;
- private-zone masking and user-controlled route visibility;
- data-processing and subprocessor documentation;
- GDPR-oriented access, correction, portability and erasure workflows where applicable;
- retention policies that cover primary data, logs and backups;
- dependency and secret scanning in CI.

Legal requirements depend on the operator, users and deployment regions. This document is an engineering roadmap, not legal advice.

## AI data boundary

For each requested analysis, RideDock currently sends normalized training context rather than raw recordings.

| Data | Stored by RideDock | Sent in AI context |
| --- | --- | --- |
| Activity metrics and sensor summaries | Yes | Yes |
| RPE and derived session load | Yes | Yes |
| Training goal, availability and constraints | Yes | Yes |
| Activity notes | Yes | No |
| GPS coordinates and route geometry | Yes | No |
| FIT files and provider payloads | Yes | No |
| OAuth credentials | Yes, encrypted | No |

Managed-service privacy information must also explain that `store: false` disables retrievable Response storage for RideDock's request but is not itself a contractual Zero Data Retention arrangement. OpenAI documents application-state, abuse-monitoring and optional organization/project retention controls separately in its [data controls guide](https://developers.openai.com/api/docs/guides/your-data).

## Suggested rollout

### Phase 0: private staging

- production-like PostgreSQL, queues, storage and backups;
- separate provider and OpenAI projects;
- synthetic or explicitly consented test data;
- operational dashboards and restore drills.

### Phase 1: invite-only personal beta

- a small known user group;
- verified email, password reset and account deletion;
- manual support and conservative AI quotas;
- provider production approval and terms confirmed;
- no paid plan until usage and support costs are understood.

### Phase 2: managed beta

- automated onboarding and lifecycle flows;
- usage accounting, quotas and operator spend controls;
- support tooling, incident procedures and public privacy documents;
- measured queue, database and FIT-storage capacity.

### Phase 3: public availability

- security review findings resolved;
- billing and subscription lifecycle if monetized;
- published service status and support expectations;
- reliable deletion, export, backup and recovery exercises;
- sufficient provider quotas and documented escalation paths.

## Public-launch gate

Do not open unrestricted registration until all of the following are true:

- [ ] Provider terms and production approvals cover the intended service.
- [ ] Email verification, recovery, suspension and deletion work end to end.
- [ ] Database and FIT-file restores have been tested.
- [ ] Queue failures and external API outages are visible and recoverable.
- [ ] Per-user AI and provider limits prevent one account exhausting shared capacity.
- [ ] Privacy and retention disclosures match actual application behavior.
- [ ] A security review and incident-response exercise have been completed.
- [ ] Operating and support costs are understood for the expected user volume.

Until this gate is met, RideDock should be described as a private or invite-only deployment rather than a generally available hosted service.
