<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\ListAIOrchestratorCapabilitiesAction;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;

it('registers the authoring module with three capabilities', function (): void {
    $registry = app(AIOrchestratorModuleRegistry::class);

    expect(array_keys($registry->modules()))->toContain('ai-authoring');

    $capabilityKeys = collect(app(ListAIOrchestratorCapabilitiesAction::class)->handle())
        ->pluck('key')
        ->all();

    expect($capabilityKeys)
        ->toContain('suggest-title')
        ->toContain('generate-content')
        ->toContain('suggest-meta-description');
});
