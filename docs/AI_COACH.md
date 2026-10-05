# AI coach configuration

The AI coach is optional. Without an AI provider, RideDock remains usable for importing and viewing activities. Normalized activity summaries can also be exported through the authenticated [JSON API](API.md); CSV export is currently disabled. The current implementation uses the OpenAI [Responses API](https://developers.openai.com/api/reference/responses) with strict structured outputs.

### 1. Create server-side credentials

Create or select an OpenAI project in the [API Platform](https://platform.openai.com/settings/organization/projects), then create a project API key. Use a separate project and key for each deployment environment so usage, limits and key rotation remain isolated.

Never expose the key in browser code, a mobile application, screenshots or the repository. OpenAI recommends loading API credentials from a server-side environment variable or key-management service; RideDock reads the key only from server configuration. See the official [OpenAI API authentication guidance](https://developers.openai.com/api/reference/overview#authentication).

### 2. Configure RideDock

Set these values in the deployment's `.env` file or secret manager:

```dotenv
AI_QUEUE_CONNECTION=ai
AI_PROVIDER=openai
OPENAI_API_KEY=replace-with-a-project-api-key
OPENAI_MODEL=replace-with-a-responses-api-model
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT_SECONDS=60
```

`OPENAI_MODEL` has no default so enabling AI is always deliberate. Choose a model available to the project that supports the Responses API and structured outputs, then validate it against representative activities and training plans. Consult the current [OpenAI model catalog](https://developers.openai.com/api/docs/models) instead of treating an example model ID as a permanent recommendation.

After changing environment configuration, clear Laravel's cached configuration:

```bash
php artisan config:clear
```

### 3. Verify the integration

1. Sign in and open **Settings → Training & AI**.
2. Save a training profile with at least a goal, a future target date and available training days.
3. Import an activity and select **Analyze ride** on its detail page.
4. Open **Coach** and create the planning levels in order:
    - **Goal roadmap:** phases from tomorrow through the configured target date;
    - **Four-week block:** the current roadmap phase translated into four progressive weeks;
    - **Seven-day plan:** concrete sessions matched to available days and recent workload.

The seven-day plan remains useful on its own, but it uses the current roadmap and matching four-week block automatically when they exist. Creating a new roadmap makes an older block stale, so RideDock asks for a new block before using it for subsequent weekly plans.

Requests return immediately with a queued status. Ride and Coach pages check progress automatically and reload when the job completes or fails. Duplicate requests reuse the outstanding analysis; provider failures allow up to three attempts and then show a safe error without replacing stored activities or completed plans. The worker setup is described in [DEPLOYMENT.md](DEPLOYMENT.md).

### Data sent to OpenAI

RideDock sends only the normalized context needed for the requested feature:

- activity summaries and available sensor aggregates;
- subjective RPE and derived session-RPE load;
- aggregate training history for the previous six weeks;
- the athlete's configured goal, availability, constraints and optional performance values;
- the current generated roadmap and matching four-week block when producing a seven-day plan.

RideDock does not send OAuth credentials, provider payloads, FIT files, GPS coordinates, route geometry or activity notes. Requests set `store: false`, so RideDock does not create retrievable Response objects for later application use. This setting is not the same as contractual Zero Data Retention: standard API abuse-monitoring retention and project-level data controls are documented separately in OpenAI's [data controls guide](https://developers.openai.com/api/docs/guides/your-data).

### Cost and hosted use

API usage is billed to the OpenAI project behind `OPENAI_API_KEY`. A personal installation is controlled by its operator. A managed multiuser service must add per-user quotas, rate limits, usage accounting and operator spend alerts before public access. End users of a managed service should not be asked to paste the operator's API key into the browser. See [HOSTING.md](HOSTING.md) for the managed-service roadmap.
