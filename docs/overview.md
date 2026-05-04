# Assistant

Status: **Available, no schema impact** · Kind: **package** · Tier: **premium** · Bundle: **commercial** · Contexts: **admin** · Product group: **Capell Commercial**

This page is the consolidated implementation overview for the Assistant package. It is extracted from the package README, service providers, migrations, config files, routes, resources, models, actions, and the shared Capell ERD notes where available.

## What This Plugin Adds

Assistant provides the orchestration layer for Capell assistant modules and capability execution.

- Assistant module registry.
- Contracts for modules and provider connectors.
- Actions for listing, registering, and running capabilities.
- Mosaic integration module for layout planning preview.

## Developer Notes

Defines the module and capability contracts other packages can use without putting AI workflow logic into resources or controllers.

- AssistantServiceProvider registers assistant services.
- Contracts: AssistantModule and AssistantProviderConnector.
- Actions: ListAssistantCapabilitiesAction, RegisterAssistantModuleAction, RunAssistantCapabilityAction.
- Data objects describe capabilities and runs.
- Enums model approval level.

## Operational Notes

Lets Capell installations add assisted workflows while keeping approvals and capability boundaries explicit.

- Adds assistant service bindings and module registry.
- No migrations.
- No routes in this package.
- No Filament resource is registered by this package alone.

## Data And Retention

- This package does not own database tables.
- State is passed through data objects and consuming package integrations.
- Persistence, if needed, belongs to the package that runs the capability.

## Screenshot Plan

- Capability list or prompt surface where provided by a consuming package.
- Mosaic layout preview workflow if Mosaic integration is enabled.
- Approval state where a capability requires review.

## Pitfalls

- Install the package that supplies the assistant surface before expecting UI.
- Treat capability output as reviewable draft data unless the consuming package proves otherwise.
- Provider connector configuration belongs to the consuming assistant integration.

## Verification

- Run `vendor/bin/pest packages/assistant/tests` when package tests exist.
- Run the relevant host-app migration or package install flow in a disposable database.
- Open the listed admin or frontend surface and compare it with the screenshot plan.

## Package Manifest

- Composer name: `capell-app/assistant`
- Product group: Capell Commercial
- Kind: package
- Tier: premium
- Bundle: commercial
- Contexts: `admin`
- Requires: `capell-app/admin`
- Optional dependencies: None listed.

## Admin Surfaces

- None proven in this package directory.

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

Deployment should read [screenshots.json](screenshots.json), install the package with demo data, resolve each admin surface or frontend URL, and write images to `public/docs/screenshots/packages/assistant`.

- Capability list or prompt surface where provided by a consuming package.
- Mosaic layout preview workflow if Mosaic integration is enabled.
- Approval state where a capability requires review.
