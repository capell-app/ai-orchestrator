<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\LayoutBuilderAIOrchestratorModule;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\PreviewLayoutBuilderLayoutPlanAction;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\LayoutBuilder\Data\LayoutPlanResultData;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
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
        ->and(AiOrchestratorHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('makes layout planning available after the package boots', function (bool $resolveBeforeBoot): void {
    InstalledRuntimeState::run(function () use ($resolveBeforeBoot): void {
        $registry = null;
        $application = InstalledRuntimeTestCase::boot(
            InstalledRuntimeParity::manifest('ai-orchestrator'),
            true,
            function (Application $application) use ($resolveBeforeBoot, &$registry): void {
                CapellCore::forcePackageInstalled('capell-app/layout-builder');
                if ($resolveBeforeBoot) {
                    $application->booting(function () use ($application, &$registry): void {
                        $registry = $application->make(AIOrchestratorModuleRegistry::class);
                    });
                }
            },
        );
        $registry ??= $application->make(AIOrchestratorModuleRegistry::class);
        expect($registry->module('layout-builder')->capabilities()[0]->key)->toBe('preview-layout-plan');
    });
})->with(['registry resolved after boot' => false, 'registry resolved before boot' => true]);

it('delegates layout builder preview planning through the integration action', function (): void {
    $run = new AIOrchestratorRunData(
        moduleKey: 'layout-builder',
        capabilityKey: 'preview-layout-plan',
        prompt: 'Draft a landing page',
        context: ['page' => 'home'],
    );

    $result = PreviewLayoutBuilderLayoutPlanAction::run($run);

    expect($result)->toBeInstanceOf(LayoutPlanResultData::class);
    throw_unless($result instanceof LayoutPlanResultData, RuntimeException::class, 'Expected a preview layout plan result.');
    expect($result->plan->prompt)->toBe('Draft a landing page');
});
