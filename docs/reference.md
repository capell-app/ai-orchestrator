# AI Orchestrator reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/ai-orchestrator`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

AI Orchestrator is an **Available**, **Schema-owning** Capell package in the **Capell AI** product group. It ships as `capell-app/ai-orchestrator` and extends these surfaces: admin.

- Status: Available
- Tier: free
- Bundle: ai
- Composer package: `capell-app/ai-orchestrator`
- Namespace: `Capell\AIOrchestrator`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\AIOrchestrator\Providers\MigrationMetadataServiceProvider`
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
- `AIOrchestratorSettingsRepository`

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

<!-- prettier-ignore-end -->
