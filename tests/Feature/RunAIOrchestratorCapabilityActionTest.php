<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\RegisterAIOrchestratorModuleAction;
use Capell\AIOrchestrator\Actions\RunAIOrchestratorCapabilityAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorRunStatus;
use Capell\AIOrchestrator\Events\AIOrchestratorCapabilityRunRecorded;
use Capell\AIOrchestrator\Exceptions\AIOrchestratorPolicyGuardrailException;
use Capell\AIOrchestrator\Support\AIOrchestratorPolicyGuardrailRegistry;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorActorFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorModuleFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorPolicyGuardrailFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\FailingAIOrchestratorRunActionFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\NotRunnableAIOrchestratorActionFixture;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

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

it('authorizes required capability abilities before execution', function (): void {
    Gate::define(
        'ai-orchestrator.run-safe-capability',
        fn (AIOrchestratorActorFixture $actor, AIOrchestratorRunData $run): bool => $run->context['allowed'] ?? false,
    );

    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'authorized-module',
        capabilityKey: 'authorized-capability',
        requiredAbility: 'ai-orchestrator.run-safe-capability',
    ));

    $result = RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'authorized-module',
        capabilityKey: 'authorized-capability',
        prompt: 'Create a sidebar layout',
        context: ['page' => 'home', 'allowed' => true],
        actor: new AIOrchestratorActorFixture,
    ));

    expect($result)->toBe([
        'prompt' => 'Create a sidebar layout',
        'page' => 'home',
    ]);
});

it('rejects capabilities that require an actor when none is provided', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'missing-actor-module',
        capabilityKey: 'missing-actor-capability',
        requiredAbility: 'ai-orchestrator.run-safe-capability',
    ));

    expect(fn (): mixed => RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'missing-actor-module',
        capabilityKey: 'missing-actor-capability',
        prompt: 'Create a sidebar layout',
    )))->toThrow(
        AuthorizationException::class,
        'AIOrchestrator capability [missing-actor-module:missing-actor-capability] requires ability [ai-orchestrator.run-safe-capability] but no actor was provided.',
    );
});

it('rejects capabilities when the actor lacks the required ability before execution', function (): void {
    Gate::define(
        'ai-orchestrator.run-denied-capability',
        fn (AIOrchestratorActorFixture $actor, AIOrchestratorRunData $run): bool => false,
    );
    Event::fake([AIOrchestratorCapabilityRunRecorded::class]);

    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'denied-module',
        capabilityKey: 'denied-capability',
        requiredAbility: 'ai-orchestrator.run-denied-capability',
    ));

    expect(fn (): mixed => RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'denied-module',
        capabilityKey: 'denied-capability',
        prompt: 'Create a sidebar layout',
        context: ['page' => 'home'],
        actor: new AIOrchestratorActorFixture,
    )))->toThrow(
        AuthorizationException::class,
        'AIOrchestrator capability [denied-module:denied-capability] is not authorized for ability [ai-orchestrator.run-denied-capability].',
    );

    Event::assertNotDispatched(AIOrchestratorCapabilityRunRecorded::class);
});

it('runs policy guardrails before capability execution', function (): void {
    resolve(AIOrchestratorPolicyGuardrailRegistry::class)->register(
        'fixture-policy',
        new AIOrchestratorPolicyGuardrailFixture,
    );

    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'guardrail-allowed-module',
        capabilityKey: 'guardrail-allowed-capability',
    ));

    $result = RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'guardrail-allowed-module',
        capabilityKey: 'guardrail-allowed-capability',
        prompt: 'Create a sidebar layout',
        context: ['page' => 'home', 'guardrail_allowed' => true],
    ));

    expect($result)->toBe([
        'prompt' => 'Create a sidebar layout',
        'page' => 'home',
    ]);
});

it('rejects a capability before execution when a policy guardrail denies it', function (): void {
    Event::fake([AIOrchestratorCapabilityRunRecorded::class]);

    resolve(AIOrchestratorPolicyGuardrailRegistry::class)->register(
        'fixture-policy',
        new AIOrchestratorPolicyGuardrailFixture,
    );

    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'guardrail-denied-module',
        capabilityKey: 'guardrail-denied-capability',
    ));

    expect(fn (): mixed => RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
        moduleKey: 'guardrail-denied-module',
        capabilityKey: 'guardrail-denied-capability',
        prompt: 'Create a sidebar layout',
        context: ['page' => 'home', 'guardrail_allowed' => false],
    )))->toThrow(
        AIOrchestratorPolicyGuardrailException::class,
        'Policy guardrail denied [guardrail-denied-module:guardrail-denied-capability].',
    );

    Event::assertNotDispatched(AIOrchestratorCapabilityRunRecorded::class);
});

it('rejects duplicate policy guardrail keys', function (): void {
    $registry = resolve(AIOrchestratorPolicyGuardrailRegistry::class);

    $registry->register('fixture-policy', new AIOrchestratorPolicyGuardrailFixture);

    expect(fn (): mixed => $registry->register('fixture-policy', new AIOrchestratorPolicyGuardrailFixture))
        ->toThrow(InvalidArgumentException::class, 'AIOrchestrator policy guardrail [fixture-policy] is already registered.');
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
