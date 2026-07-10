<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\GenerateAiAssistantFieldsAction;
use Capell\AIOrchestrator\Actions\QueueAiAssistantGenerationAction;
use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Data\AiAssistantGenerationResultData;
use Capell\AIOrchestrator\Enums\AiGenerationRequestStatus;
use Capell\AIOrchestrator\Jobs\RunAiAssistantGenerationJob;
use Capell\AIOrchestrator\Models\AiGenerationRequest;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

afterEach(function (): void {
    app()->forgetInstance(GenerateAiAssistantFieldsAction::class);
});

it('queues one durable request for identical assistant inputs', function (): void {
    $data = new AiAssistantGenerationData(
        fields: ['title'],
        content: 'Existing body',
        keywords: 'launch',
        siteId: 42,
    );

    $first = QueueAiAssistantGenerationAction::run($data);
    $duplicate = QueueAiAssistantGenerationAction::run($data);

    expect($first->getKey())->toBe($duplicate->getKey())
        ->and($first->status)->toBe(AiGenerationRequestStatus::Queued)
        ->and(AiGenerationRequest::query()->count())->toBe(1);

    Queue::assertPushed(RunAiAssistantGenerationJob::class, 1);
});

it('executes queued assistant generation and stores its result', function (): void {
    app()->instance(GenerateAiAssistantFieldsAction::class, new class extends GenerateAiAssistantFieldsAction
    {
        public function handle(AiAssistantGenerationData $data): AiAssistantGenerationResultData
        {
            return new AiAssistantGenerationResultData(
                generated: ['title' => ['Queued title']],
                failures: [],
            );
        }
    });

    $request = QueueAiAssistantGenerationAction::run(new AiAssistantGenerationData(
        fields: ['title'],
        keywords: 'launch',
    ));

    (new RunAiAssistantGenerationJob((int) $request->getKey()))->handle();
    $request->refresh();

    expect($request->status)->toBe(AiGenerationRequestStatus::Completed)
        ->and($request->generated)->toBe(['title' => ['Queued title']])
        ->and($request->completed_at)->not->toBeNull();
});
