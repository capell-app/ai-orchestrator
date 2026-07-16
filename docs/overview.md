# AI Orchestrator

<!-- prettier-ignore-start -->

## What it does

AI Orchestrator provides the shared AI capability layer used by other Capell packages, such as AI Creator, Blog, Layout Builder, Media AI, SEO Suite, and Translation Manager. Those packages own the editor-facing actions that create, suggest, or apply content.

## Where to review it

Go to **System → AI Orchestrator** to open the **AI Orchestrator Capability Catalog**. This is a review screen, not a content-generation tool: it lists the AI modules and capabilities currently registered by installed packages, together with each capability's approval level, required ability, and underlying action.

Use it when an AI feature is unexpectedly unavailable, when checking what an installed package contributes, or when your developer is reviewing permissions and approval boundaries. It does not run a capability, change its approval level, or approve generated content.

## Settings

The central AI settings surface controls the shared model, request-rate limit, and prompt templates for title, meta-description, and content generation. Change these only if you are responsible for the site's AI configuration; package-specific AI buttons and their generated content remain in the package that provides them.

## Good to know

- Use **AI Creator** and other AI actions from the package where you are editing content; this catalog is for visibility into the registered capabilities.
- If AI features stop working across packages, review the catalog and ask a developer to check the shared AI configuration and provider credentials.
- Generated AI output still follows the approval and editing flow of the package that requested it.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
