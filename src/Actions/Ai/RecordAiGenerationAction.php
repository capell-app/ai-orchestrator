<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Data\Ai\AiGenerationResultData;
use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Support\Ai\AiSpendGuard;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static AIGenerationHistory run(AiGenerationResultData|array<string, mixed> $result)
 */
class RecordAiGenerationAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly ?AiSpendGuard $spendGuard = null) {}

    /**
     * @param  AiGenerationResultData|array<string, mixed>  $result
     */
    public function handle(AiGenerationResultData|array $result): AIGenerationHistory
    {
        if (is_array($result)) {
            $history = AIGenerationHistory::query()->create($this->withCostAndActor($result));
            $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
            $this->spendGuard?->release($this->stringOrNull($metadata['spend_reservation_id'] ?? null));

            return $history;
        }

        $responseMetadata = $result->response->metadata ?? [];
        $metadata = $this->stringKeyedArray(array_merge($responseMetadata, $result->metadata));

        if ($result->messages !== null) {
            $metadata['ai_messages'] = $result->messages;
        }

        if ($result->params !== null) {
            $metadata['ai_params'] = $result->params;
        }

        if ($result->aiCreatorSessionId !== null) {
            $metadata['ai_creator_session_id'] = $result->aiCreatorSessionId;
        }

        $history = AIGenerationHistory::query()->create($this->withCostAndActor([
            'site_id' => $result->siteId ?? $this->integerOrNull($metadata['site_id'] ?? null),
            'action' => $result->actionKey,
            'model' => $result->response?->model,
            'input' => $result->inputText,
            'output' => $result->outputText,
            'prompt_tokens' => $this->integerValue($responseMetadata['prompt_tokens'] ?? 0),
            'completion_tokens' => $this->integerValue($responseMetadata['completion_tokens'] ?? 0),
            'total_tokens' => $result->response->tokensUsed ?? 0,
            'duration' => $result->response->duration ?? 0,
            'pageable_id' => $result->pageableId,
            'pageable_type' => $result->pageableType,
            'language_id' => $result->languageId,
            'metadata' => $metadata,
        ]));

        $this->spendGuard?->release($this->stringOrNull($metadata['spend_reservation_id'] ?? null));

        return $history;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withCostAndActor(array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];
        $metadata = is_array($metadata) ? $this->stringKeyedArray($metadata) : [];
        $promptTokens = $this->integerValue($payload['prompt_tokens'] ?? 0);
        $completionTokens = $this->integerValue($payload['completion_tokens'] ?? 0);
        $totalTokens = $this->integerValue($payload['total_tokens'] ?? 0);
        $cost = EstimateAiGenerationCostAction::run(
            model: is_string($payload['model'] ?? null) ? $payload['model'] : null,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            metadata: $metadata,
        );

        $payload['cost_micros'] ??= $cost['cost_micros'];
        $payload['cost_currency'] ??= $cost['currency'];
        $payload['created_by_user_id'] ??= $this->createdByUserId($metadata);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createdByUserId(array $metadata): ?int
    {
        $candidate = $metadata['ai_creator_user_id'] ?? $metadata['user_id'] ?? Auth::id();

        if ($candidate === null || $candidate === '') {
            return null;
        }

        return is_numeric($candidate) ? (int) $candidate : null;
    }

    private function integerValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
