<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\AIOrchestrator\Health\AiOrchestratorHealthCheck;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\LayoutBuilderAIOrchestratorModule;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\PreviewLayoutBuilderLayoutPlanAction;
use Capell\LayoutBuilder\Data\LayoutPlanResultData;

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
