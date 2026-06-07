# AIOrchestrator

Status: **Available, no schema impact** · Kind: **package** · Tier: **premium** · Bundle: **commercial** · Contexts: **admin** · Product group: **Capell Commercial**

This page is the consolidated implementation overview for the AIOrchestrator package. It is extracted from the package README, service providers, migrations, config files, routes, resources, models, actions, and the shared Capell ERD notes where available.

## What This Package Adds

AI Orchestrator provides the shared layer for Capell AI modules and capability execution.

- AIOrchestrator module registry.
- Contract for package-provided AI modules.
- Actions for listing, registering, and running capabilities.
- Layout Builder integration module for layout planning preview.

## Developer Notes

Defines the module and capability contracts other packages can use without putting AI workflow logic into resources or controllers.

- AIOrchestratorServiceProvider registers ai-orchestrator services.
- Contract: AIOrchestratorModule.
- Actions: ListAIOrchestratorCapabilitiesAction, RegisterAIOrchestratorModuleAction, RunAIOrchestratorCapabilityAction.
- Data objects describe capabilities and runs.
- Enums model approval level.

## Operational Notes

Lets Capell installations add assisted workflows while keeping approvals and capability boundaries explicit.

- Adds ai-orchestrator service bindings and module registry.
- No migrations.
- No routes in this package.
- No Filament resource is registered by this package alone.

## Data And Retention

- This package does not own database tables.
- State is passed through data objects and consuming package integrations.
- Persistence, if needed, belongs to the package that runs the capability.

## Screenshot Plan

- Capability list or prompt surface where provided by a consuming package.
- Layout Builder preview workflow when the integration is enabled.
- Approval state where a capability requires review.

Current marketplace media is intentionally empty. The previous promoted PNGs showed generic Extensions/Layout Builder pages rather than a styled AI Orchestrator capability flow, so they remain runner evidence only until a consuming package exposes a real AI capability surface.

## Pitfalls

- Install the package that supplies the ai-orchestrator surface before expecting UI.
- Treat capability output as reviewable draft data unless the consuming package proves otherwise.
- Provider and prompt configuration belongs to the consuming AI integration until a provider abstraction ships here.

## Verification

- Run `vendor/bin/pest packages/ai-orchestrator/tests` when package tests exist.
- Run the relevant host-app migration or package install flow in a disposable database.
- Open the listed admin or frontend surface and compare it with the screenshot plan.

## Package Manifest

- Composer name: `capell-app/ai-orchestrator`
- Product group: Capell Commercial
- Kind: package
- Tier: premium
- Bundle: commercial
- Contexts: `admin`
- Requires: `capell-app/admin`, `capell-app/core`, `capell-app/layout-builder`
- Optional dependencies: None listed.

## Admin Surfaces

- None proven in this package directory. This package registers orchestration services and a Layout Builder AI module, but no standalone Filament navigation item.

## Commands

- None proven in this package directory.

## Routes And Config

- None proven in this package directory.

## Permissions And Gates

- None proven in this package directory.

## Migrations

- None proven in this package directory.

## ERD Excerpt

This package has no committed ERD excerpt. Use implementation notes and extension points instead of inventing schema.

## Screenshot Automation

Deployment should read [screenshots.json](screenshots.json), install the package with demo data, resolve each admin surface or frontend URL, and write images to `packages/ai-orchestrator/docs/screenshots`.

- Capability list or prompt surface where provided by a consuming package.
- Layout Builder preview workflow when the integration is enabled.
- Approval state where a capability requires review.
