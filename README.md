# AI Orchestrator

<!-- prettier-ignore-start -->

## What This Plugin Adds

AI Orchestrator is an **Available**, **Schema-owning** Capell package in the **Capell AI** product group. It ships as `capell-app/ai-orchestrator` and extends these surfaces: admin.

AI Orchestrator provides a shared capability registry, governed execution path, generation history, and request queue for AI features owned by other Capell packages.

Admins can inspect registered capability metadata in the capability catalog. Consuming packages keep ownership of their editor actions and approval screens.

Evidence: [`capell.json`](capell.json), [`src/Support/AIOrchestratorModuleRegistry.php`](src/Support/AIOrchestratorModuleRegistry.php), [`src/Actions/RunAIOrchestratorCapabilityAction.php`](src/Actions/RunAIOrchestratorCapabilityAction.php), [`src/Filament/Pages/AIOrchestratorCapabilityCatalogPage.php`](src/Filament/Pages/AIOrchestratorCapabilityCatalogPage.php), [`tests/Feature/AIOrchestratorCapabilityCatalogPageTest.php`](tests/Feature/AIOrchestratorCapabilityCatalogPageTest.php), [`docs/screenshots.json`](docs/screenshots.json).

Status details:

- Status: Available
- Tier: free
- Bundle: ai
- Composer package: `capell-app/ai-orchestrator`
- Namespace: `Capell\AIOrchestrator`
- Theme key: not applicable

## Why It Matters

**For developers:** Packages implement AIOrchestratorModule and register typed capabilities, while RunAIOrchestratorCapabilityAction enforces runnable actions, authorization, and policy guardrails in one place.

**For teams:** AI-assisted features across Capell share the same registration, approval, execution, and recording boundaries instead of behaving differently in each package.

Evidence: [`src/Contracts/AIOrchestratorModule.php`](src/Contracts/AIOrchestratorModule.php), [`src/Contracts/AIOrchestratorPolicyGuardrail.php`](src/Contracts/AIOrchestratorPolicyGuardrail.php), [`src/Actions/RunAIOrchestratorCapabilityAction.php`](src/Actions/RunAIOrchestratorCapabilityAction.php), [`tests/Feature/RunAIOrchestratorCapabilityActionTest.php`](tests/Feature/RunAIOrchestratorCapabilityActionTest.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`tests/Feature/Integrations/AIAuthoringModuleTest.php`](tests/Feature/Integrations/AIAuthoringModuleTest.php), [`tests/Feature/Actions/QueueAiAssistantGenerationActionTest.php`](tests/Feature/Actions/QueueAiAssistantGenerationActionTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Illustrative AI Orchestrator Beta contract from capability registration to consumer-owned UI](docs/assets/marketplace/beta-contract.svg)

- AI Orchestrator beta contract artwork (marketplace, supplementary evidence).

## Technical Shape

### Service providers

- `Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider`

### Config files

- `packages/ai-orchestrator/config/capell-ai-orchestrator.php`

### Migrations

- `packages/ai-orchestrator/database/migrations/2026_05_10_190870_02_create_ai_generation_histories_table.php`
- `packages/ai-orchestrator/database/migrations/2026_06_08_000001_add_cost_fields_to_ai_generation_histories_table.php`
- `packages/ai-orchestrator/database/migrations/2026_07_10_000001_add_site_id_to_ai_generation_histories_table.php`
- `packages/ai-orchestrator/database/migrations/2026_07_10_000002_create_ai_generation_requests_table.php`
- `packages/ai-orchestrator/database/migrations/2026_07_10_000003_encrypt_ai_generation_history_payloads.php`
- `packages/ai-orchestrator/database/migrations/2026_07_10_000003_encrypt_ai_generation_requests_table.php`
- `packages/ai-orchestrator/database/migrations/2026_07_20_000001_create_ai_managed_provider_calls_table.php`

### Settings migrations

- `packages/ai-orchestrator/database/settings/2026_05_10_190871_01_create_ai-orchestrator_settings.php`
- `packages/ai-orchestrator/database/settings/2026_09_04_213000_01_encrypt_ai_orchestrator_api_key.php`

### Settings classes

- `AIOrchestratorSettings`

### Models

- `AIGenerationHistory`
- `AiGenerationRequest`
- `ManagedProviderCall`

### Filament classes

- `AIOrchestratorCapabilityCatalogPage`
- `AIOrchestratorSettingsSchema`

### Extension contracts

- `AIOrchestratorModule`
- `AIOrchestratorPolicyGuardrail`
- `AiActionContextInterface`
- `AiCreatorContextInterface`
- `ProviderOutputValidator`
- `VersionedContract`

### Events

- `AIOrchestratorCapabilityRunRecorded`
- `AiGenerationCompleted`
- `AiGenerationFailed`
- `AiGenerationStarted`

