<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\GenerateAiAssistantFieldsAction;
use Capell\AIOrchestrator\Actions\RunAIOrchestratorCapabilityAction;
use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Data\AiAssistantGenerationResultData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;

/**
 * Swap the capability runner for a stub so the generation Action can be tested
 * in isolation from Gate checks, guardrails, and the real Prism integration.
 * AsObject::run() resolves the action via app(static::class), so a container
 * instance binding fully intercepts every RunAIOrchestratorCapabilityAction::run().
 */
function fakeCapabilityRunner(Closure $handler): void
{
    app()->instance(
        RunAIOrchestratorCapabilityAction::class,
        new class($handler) extends RunAIOrchestratorCapabilityAction
        {
            public function __construct(private Closure $handler) {}

            public function handle(AIOrchestratorRunData $run): mixed
            {
                return ($this->handler)($run);
            }
        },
    );
}

afterEach(function (): void {
    app()->forgetInstance(RunAIOrchestratorCapabilityAction::class);
});

it('runs each selected capability and returns its output keyed by field', function (): void {
    fakeCapabilityRunner(fn (AIOrchestratorRunData $run): mixed => match ($run->capabilityKey) {
        'suggest-title' => ['Title A', 'Title B'],
        'generate-content' => 'Generated body',
        'suggest-meta-description' => ['Meta A'],
        default => null,
    });

    $result = GenerateAiAssistantFieldsAction::make()->handle(new AiAssistantGenerationData(
        fields: ['title', 'content', 'meta'],
        keywords: 'launch, pricing',
    ));

    expect($result)->toBeInstanceOf(AiAssistantGenerationResultData::class)
        ->and($result->generated)->toBe([
            'title' => ['Title A', 'Title B'],
            'content' => 'Generated body',
            'meta' => ['Meta A'],
        ])
        ->and($result->failures)->toBe([]);
});

it('maps per-field options for title and content', function (): void {
    $capturedContext = [];

    fakeCapabilityRunner(function (AIOrchestratorRunData $run) use (&$capturedContext): mixed {
        $capturedContext[$run->capabilityKey] = $run->context;

        return 'output';
    });

    GenerateAiAssistantFieldsAction::run(new AiAssistantGenerationData(
        fields: ['title', 'content'],
        content: 'Existing body',
        currentTitle: 'Old Title',
        keywords: 'kw',
        siteId: 42,
        languageId: 2,
        titleIncludeCurrent: true,
        contentRefactor: true,
        contentTargetLength: 500,
    ));

    expect($capturedContext['suggest-title']['options'])
        ->toBe(['site_id' => 42, 'current_title' => 'Old Title'])
        ->and($capturedContext['generate-content']['options'])
        ->toBe([
            'site_id' => 42,
            'current_title' => 'Old Title',
            'target_length' => 500,
            'refactor' => true,
        ])
        ->and($capturedContext['generate-content']['languageId'])->toBe(2)
        ->and($capturedContext['generate-content']['content'])->toBe('Existing body');
});

it('omits the current title option when titleIncludeCurrent is false', function (): void {
    $capturedContext = [];

    fakeCapabilityRunner(function (AIOrchestratorRunData $run) use (&$capturedContext): mixed {
        $capturedContext[$run->capabilityKey] = $run->context;

        return 'output';
    });

    GenerateAiAssistantFieldsAction::run(new AiAssistantGenerationData(
        fields: ['title'],
        currentTitle: 'Old Title',
        titleIncludeCurrent: false,
    ));

    expect($capturedContext['suggest-title']['options'])->toBe([]);
});

it('records a failure and continues instead of aborting when a capability throws', function (): void {
    fakeCapabilityRunner(function (AIOrchestratorRunData $run): mixed {
        if ($run->capabilityKey === 'generate-content') {
            throw new RuntimeException('content generation failed');
        }

        return ['Title A'];
    });

    $result = GenerateAiAssistantFieldsAction::make()->handle(new AiAssistantGenerationData(
        fields: ['title', 'content'],
    ));

    expect($result->generated)->toBe(['title' => ['Title A']])
        ->and($result->failures)->toBe(['content' => 'content generation failed']);
});

it('skips fields that are not real capabilities', function (): void {
    fakeCapabilityRunner(fn (AIOrchestratorRunData $run): mixed => ['Title A']);

    $result = GenerateAiAssistantFieldsAction::make()->handle(new AiAssistantGenerationData(
        fields: ['title', 'not-a-field'],
    ));

    expect($result->generated)->toBe(['title' => ['Title A']])
        ->and($result->failures)->toBe([]);
});
