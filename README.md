# AI Orchestrator

<!-- prettier-ignore-start -->

## What This Plugin Adds

AI Orchestrator is an **Available**, **No schema impact** Capell package in the **Capell Commercial** product group. It ships as `capell-app/ai-orchestrator` and extends these surfaces: admin.

Shared AI capability registry and execution layer for Capell packages.

After install, the package contributes admin-facing extension points. Docs gap: no concrete Filament resource or page was detected.

Status details:

- Status: Available
- Tier: premium
- Bundle: commercial
- Composer package: `capell-app/ai-orchestrator`
- Namespace: `Capell\AIOrchestrator`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, and Data objects instead of pushing this behaviour into core or application code.

**For teams:** The shared AI backbone for Capell packages: register, run, and govern AI-assisted capabilities from one admin-safe layer.

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

- Capability list or prompt surface where provided by a consuming package (admin, optional).
- LayoutBuilder layout preview workflow if LayoutBuilder integration is enabled (admin, optional).
- Approval state where a capability requires review (admin, optional).

## Technical Shape

- Service providers: `Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider`.
- Actions: `ListAIOrchestratorCapabilitiesAction`, `RegisterAIOrchestratorModuleAction`, `RunAIOrchestratorCapabilityAction`.
- Data objects: `AIOrchestratorCapabilityData`, `AIOrchestratorRunData`.
- Health checks: `Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: admin-facing extension points are declared, but no concrete Filament class was detected.
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
3. Verify the package provider is registered and the related frontend, command, or extension point is active.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Layout Builder](../layout-builder/README.md), [Content Sections](../content-sections/README.md), [Media Ai](../media-ai/README.md), [Seo Suite](../seo-suite/README.md), [Translation Manager](../translation-manager/README.md).
- Focused tests: `vendor/bin/pest packages/ai-orchestrator/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
