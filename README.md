# Assistant

Status: **Available, no schema impact** · Kind: **package** · Tier: **premium** · Bundle: **commercial** · Contexts: **admin** · Product group: **Capell Commercial**

## What This Plugin Adds

Assistant provides the orchestration layer for Capell assistant modules and capability execution.

- Assistant module registry.
- Contracts for modules and provider connectors.
- Actions for listing, registering, and running capabilities.
- Mosaic integration module for layout planning preview.

## Why It Matters

**For developers:** Defines the module and capability contracts other packages can use without putting AI workflow logic into resources or controllers.

**For teams:** Lets Capell installations add assisted workflows while keeping approvals and capability boundaries explicit.

## Screens And Workflow

Screenshots are generated from [docs/screenshots.json](docs/screenshots.json) during package deployment.

- Capability list or prompt surface where provided by a consuming package.
- Mosaic layout preview workflow if Mosaic integration is enabled.
- Approval state where a capability requires review.

## Technical Shape

- AssistantServiceProvider registers assistant services.
- Contracts: AssistantModule and AssistantProviderConnector.
- Actions: ListAssistantCapabilitiesAction, RegisterAssistantModuleAction, RunAssistantCapabilityAction.
- Data objects describe capabilities and runs.
- Enums model approval level.

## Data Model

- This package does not own database tables.
- State is passed through data objects and consuming package integrations.
- Persistence, if needed, belongs to the package that runs the capability.

## Install Impact

- Adds assistant service bindings and module registry.
- No migrations.
- No routes in this package.
- No Filament resource is registered by this package alone.

## Commands

- None proven in this package directory.

## Admin And Access

- None proven in this package directory.

- None proven in this package directory.

## Common Pitfalls

- Install the package that supplies the assistant surface before expecting UI.
- Treat capability output as reviewable draft data unless the consuming package proves otherwise.
- Provider connector configuration belongs to the consuming assistant integration.

## Quick Start

1. Install the package with `composer require capell-app/assistant`.
2. Register the package provider through Composer discovery and clear cached config if the host app uses config caching.
3. Open the new admin surface or integration point and verify the result.

## Next Steps

- [docs/overview.md](docs/overview.md)
- [../mosaic/README.md](../mosaic/README.md)
- [../mcp/README.md](../mcp/README.md)