### Listeners

- `LogAiGeneration`
- `NotifyAiFailure`

### Actions

- `BuildManagedProviderExecutionReceiptAction`
- `GeneratorPageContentAction`
- `PruneAiGenerationPayloadsAction`
- `RecordAiGenerationAction`
- `SuggestMetaDescriptionsAction`
- `SuggestPageTitlesAction`
- `GenerateAiAssistantFieldsAction`
- `ListAIOrchestratorCapabilitiesAction`
- `QueueAiAssistantGenerationAction`
- `RegisterAIOrchestratorModuleAction`
- `ResolveAIOrchestratorRuntimeSettingsAction`
- `RunAIOrchestratorCapabilityAction`

### Data objects

- `AIOrchestratorCapabilityData`
- `AIOrchestratorRunData`
- `AIOrchestratorRuntimeSettingsData`
- `AiGenerationInputData`
- `AiGenerationResultData`
- `ManagedProviderCallClaimData`
- `ManagedProviderExecutionReceiptData`
- `MeasuredUsageData`
- `ProviderAttemptEvidenceData`
- `ProviderCallAllowanceData`
- `ProviderCallAuthorizationData`
- `ProviderExecutionResultData`
- `ProviderIdempotencyHeaderData`
- `TerminalSettlementResultData`
- `UsableVersionAttachmentData`
- `ValidatedProviderOutputData`
- `AiAssistantGenerationData`
- `AiAssistantGenerationResultData`

### Jobs

- `RunAiAssistantGenerationJob`

### Command signatures

- `capell:ai-orchestrator:prune-generation-payloads`

### Scheduled commands

- `capell:ai-orchestrator:prune-generation-payloads (daily; package registered)`

### Console command classes

- `PruneAiGenerationPayloadsCommand`

### Manifest contributions

- `admin-page: Capell\AIOrchestrator\Manifest\AiOrchestratorAdminPageContribution`
- `console-command: Capell\AIOrchestrator\Manifest\AiOrchestratorPruneScheduleContribution`
- `scheduled-job: Capell\AIOrchestrator\Manifest\AiOrchestratorPruneScheduleContribution`

### Health checks

- `Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck`

### Blade views

- `packages/ai-orchestrator/resources/views/filament/pages/capability-catalog.blade.php`


## Data Model

- Required tables: `ai_generation_histories`, `ai_generation_requests`, `ai_managed_provider_calls`.
- Models: `AIGenerationHistory`, `AiGenerationRequest`, `ManagedProviderCall`.
- Core record references in migrations: `sites via site_id`, `languages via language_id`.
- Migration files: `2026_05_10_190870_02_create_ai_generation_histories_table.php`, `2026_06_08_000001_add_cost_fields_to_ai_generation_histories_table.php`, `2026_07_10_000001_add_site_id_to_ai_generation_histories_table.php`, `2026_07_10_000002_create_ai_generation_requests_table.php`, `2026_07_10_000003_encrypt_ai_generation_history_payloads.php`, `2026_07_10_000003_encrypt_ai_generation_requests_table.php`, `2026_07_20_000001_create_ai_managed_provider_calls_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: retention is scheduled through `capell:ai-orchestrator:prune-generation-payloads` (daily; registered by the package provider).

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`.
- Admin navigation: declares `admin-page: AiOrchestratorAdminPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: none declared.
- Permissions: no package permission declarations or Shield gates detected; host access rules still apply.
- Public routes: none declared.
- Database changes: package migrations are declared.
- Config: `config/capell-ai-orchestrator.php`.
- Settings: `Capell\AIOrchestrator\Settings\AIOrchestratorSettings`.
- Queues or schedules: scheduled commands `capell:ai-orchestrator:prune-generation-payloads (daily; package registered)`; queue jobs `RunAiAssistantGenerationJob`.
- Cache tags: none declared.
- Commands: `capell:ai-orchestrator:prune-generation-payloads`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-ai-orchestrator.php`, `Capell\AIOrchestrator\Settings\AIOrchestratorSettings`.
- Keep the host Laravel scheduler running so package-registered schedules can execute: `capell:ai-orchestrator:prune-generation-payloads (daily; package registered)`.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |

## Quick Start

1. Install the package: `composer require capell-app/ai-orchestrator`.
2. Open the package admin page or resource and verify AI Orchestrator is available.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- Configuration files: [`config/capell-ai-orchestrator.php`](config/capell-ai-orchestrator.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Ai Creator](../ai-creator/README.md), [Content Sections](../content-sections/README.md), [Layout Builder](../layout-builder/README.md), [Media Ai](../media-ai/README.md), [Seo Suite](../seo-suite/README.md), [Translation Manager](../translation-manager/README.md).
- Focused tests: `vendor/bin/pest packages/ai-orchestrator/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
