<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\PreviewLayoutBuilderLayoutPlanAction;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorModuleFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\NotRunnableAIOrchestratorActionFixture;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;

it('runs real ai orchestrator health diagnostics', function (): void {
    CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName);
    (new AIOrchestratorServiceProvider(app()))->registeringPackage();

    $results = AiOrchestratorHealthCheck::runDiagnostics();
    $check = new AiOrchestratorHealthCheck;

    expect($results)->toHaveCount(6)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue()
        ->and($results
            ->filter(static fn (DoctorCheckResultData $result): bool => ! $result->passed)
            ->map(static fn (DoctorCheckResultData $result): array => $result->toArray())
            ->values()
            ->all())->toBe([])
        ->and(AiOrchestratorHealthCheck::passed())->toBeTrue()
        ->and($check->registryBindingIsHealthy())->toBeTrue()
        ->and($check->moduleCount())->toBeGreaterThan(0)
        ->and($check->layoutBuilderModuleIsAvailable())->toBeTrue()
        ->and($check->duplicateCapabilityProtectionIsHealthy())->toBeTrue()
        ->and($check->notRunnableCapabilities())->toBe([])
        ->and($results->first()?->label)->toBe(__('capell-ai-orchestrator::package.health_package_installed_label'));
});

it('fails health when the package is not marked installed', function (): void {
    CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName, false);

    try {
        $check = new AiOrchestratorHealthCheck;

        expect($check->packageInstalledCheck()->passed)->toBeFalse()
            ->and(AiOrchestratorHealthCheck::passed())->toBeFalse();
    } finally {
        CapellCore::forcePackageInstalled(AIOrchestratorServiceProvider::$packageName);
    }
});

it('fails health when the registry binding is broken', function (): void {
    app()->bind(AIOrchestratorModuleRegistry::class, static fn (): object => new stdClass);

    $check = new AiOrchestratorHealthCheck;

    expect($check->registryBindingIsHealthy())->toBeFalse()
        ->and($check->registryBindingCheck()->passed)->toBeFalse()
        ->and($check->moduleCount())->toBe(0)
        ->and($check->layoutBuilderModuleIsAvailable())->toBeFalse()
        ->and($check->notRunnableCapabilities())->toBe([
            __('capell-ai-orchestrator::package.health_runnable_actions_registry_missing'),
        ])
        ->and(AiOrchestratorHealthCheck::passed())->toBeFalse();
});

it('fails health when no modules are registered', function (): void {
    app()->instance(AIOrchestratorModuleRegistry::class, new AIOrchestratorModuleRegistry);

    $check = new AiOrchestratorHealthCheck;

    expect($check->moduleRegistryCheck()->passed)->toBeFalse()
        ->and($check->layoutBuilderModuleCheck()->passed)->toBeFalse()
        ->and(AiOrchestratorHealthCheck::passed())->toBeFalse();
});

it('reports registered capabilities with action classes that are not runnable', function (): void {
    $registry = new AIOrchestratorModuleRegistry;
    $registry->register(new AIOrchestratorModuleFixture(
        moduleKey: 'not-runnable-module',
        capabilityKey: 'not-runnable-capability',
        actionClass: NotRunnableAIOrchestratorActionFixture::class,
    ));
    app()->instance(AIOrchestratorModuleRegistry::class, $registry);

    $check = new AiOrchestratorHealthCheck;

    expect($check->notRunnableCapabilities())->toBe(['not-runnable-module:not-runnable-capability'])
        ->and($check->runnableCapabilityActionsCheck()->passed)->toBeFalse()
        ->and(AiOrchestratorHealthCheck::passed())->toBeFalse();
});

it('confirms duplicate capability protection without mutating the runtime registry', function (): void {
    $registry = app()->make(AIOrchestratorModuleRegistry::class);
    $moduleCount = count($registry->modules());

    $check = new AiOrchestratorHealthCheck;

    expect($check->duplicateCapabilityProtectionIsHealthy())->toBeTrue()
        ->and($registry->modules())->toHaveCount($moduleCount)
        ->and(class_exists(PreviewLayoutBuilderLayoutPlanAction::class))->toBeTrue();
});
