## What it does

A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows.

AI Orchestrator provides the shared AI capability layer used by other Capell packages, such as AI Creator, Blog, Layout Builder, Media AI, SEO Suite, and Translation Manager. Those packages own the editor-facing actions that create, suggest, or apply content.

Its marketplace diagram is explicitly labelled as illustrative Beta contract artwork, not a product-screen claim.

## Where to review it

Go to **System → AI Orchestrator** to open the **AI Orchestrator Capability Catalog**. This is a review screen, not a content-generation tool: it lists the AI modules and capabilities currently registered by installed packages, together with each capability's approval level, required ability, and underlying action.

Use it when an AI feature is unexpectedly unavailable, when checking what an installed package contributes, or when your developer is reviewing permissions and approval boundaries. It does not run a capability, change its approval level, or approve generated content.

## Settings

The central AI settings surface controls the shared model, request-rate limit, and prompt templates for title, meta-description, and content generation. Change these only if you are responsible for the site's AI configuration; package-specific AI buttons and their generated content remain in the package that provides them.

The host must also have working provider credentials, an asynchronous queue worker, and the application scheduler. Financial authorization and settlement belong to the consuming application; this package accepts only opaque authorization evidence and non-financial execution allowances.

Developers integrating hosted calls should use the versioned types in [Managed provider contract v1](managed-provider-contract-v1.md). Do not pass wallet, journal, price, entitlement, refund, or balance models into AI Orchestrator.

## Processing, failures, and privacy

- Identical assistant requests share one durable queued request instead of sending duplicate provider calls. A stalled request can be queued again after five minutes.
- A generation job can run three times and has a 120-second timeout. A failure leaves the edited content unchanged and sends the requesting user a database notification; check the queue and provider configuration before trying again.
- Rate limits and caller-issued execution allowances can stop a request before content is generated. Raising a limit does not approve or publish any previous output.
- Prompts, unpublished content, generated results, and stored error details are encrypted in the database.
- Generation requests expire after one day. A daily scheduled task removes expired requests and clears detailed generation-history payloads after the configured retention window, which defaults to 30 days. Aggregate token, duration, and failure fields remain available for audit reporting.

## Good to know

- Use **AI Creator** and other AI actions from the package where you are editing content; this catalog is for visibility into the registered capabilities.
- If AI features stop working across packages, review the catalog and ask a developer to check the shared AI configuration and provider credentials.
- Generated AI output still follows the approval and editing flow of the package that requested it.
