# Managed hosting roadmap

This document separates RideDock's current self-hosted application from a future managed service for cyclists who do not want to operate servers or developer accounts.

The target experience is simple: a user creates an account, connects a supported cycling provider, imports rides and optionally uses the AI coach. Provider credentials, infrastructure and the operator's AI credentials remain server-side.

## Current foundation

RideDock already provides several useful multiuser boundaries:

- administrator-managed access and expiring, revocable invitations;
- account-scoped activities, sources, streams, events and analyses;
- ownership checks on web pages, APIs, exports and route geometry;
- encrypted OAuth access and refresh tokens;
- private FIT storage with hashed filenames and checksums;
- idempotent imports and unique queue jobs;
- normalized, data-minimized AI contexts;
- optional PostgreSQL and asynchronous queue operation.

These are foundations, not a complete hosted product. Public registration is removed as of v0.6.0; accounts are provisioned by administrators. See [account administration](ACCOUNTS.md). The following readiness work remains before broader managed hosting.

## v0.8.0 operational controls

Version 0.8.0 implements controls for two operational areas:

1. **Provider operations:** hosted approval/terms confirmation gates, configurable API budgets and cooldowns for user-triggered sync and existing webhooks, coordination across application servers and centralized failure monitoring. Provider approvals must be obtained externally; documentation or application changes do not grant approval. Automatic periodic synchronization (Auto-Sync) remains deferred.
2. **AI costs and capacity:** per-user quotas with administrator overrides, per-attempt usage/estimated cost summaries, configurable operator budget warnings and operational monitoring. The existing queue, retries, input/output token recording and prompt versioning form the implementation baseline.

Follow [operations setup](OPERATIONS.md) and the [v0.8.0 backlog](MVP_BACKLOG.md#v080--provider-operations-and-ai-costscapacity). These controls require deployment configuration and verification; they do not close the other hosted-service requirements or the public-launch gate below.

## Hosting model

The recommended first managed architecture is one application deployment with a shared PostgreSQL database and strict `user_id` ownership boundaries. RideDock does not currently model teams or organizations, so adding a more complex tenancy layer would create operational cost without a present product requirement.

Use centralized provider applications and one operator-controlled OpenAI project. End users should authorize their own Wahoo or Hammerhead accounts through OAuth, but they should never receive provider client secrets or the operator's OpenAI key.

Bring-your-own OpenAI keys may remain an option for self-hosted installations. It is not recommended as the default managed-service experience because it exposes non-technical users to developer billing and key-management work.

## Required product work

### Identity and onboarding

- general email verification (email-delivered invitations verify their bound address; manual links and legacy accounts do not);
- password-reset and account-recovery flows;
- acceptance and versioning of terms and privacy information;
- administrative account suspension and support tooling;
- login throttling, bot protection and session management review.

### Account and data lifecycle

Version 0.7.0 provides [personal archives, synchronous confirmed account deletion and configurable operational retention](PRIVACY.md) for sync/database queues. Broader managed hosting still requires:

- scalable background export/deletion status for accounts too large for synchronous requests;
- owner-aware cleanup for any non-database queue backend;
- monitored retention execution and verified external backup/log expiry;
- operator erasure reconciliation during recovery;
- a policy for provider-originated deletions and revoked permissions.

### Provider operations

- production approval for every supported provider application;
- terms review for offering each integration to unrelated users;
- stable production callback and webhook domains;
- deployment verification of the implemented shared API budgets, delayed queue releases, locks and cooldowns;
- alert delivery and support use of the implemented provider operations dashboard and user-visible connection states;
- a support path for revoked, expired and duplicate provider connections.

### AI service controls

- monitoring and capacity planning for the implemented AI queue (activity analyses, goal roadmaps, training blocks and weekly plans);
- a dedicated server-side OpenAI project or service-account key;
- operator-selected per-user quotas and shared capacity limits, implemented in v0.8.0;
- configured model prices, usage/cost reconciliation and treatment of unreported spend for the implemented per-attempt accounting;
- provider project budgets and delivery of the implemented operator budget warnings;
- deployment validation of request admission limits and graceful queue handling under provider outages;
- a privacy-preserving `safety_identifier` before unrestricted public access;
- regression evaluations for the existing model metadata and prompt versioning;
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
