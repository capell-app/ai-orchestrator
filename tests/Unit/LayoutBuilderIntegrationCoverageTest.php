<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\LayoutBuilderAIOrchestratorModule;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\PreviewLayoutBuilderLayoutPlanAction;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\LayoutBuilder\Data\LayoutPlanResultData;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;

it('describes the layout builder ai orchestrator module capability', function (): void {
    $module = new LayoutBuilderAIOrchestratorModule;
    $capabilities = $module->capabilities();

    expect($module->key())->toBe('layout-builder')
        ->and($module->label())->toBe('LayoutBuilder')
        ->and($capabilities)->toHaveCount(1)
        ->and($capabilities[0]->key)->toBe('preview-layout-plan')
        ->and($capabilities[0]->actionClass)->toBe(PreviewLayoutBuilderLayoutPlanAction::class)
        ->and($capabilities[0]->approvalLevel)->toBe(AIOrchestratorApprovalLevel::Draft)
        ->and(AiOrchestratorHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('registers the layout builder module when the registry resolves after services register', function (): void {
    $application = new Application;
    $application->singleton(AIOrchestratorModuleRegistry::class);

    aiOrchestratorRegisterServices($application);

    expect($application->make(AIOrchestratorModuleRegistry::class)->modules())
        ->toHaveKey('layout-builder');
});

it('registers the layout builder module when the registry was already resolved', function (): void {
    $application = new Application;
    $application->singleton(AIOrchestratorModuleRegistry::class);
    $registry = $application->make(AIOrchestratorModuleRegistry::class);

    aiOrchestratorRegisterServices($application);

    expect($registry->modules())
        ->toHaveKey('layout-builder');
});

it('delegates layout builder preview planning through the integration action', function (): void {
    $run = new AIOrchestratorRunData(
        moduleKey: 'layout-builder',
        capabilityKey: 'preview-layout-plan',
        prompt: 'Draft a landing page',
        context: ['page' => 'home'],
    );

    $result = PreviewLayoutBuilderLayoutPlanAction::run($run);

    expect($result)->toBeInstanceOf(LayoutPlanResultData::class)
        ->and($result->plan->prompt)->toBe('Draft a landing page');
});

function aiOrchestratorRegisterServices(Application $application): void
{
    $application->instance('config', new Repository);

    $provider = new AIOrchestratorServiceProvider($application);
    $method = new ReflectionMethod($provider, 'registerServices');
    $method->invoke($provider);
}
