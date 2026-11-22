<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\GenerateAiAssistantFieldsAction;
use Capell\AIOrchestrator\Actions\QueueAiAssistantGenerationAction;
use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Data\AiAssistantGenerationResultData;
use Capell\AIOrchestrator\Enums\AiGenerationRequestStatus;
use Capell\AIOrchestrator\Jobs\RunAiAssistantGenerationJob;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\Core\Models\CapellExtension;
use Illuminate\Support\Facades\Queue;

/*
 * Disabling AI Orchestrator in another process changes the durable lifecycle row, but a warm
 * queue worker keeps the enabled state it cached. A generation job already on the queue must
 * make no provider call (each one is billed) and leave its request queued.
 */

function setAiOrchestratorStatusInAnotherProcess(string $status): void
{
    CapellExtension::query()
        ->where('composer_name', AIOrchestratorServiceProvider::$packageName)
        ->toBase()
        ->update(['status' => $status]);
}

afterEach(function (): void {
    app()->forgetInstance(GenerateAiAssistantFieldsAction::class);
});

it('makes no provider call for a queued generation once AI Orchestrator is disabled elsewhere, and runs it after re-enable', function (): void {
    Queue::fake();
    $provider = new class extends GenerateAiAssistantFieldsAction
    {
        public int $calls = 0;

        public function handle(AiAssistantGenerationData $data): AiAssistantGenerationResultData
        {
            $this->calls++;

            return new AiAssistantGenerationResultData(generated: ['title' => ['Queued title']], failures: []);
        }
    };
    app()->instance(GenerateAiAssistantFieldsAction::class, $provider);
    $request = QueueAiAssistantGenerationAction::run(new AiAssistantGenerationData(fields: ['title'], keywords: 'launch'));
    $requestId = $request->getKey();
    throw_unless(is_int($requestId), RuntimeException::class, 'Expected an integer generation request key.');

    setAiOrchestratorStatusInAnotherProcess('disabled');
    (new RunAiAssistantGenerationJob($requestId))->handle();

    expect($provider->calls)->toBe(0)
        ->and($request->refresh()->status)->toBe(AiGenerationRequestStatus::Queued);

    setAiOrchestratorStatusInAnotherProcess('enabled');
    (new RunAiAssistantGenerationJob($requestId))->handle();

    expect($provider->calls)->toBe(1)
        ->and($request->refresh()->status)->toBe(AiGenerationRequestStatus::Completed);
});
