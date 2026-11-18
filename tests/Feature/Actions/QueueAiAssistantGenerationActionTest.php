<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\PruneAiGenerationPayloadsAction;
use Capell\AIOrchestrator\Actions\GenerateAiAssistantFieldsAction;
use Capell\AIOrchestrator\Actions\QueueAiAssistantGenerationAction;
use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Data\AiAssistantGenerationResultData;
use Capell\AIOrchestrator\Enums\AiGenerationRequestStatus;
use Capell\AIOrchestrator\Jobs\RunAiAssistantGenerationJob;
use Capell\AIOrchestrator\Models\AiGenerationRequest;
use Illuminate\Support\Facades\DB;
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

    $requestId = $request->getKey();
    throw_unless(is_int($requestId), RuntimeException::class, 'Expected an integer generation request key.');
    (new RunAiAssistantGenerationJob($requestId))->handle();
    $request->refresh();

    expect($request->status)->toBe(AiGenerationRequestStatus::Completed)
        ->and($request->generated)->toBe(['title' => ['Queued title']])
        ->and($request->completed_at)->not->toBeNull();
});

it('encrypts transient generation payloads and prunes expired requests', function (): void {
    $request = QueueAiAssistantGenerationAction::run(new AiAssistantGenerationData(
        fields: ['title'],
        content: 'Unpublished body with customer-secret',
        keywords: 'private-launch',
    ));

    $rawRequest = DB::table('ai_generation_requests')->where('id', $request->getKey())->first();
    throw_unless($rawRequest instanceof stdClass && is_string($rawRequest->payload ?? null), RuntimeException::class, 'Expected an encrypted request payload.');
    $requestId = $request->getKey();
    throw_unless(is_int($requestId), RuntimeException::class, 'Expected an integer generation request key.');

    expect($rawRequest)->not->toBeNull()
        ->and($rawRequest->payload)->not->toContain('customer-secret')
        ->not->toContain('private-launch')
        ->and($request->refresh()->payload['content'] ?? null)->toBe('Unpublished body with customer-secret')
        ->and(serialize(new RunAiAssistantGenerationJob($requestId)))
        ->not->toContain('customer-secret');

    $request->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(PruneAiGenerationPayloadsAction::run())->toBe(1)
        ->and(AiGenerationRequest::query()->whereKey($request->getKey())->exists())->toBeFalse();
});

function queuedGenerationRequestId(string $keywords): int
{
    $id = QueueAiAssistantGenerationAction::run(new AiAssistantGenerationData(fields: ['title'], keywords: $keywords))->getKey();
    throw_unless(is_int($id), RuntimeException::class, 'Expected an integer generation request key.');

    return $id;
}

function bindThrowingGenerationAction(int &$calls): void
{
    $counter = &$calls;
    app()->instance(GenerateAiAssistantFieldsAction::class, new class($counter) extends GenerateAiAssistantFieldsAction
    {
        public function __construct(private int &$counter) {}

        public function handle(AiAssistantGenerationData $data): AiAssistantGenerationResultData
        {
            $this->counter++;

            throw new RuntimeException('provider down');
        }
    });
}

it('does not re-run a failed provider result when the job is retried', function (): void {
    $calls = 0;
    bindThrowingGenerationAction($calls);
    $requestId = queuedGenerationRequestId('retry-guard');

    (new RunAiAssistantGenerationJob($requestId))->handle();
    (new RunAiAssistantGenerationJob($requestId))->handle();
    (new RunAiAssistantGenerationJob($requestId))->handle();

    $request = AiGenerationRequest::query()->findOrFail($requestId);

    expect($calls)->toBe(1)
        ->and($request->status)->toBe(AiGenerationRequestStatus::Failed)
        ->and($request->error_message)->toBe('provider down');
});

it('does not claim a request that another worker is still running', function (): void {
    $calls = 0;
    bindThrowingGenerationAction($calls);
    $requestId = queuedGenerationRequestId('still-running');
    AiGenerationRequest::query()->whereKey($requestId)->update(['status' => AiGenerationRequestStatus::Running]);

    (new RunAiAssistantGenerationJob($requestId))->handle();

    expect($calls)->toBe(0);
});

it('reclaims a running request whose worker died', function (): void {
    $calls = 0;
    bindThrowingGenerationAction($calls);
    $requestId = queuedGenerationRequestId('dead-worker');
    AiGenerationRequest::query()->whereKey($requestId)->toBase()->update([
        'status' => AiGenerationRequestStatus::Running->value,
        'updated_at' => now()->subMinutes(10),
    ]);

    (new RunAiAssistantGenerationJob($requestId))->handle();

    expect($calls)->toBe(1);
});

it('marks a running request failed once when the queue reports the job failed', function (): void {
    $requestId = queuedGenerationRequestId('killed-worker');
    AiGenerationRequest::query()->whereKey($requestId)->update(['status' => AiGenerationRequestStatus::Running]);
    $job = new RunAiAssistantGenerationJob($requestId);

    $job->failed(new RuntimeException('worker killed'));
    $job->failed(new RuntimeException('second report'));

    $request = AiGenerationRequest::query()->findOrFail($requestId);

    expect($request->status)->toBe(AiGenerationRequestStatus::Failed)
        ->and($request->error_message)->toBe('worker killed')
        ->and($job->backoff())->toBe([10, 60]);
});
