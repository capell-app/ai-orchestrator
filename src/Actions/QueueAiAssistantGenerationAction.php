<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Enums\AiGenerationRequestStatus;
use Capell\AIOrchestrator\Jobs\RunAiAssistantGenerationJob;
use Capell\AIOrchestrator\Models\AiGenerationRequest;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/** @method static AiGenerationRequest run(AiAssistantGenerationData $data) */
final class QueueAiAssistantGenerationAction
{
    use AsFake;
    use AsObject;

    public function handle(AiAssistantGenerationData $data): AiGenerationRequest
    {
        $payload = $this->payload($data);
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        AiGenerationRequest::query()
            ->where('fingerprint', $fingerprint)
            ->where('expires_at', '<=', now())
            ->delete();

        $request = AiGenerationRequest::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'status' => AiGenerationRequestStatus::Queued,
                'site_id' => $data->siteId,
                'payload' => $payload,
                'expires_at' => now()->addDay(),
            ],
        );

        $shouldDispatch = $request->wasRecentlyCreated;

        if ($request->status === AiGenerationRequestStatus::Failed) {
            $request->update([
                'status' => AiGenerationRequestStatus::Queued,
                'error_message' => null,
                'expires_at' => now()->addDay(),
            ]);
            $shouldDispatch = true;
        }

        if (in_array($request->status, [AiGenerationRequestStatus::Queued, AiGenerationRequestStatus::Running], true)
            && $request->updated_at?->lte(now()->subMinutes(5)) === true) {
            $request->update([
                'status' => AiGenerationRequestStatus::Queued,
                'error_message' => null,
            ]);
            $shouldDispatch = true;
        }

        if ($shouldDispatch) {
            $this->assertAsynchronousProductionQueue();
            $requestKey = $request->getKey();
            $job = new RunAiAssistantGenerationJob(is_int($requestKey) ? $requestKey : 0);
            $connection = config('capell-ai-orchestrator.queue.connection');

            if (is_string($connection) && $connection !== '') {
                $job->onConnection($connection);
            }

            $queue = config('capell-ai-orchestrator.queue.name', 'ai');

            if (is_string($queue) && $queue !== '') {
                $job->onQueue($queue);
            }

            dispatch($job);
            $request->refresh();
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AiAssistantGenerationData $data): array
    {
        $actor = $data->actor;
        $actorKey = $actor instanceof Model ? $actor->getKey() : null;

        return [
            'fields' => array_values($data->fields),
            'content' => $data->content,
            'current_title' => $data->currentTitle,
            'keywords' => $data->keywords,
            'page_id' => $data->pageId,
            'page_type' => $data->pageType,
            'site_id' => $data->siteId,
            'language_id' => $data->languageId,
            'title_include_current' => $data->titleIncludeCurrent,
            'content_refactor' => $data->contentRefactor,
            'content_target_length' => $data->contentTargetLength,
            'meta_include_current' => $data->metaIncludeCurrent,
            'actor_type' => $actor instanceof Model ? $actor::class : null,
            'actor_id' => is_int($actorKey) || is_string($actorKey) ? $actorKey : null,
        ];
    }

    private function assertAsynchronousProductionQueue(): void
    {
        $connection = config('capell-ai-orchestrator.queue.connection') ?? config('queue.default');

        if ($connection === 'sync' && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('AI generation requires an asynchronous queue connection outside local and testing environments.');
        }
    }
}
