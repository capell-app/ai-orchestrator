# AI Orchestrator

<!-- prettier-ignore-start -->

## What This Plugin Adds

AI Orchestrator is an **Available**, **No schema impact** Capell package in the **Capell Commercial** product group. It ships as `capell-app/ai-orchestrator` and extends these surfaces: admin.

A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows.

After install, admins get package-owned management or reporting surfaces inside Capell.

Status details:

- Status: Available
- Tier: premium
- Bundle: commercial
- Composer package: `capell-app/ai-orchestrator`
- Namespace: `Capell\AIOrchestrator`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Capability list or prompt surface where provided by a consuming package (admin, optional).
- LayoutBuilder layout preview workflow if LayoutBuilder integration is enabled (admin, optional).
- Approval state where a capability requires review (admin, optional).

## Technical Shape

- Service providers: `Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider`.
- Filament classes: `AIOrchestratorCapabilityCatalogPage`.
- Events: `AIOrchestratorCapabilityRunRecorded`.
- Actions: `ListAIOrchestratorCapabilitiesAction`, `RegisterAIOrchestratorModuleAction`, `RunAIOrchestratorCapabilityAction`.
- Data objects: `AIOrchestratorCapabilityData`, `AIOrchestratorRunData`.
- Manifest contributions: `admin-page: Capell\AIOrchestrator\Manifest\AiOrchestratorAdminPageContribution`.
- Health checks: `Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck`.
- Blade views: `packages/ai-orchestrator/resources/views/filament/pages/capability-catalog.blade.php`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: none declared in `capell.json`.
- Public routes: none detected in package route files.
- Database changes: no package migrations declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: none declared.
- Commands: none declared.

## Common Pitfalls

- Verify the package is installed before expecting its provider, views, or extension contributions to run.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |

## Quick Start

1. Install the package: `composer require capell-app/ai-orchestrator`.
2. Run the required setup: no package migrations are declared; clear cached config and routes if the host app uses caches.
3. Open the related Capell admin surface and verify AI Orchestrator appears.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Layout Builder](../../layout-builder/README.md), [Content Sections](../../content-sections/README.md), [Media Ai](../../media-ai/README.md), [Seo Suite](../../seo-suite/README.md), [Translation Manager](../../translation-manager/README.md).
- Focused tests: `vendor/bin/pest packages/ai-orchestrator/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
