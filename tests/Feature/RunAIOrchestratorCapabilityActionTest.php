<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\RegisterAIOrchestratorModuleAction;
use Capell\AIOrchestrator\Actions\RunAIOrchestratorCapabilityAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorModuleFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\NotRunnableAIOrchestratorActionFixture;

it('runs a capability through the registered package action', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'run-module',
        capabilityKey: 'run-capability',
    ));

    $result = RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'run-module',
        capabilityKey: 'run-capability',
        prompt: 'Create a sidebar layout',
        context: ['page' => 'home'],
    ));

    expect($result)->toBe([
        'prompt' => 'Create a sidebar layout',
        'page' => 'home',
    ]);
});

it('throws when a capability action class does not exist', function (): void {
    $missingActionClass = 'Capell\\AIOrchestrator\\Tests\\Fixtures\\Autoload\\MissingAIOrchestratorActionFixture';

    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'missing-action-module',
        capabilityKey: 'missing-action-capability',
        actionClass: $missingActionClass,
    ));

    expect(fn (): mixed => RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'missing-action-module',
        capabilityKey: 'missing-action-capability',
        prompt: 'Create a sidebar layout',
    )))->toThrow(
        RuntimeException::class,
        'AIOrchestrator capability [missing-action-module:missing-action-capability] action [' . $missingActionClass . '] is not runnable.',
    );
});

it('throws when a capability action class has no run method', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'not-runnable-action-module',
        capabilityKey: 'not-runnable-action-capability',
        actionClass: NotRunnableAIOrchestratorActionFixture::class,
    ));

    expect(fn (): mixed => RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'not-runnable-action-module',
        capabilityKey: 'not-runnable-action-capability',
        prompt: 'Create a sidebar layout',
    )))->toThrow(
        RuntimeException::class,
        'AIOrchestrator capability [not-runnable-action-module:not-runnable-action-capability] action [' . NotRunnableAIOrchestratorActionFixture::class . '] is not runnable.',
    );
});
