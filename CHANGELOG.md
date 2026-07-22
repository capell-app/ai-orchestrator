# Changelog

All notable changes to `capell-app/ai-orchestrator` will be documented in this file.

## Unreleased

- Removed producer-owned pricing, spend reservation, release, and cost-estimation APIs. Managed execution now relies exclusively on caller-issued non-financial allowances and opaque reservation evidence.

### 2.0 migration

- Remove calls to `AiSpendGuard`, `AiSpendReservationData`, `AssertAiModelPriceConfiguredAction`, `AssertAiRequestBudgetAction`, and `EstimateAiGenerationCostAction`.
- Move financial authorization and settlement to the consuming application. Pass only caller-issued execution allowances and opaque `reservation_reference` evidence to managed provider calls.
- Stop treating legacy `ai_generation_histories.cost_micros` and `cost_currency` columns as live producer-owned cost authority. They remain in historical schemas only for upgrade compatibility.

- Prepared package metadata and documentation for ongoing Capell 0.0.x package work.
