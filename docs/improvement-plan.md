# AI Orchestrator - Improvement & Growth Plan

> Package: capell-app/ai-orchestrator · Kind: package · Tier: premium · Product group: Capell Commercial · Bundle: commercial · Status: Active

## 1. Snapshot

AI Orchestrator is a headless shared registry and execution layer for AI-assisted Capell capabilities. It currently registers an `AIOrchestratorModuleRegistry`, adds the Layout Builder module after the package is installed, lists modules/capabilities, and runs capability action classes. Tests cover registry behavior, capability execution, package boundaries, and Layout Builder integration. The package is useful as a developer substrate, but the health check is a placeholder, provider registration can miss an already-resolved registry, and there is no approval/audit persistence even though the package language and screenshots describe governance.

## 2. Improvements (existing functionality)

1. **Make module registration robust when the registry is already resolved.** The provider only registers the Layout Builder module in an `afterResolving()` callback inside `registerServices()`. If `AIOrchestratorModuleRegistry` has already been resolved before that callback is attached, the module can be missing. Add immediate registration when the registry is already resolved and test both paths. Evidence: `src/Providers/AIOrchestratorServiceProvider.php`, `tests/Unit/LayoutBuilderIntegrationCoverageTest.php`. - **S**

2. **Implement real health diagnostics.** `AiOrchestratorHealthCheck` only reports compatible API version. Add diagnostics for package installation, registry binding, module count, Layout Builder module availability, duplicate capability protection, and runnable action classes. Evidence: `src/Health/AiOrchestratorHealthCheck.php`, `src/Support/AIOrchestratorModuleRegistry.php`, `src/Actions/RunAIOrchestratorCapabilityAction.php`. - **M**

3. **Add approval and audit execution records.** `RunAIOrchestratorCapabilityAction` executes capability action classes directly after a runnable check. Add a typed run record or event/audit bridge so approval-level decisions are observable and can be surfaced by consuming packages. Evidence: `src/Data/AIOrchestratorRunData.php`, `src/Enums/AIOrchestratorApprovalLevel.php`, `RunAIOrchestratorCapabilityAction`. - **L**

4. **Clarify headless package positioning in docs and screenshots.** README currently states no concrete admin page exists, and screenshot entries are optional consuming-package surfaces. Docs should say this package is an infrastructure layer until a consuming package or admin catalog is installed. Evidence: `README.md`, `docs/screenshots.json`, `capell.json marketplace.screenshots`. - **S**

## 3. Missing Features (gaps)

Capabilities declared: `ai-orchestrator` and `ai-orchestrator-admin`.

- **No real health check.** Installers cannot tell whether modules/capabilities are registered.
- **No approval workflow persistence.** Approval levels exist as data, but execution is not recorded or gated by a first-class approval store.
- **No admin catalog.** The package declares an admin surface but contributes no resource/page.
- **No Marketplace media.** This is currently correct for a headless package, but buyer-facing media needs a real consuming surface.

## 4. Issues / Risks

1. **Important risk: module registration can be missed if the registry is resolved early.** Recommended fix: register immediately when resolved and keep the after-resolving path for later resolution. - **P2**

2. **Important gap: health is a stub.** Recommended fix: diagnostics for registry, modules, capabilities, and runnable Actions. - **P2**

3. **Important gap: approval-level language is not backed by persistent governance.** Recommended fix: add run/audit records or a consuming governance bridge before marketing approval workflows. - **P2**

4. **Improvement: package positioning is too UI-adjacent for a headless layer.** Recommended fix: docs and marketplace copy that distinguish core orchestration from consuming admin surfaces. - **P3**

## 5. Marketplace & Positioning

AI Orchestrator should be sold as Capell's shared AI capability backbone. For buyers, it is mostly invisible until paired with packages that expose AI workflows. For developers, it provides a central registry, typed run data, approval metadata, and capability execution semantics.

**Current summary:** "Shared AI capability registry and execution layer for Capell packages."

**Improved summary:** "A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows."

**Media status:** Keep marketplace media empty until a real admin capability catalog or consuming-package AI workflow can be captured.

**Cross-sell:** Agent Bridge for MCP/client access, Automation Studio for queued AI actions, Layout Builder for AI-assisted layout generation, Diagnostics for health visibility.

## 6. Prioritized Roadmap

| Item                                                     | Bucket | Effort | Impact | Section ref |
| -------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Register modules when registry has already been resolved | Now    | S      | High   | §2.1, §4.1  |
| Add real registry/module/capability health diagnostics   | Now    | M      | High   | §2.2, §4.2  |
| Rewrite docs around headless orchestrator positioning    | Now    | S      | Medium | §2.4, §4.4  |
| Add approval/audit run persistence                       | Next   | L      | High   | §2.3, §4.3  |
| Add admin capability catalog surface                     | Next   | M      | Medium | §3, §5      |
| Add policy/scope checks before capability execution      | Next   | M      | High   | §3          |
| Add consuming-package screenshot scenarios               | Later  | M      | Medium | §3, §5      |
| Add provider adapters for external LLM policy guardrails | Later  | L      | Medium | §5          |

## 7. Verification

Plan-writing review only; no commands were run for this package in this pass. First implementation slice should start with:

```bash
vendor/bin/pest packages/ai-orchestrator/tests --configuration=phpunit.xml
```

For provider/health changes, include:

```bash
vendor/bin/pest packages/ai-orchestrator/tests/Unit/AIOrchestratorModuleRegistryTest.php packages/ai-orchestrator/tests/Unit/LayoutBuilderIntegrationCoverageTest.php --configuration=phpunit.xml
```

## 8. Completion Checklist

- [x] Package plan created from current code, manifest, docs, screenshots, and tests.
- [x] Comprehensive local review pass completed for provider, registry, Actions, health, tests, docs, and marketplace media.
- [x] Capell audience pass completed for package developers, operators, and buyers.
- [ ] Approved implementation slices shipped.
- [ ] Focused AI Orchestrator verification passed.
- [ ] Package tests passed.
- [ ] Repo preflight passed for changed files.
