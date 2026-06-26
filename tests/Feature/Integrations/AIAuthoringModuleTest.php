<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\ListAIOrchestratorCapabilitiesAction;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;

it('registers the authoring module with three capabilities', function (): void {
    $registry = app(AIOrchestratorModuleRegistry::class);

    $modules = $registry->modules();

    expect(array_keys($modules))->toContain('ai-authoring');

    $authoringCapabilityKeys = collect($modules['ai-authoring']->capabilities())
        ->pluck('key')
        ->all();

    expect($authoringCapabilityKeys)
        ->toHaveCount(3)
        ->toContain('suggest-title')
        ->toContain('generate-content')
        ->toContain('suggest-meta-description');

    $allCapabilityKeys = collect(app(ListAIOrchestratorCapabilitiesAction::class)->handle())
        ->pluck('key')
        ->all();

    expect($allCapabilityKeys)
        ->toContain('suggest-title')
        ->toContain('generate-content')
        ->toContain('suggest-meta-description');
});
