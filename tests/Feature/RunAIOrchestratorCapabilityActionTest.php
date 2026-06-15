<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\RegisterAIOrchestratorModuleAction;
use Capell\AIOrchestrator\Actions\RunAIOrchestratorCapabilityAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorRunStatus;
use Capell\AIOrchestrator\Events\AIOrchestratorCapabilityRunRecorded;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorModuleFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\FailingAIOrchestratorRunActionFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\NotRunnableAIOrchestratorActionFixture;
use Illuminate\Support\Facades\Event;

it('runs a capability through the registered package action', function (): void {
    Event::fake([AIOrchestratorCapabilityRunRecorded::class]);

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

    Event::assertDispatched(
        AIOrchestratorCapabilityRunRecorded::class,
        fn (AIOrchestratorCapabilityRunRecorded $event): bool => $event->run->moduleKey === 'run-module'
            && $event->run->capabilityKey === 'run-capability'
            && $event->capability->key === 'run-capability'
            && $event->status === AIOrchestratorRunStatus::Succeeded
            && $event->result === $result
            && $event->exception === null,
    );
});

it('records a failed capability run before rethrowing the exception', function (): void {
    Event::fake([AIOrchestratorCapabilityRunRecorded::class]);

    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'failing-run-module',
        capabilityKey: 'failing-run-capability',
        actionClass: FailingAIOrchestratorRunActionFixture::class,
    ));

    expect(fn (): mixed => RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'failing-run-module',
        capabilityKey: 'failing-run-capability',
        prompt: 'Create a sidebar layout',
    )))->toThrow(RuntimeException::class, 'The AI Orchestrator fixture failed for failing-run-capability.');

    Event::assertDispatched(
        AIOrchestratorCapabilityRunRecorded::class,
        fn (AIOrchestratorCapabilityRunRecorded $event): bool => $event->run->moduleKey === 'failing-run-module'
            && $event->run->capabilityKey === 'failing-run-capability'
            && $event->capability->key === 'failing-run-capability'
            && $event->status === AIOrchestratorRunStatus::Failed
            && $event->result === null
            && $event->exception instanceof RuntimeException,
    );
});

it('throws when a capability action class does not exist', function (): void {
    $missingActionClass = 'Capell\\AIOrchestrator\\Tests\\Fixtures\\Autoload\\MissingAIOrchestratorActionFixture';

    expect(fn (): mixed => RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'missing-action-module',
        capabilityKey: 'missing-action-capability',
        actionClass: $missingActionClass,
    )))->toThrow(
        RuntimeException::class,
        'The configured AI Orchestrator action fixture does not exist.',
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
