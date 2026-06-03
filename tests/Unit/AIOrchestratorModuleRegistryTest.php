<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\ListAIOrchestratorCapabilitiesAction;
use Capell\AIOrchestrator\Actions\RegisterAIOrchestratorModuleAction;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorModuleFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\DuplicateCapabilityAIOrchestratorModuleFixture;

it('registers shallow ai-orchestrator modules and lists their capabilities', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture);

    $capabilities = ListAIOrchestratorCapabilitiesAction::run();

    if (! is_array($capabilities)) {
        throw new RuntimeException('AI Orchestrator capabilities must be an array.');
    }

    $capability = collect($capabilities)
        ->first(fn (AIOrchestratorCapabilityData $candidateCapability): bool => $candidateCapability->key === 'test-capability');

    if (! $capability instanceof AIOrchestratorCapabilityData) {
        throw new RuntimeException('The test capability was not registered.');
    }

    expect($capability->approvalLevel)->toBe(AIOrchestratorApprovalLevel::Draft);
});

it('throws when a module key is registered twice', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(moduleKey: 'duplicate-module'));

    expect(fn (): mixed => RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(moduleKey: 'duplicate-module')))
        ->toThrow(InvalidArgumentException::class, 'AIOrchestrator module [duplicate-module] is already registered.');
});

it('throws when a module registers duplicate capability keys', function (): void {
    expect(fn (): mixed => RegisterAIOrchestratorModuleAction::run(new DuplicateCapabilityAIOrchestratorModuleFixture))
        ->toThrow(InvalidArgumentException::class, 'AIOrchestrator module [duplicate-capability-module] registers duplicate capability [duplicate-capability].');
});

it('throws when a module is not registered', function (): void {
    expect(fn (): mixed => resolve(AIOrchestratorModuleRegistry::class)->module('missing-module'))
        ->toThrow(InvalidArgumentException::class, 'AIOrchestrator module [missing-module] is not registered.');
});

it('throws when a capability is not registered', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(moduleKey: 'known-module'));

    expect(fn (): mixed => resolve(AIOrchestratorModuleRegistry::class)->capability('known-module', 'missing-capability'))
        ->toThrow(InvalidArgumentException::class, 'AIOrchestrator capability [known-module:missing-capability] is not registered.');
});
