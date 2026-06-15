# AI Orchestrator

<!-- prettier-ignore-start -->

## What This Plugin Adds

AI Orchestrator is an **Available**, **No schema impact** Capell package in the **Capell Commercial** product group. It ships as `capell-app/ai-orchestrator` and provides an admin-safe headless orchestration layer for consuming Capell packages.

A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows.

After install, the package registers services, Actions, Data objects, health diagnostics, and package-owned capability contracts. It does not ship a standalone Filament resource, page, public route, or visitor-facing output; consuming packages provide any editor UI that lists, previews, approves, or runs capabilities.

Status details:

- Status: Available
- Tier: premium
- Bundle: commercial
- Composer package: `capell-app/ai-orchestrator`
- Namespace: `Capell\AIOrchestrator`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives package authors a central registry, typed run data, approval metadata, run-record events, and capability execution semantics without pushing AI workflow code into core or application code.

**For teams:** AI workflows stay consistent across packages: consuming extensions can register, run, and govern AI-assisted capabilities through one admin-safe layer while keeping their own UI and persistence.

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`. Marketplace screenshots intentionally remain empty until a consuming package or future admin catalog exposes a real workflow worth capturing.

- Capability list or prompt surface where provided by a consuming package (admin, optional).
- LayoutBuilder layout preview workflow if LayoutBuilder integration is enabled (admin, optional).
- Approval state where a capability requires review (admin, optional).

## Technical Shape

- Service providers: `Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider`.
- Actions: `ListAIOrchestratorCapabilitiesAction`, `RegisterAIOrchestratorModuleAction`, `RunAIOrchestratorCapabilityAction`.
- Data objects: `AIOrchestratorCapabilityData`, `AIOrchestratorRunData`.
- Events: `AIOrchestratorCapabilityRunRecorded` is dispatched after successful and failed capability runs so consuming governance packages can persist audit records without AI Orchestrator owning schema.
- Health checks: `Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck`.

## Extension Boundary

Consuming packages register modules with `RegisterAIOrchestratorModuleAction` or the shared registry. They own the editor workflow, durable approval storage, permission checks, and any public rendering they trigger after a run. AI Orchestrator owns the registry contract, capability metadata, execution dispatch, run-record event bridge, Layout Builder integration module, and diagnostics.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables. Approval levels and run results are represented as Data objects and `AIOrchestratorCapabilityRunRecorded` events; durable approval/audit storage belongs to a consuming governance package.

## Install Impact

- Admin navigation: none; consuming packages provide any Filament resource or page that displays registered capabilities.
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
3. Verify the package provider is registered, Diagnostics can see the health check, and consuming packages can register their AI Orchestrator modules.

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
