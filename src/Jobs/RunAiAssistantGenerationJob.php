<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Jobs;

use Capell\AIOrchestrator\Actions\GenerateAiAssistantFieldsAction;
use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Enums\AiGenerationRequestStatus;
use Capell\AIOrchestrator\Models\AiGenerationRequest;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class RunAiAssistantGenerationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $requestId) {}

    public function uniqueId(): string
    {
        return (string) $this->requestId;
    }

    public function handle(): void
    {
        $claimed = AiGenerationRequest::query()
            ->whereKey($this->requestId)
            ->whereIn('status', [
                AiGenerationRequestStatus::Queued,
                AiGenerationRequestStatus::Running,
                AiGenerationRequestStatus::Failed,
            ])
            ->update([
                'status' => AiGenerationRequestStatus::Running,
                'error_message' => null,
            ]);

        if ($claimed !== 1) {
            return;
        }

        $request = AiGenerationRequest::query()->find($this->requestId);

        if (! $request instanceof AiGenerationRequest) {
            return;
        }

        try {
            $data = $this->generationData($request->payload);
            $result = app(GenerateAiAssistantFieldsAction::class)->handle($data);

            $request->update([
                'status' => AiGenerationRequestStatus::Completed,
                'generated' => $result->generated,
                'failures' => $result->failures,
                'completed_at' => now(),
                'expires_at' => now()->addDay(),
            ]);

            $this->notify($data->actor, 'ai_assistant_generation_completed', true);
        } catch (Throwable $exception) {
            $request->update([
                'status' => AiGenerationRequestStatus::Failed,
                'error_message' => mb_substr($exception->getMessage(), 0, 2_000),
            ]);
            $this->notify($this->actor($request->payload), 'ai_assistant_generation_failed', false);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generationData(array $payload): AiAssistantGenerationData
    {
        return new AiAssistantGenerationData(
            fields: $this->stringList($payload['fields'] ?? null),
            content: $this->stringValue($payload['content'] ?? null),
            currentTitle: $this->stringValue($payload['current_title'] ?? null),
            keywords: $this->stringValue($payload['keywords'] ?? null),
            pageId: $this->intOrStringOrNull($payload['page_id'] ?? null),
            pageType: $this->stringOrNull($payload['page_type'] ?? null),
            siteId: $this->positiveIntOrNull($payload['site_id'] ?? null),
            languageId: $this->integerValue($payload['language_id'] ?? null),
            titleIncludeCurrent: ($payload['title_include_current'] ?? false) === true,
            contentRefactor: ($payload['content_refactor'] ?? false) === true,
            contentTargetLength: $this->positiveIntOrNull($payload['content_target_length'] ?? null),
            metaIncludeCurrent: ($payload['meta_include_current'] ?? false) === true,
            actor: $this->actor($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function actor(array $payload): ?Authenticatable
    {
        $actorType = $payload['actor_type'] ?? null;
        $actorId = $this->intOrStringOrNull($payload['actor_id'] ?? null);

        if (! is_string($actorType) || $actorId === null || ! is_subclass_of($actorType, Model::class)) {
            return null;
        }

        $actor = $actorType::query()->find($actorId);

        return $actor instanceof Authenticatable ? $actor : null;
    }

    private function notify(?Authenticatable $actor, string $translationKey, bool $successful): void
    {
        if (! $actor instanceof Model) {
            return;
        }

        $notification = Notification::make("capell_{$translationKey}")
            ->title(__("capell-ai-orchestrator::package.{$translationKey}"));

        $successful ? $notification->success() : $notification->danger();
        $notification->sendToDatabase($actor);
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, 'is_string'))
            : [];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intOrStringOrNull(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) ? $value : null;
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function integerValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
