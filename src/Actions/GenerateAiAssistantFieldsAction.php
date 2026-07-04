<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AiAssistantGenerationData;
use Capell\AIOrchestrator\Data\AiAssistantGenerationResultData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Run the selected AI authoring capabilities once each and collect their raw
 * output. A failing capability is recorded and skipped so a single failure
 * never aborts the whole pass; the caller decides how to surface failures.
 */
class GenerateAiAssistantFieldsAction
{
    use AsObject;

    /**
     * The AI Orchestrator module that owns the authoring capabilities.
     */
    private const string MODULE_KEY = 'ai-authoring';

    /**
     * Map of short field keys to their AI Orchestrator capability keys.
     *
     * @var array<string, string>
     */
    private const array CAPABILITY_KEYS = [
        'title' => 'suggest-title',
        'content' => 'generate-content',
        'meta' => 'suggest-meta-description',
    ];

    public function handle(AiAssistantGenerationData $data): AiAssistantGenerationResultData
    {
        $generated = [];
        $failures = [];

        foreach ($data->fields as $field) {
            if (! isset(self::CAPABILITY_KEYS[$field])) {
                continue;
            }

            try {
                $generated[$field] = $this->runCapability($field, $data);
            } catch (Throwable $exception) {
                $failures[$field] = $exception->getMessage();
            }
        }

        return new AiAssistantGenerationResultData(
            generated: $generated,
            failures: $failures,
        );
    }

    private function runCapability(string $field, AiAssistantGenerationData $data): mixed
    {
        $context = [
            'content' => $data->content,
            'keywords' => $data->keywords,
            'pageId' => $data->pageId,
            'pageType' => $data->pageType,
            'languageId' => $data->languageId,
            'options' => $this->optionsForField($field, $data),
        ];

        return RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
            moduleKey: self::MODULE_KEY,
            capabilityKey: self::CAPABILITY_KEYS[$field],
            prompt: $data->keywords,
            context: $context,
            actor: $data->actor,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsForField(string $field, AiAssistantGenerationData $data): array
    {
        $userId = $data->actor?->getAuthIdentifier();
        $options = is_int($userId) ? ['user_id' => $userId] : [];

        return match ($field) {
            'title' => $data->titleIncludeCurrent && $data->currentTitle !== ''
                ? $options + ['current_title' => $data->currentTitle]
                : $options,
            'content' => $options + array_filter(
                [
                    'current_title' => $data->currentTitle !== '' ? $data->currentTitle : null,
                    'target_length' => $data->contentTargetLength,
                    'refactor' => $data->contentRefactor,
                ],
                static fn (mixed $value): bool => $value !== null,
            ),
            default => $options,
        };
    }
}
