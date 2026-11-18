<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Integrations\Authoring\Capabilities;

use Capell\AIOrchestrator\Actions\Ai\RewriteContentAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AiRewriteMode;
use Capell\AIOrchestrator\Support\Ai\Context\ContentActionContext;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Rewrites each supplied field on its own, so an editor can review and apply the results per field.
 * A field whose blueprint options set ai to false is never sent to the provider.
 */
class RewriteContentCapabilityAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<string, string> Rewritten text keyed by field.
     */
    public function handle(AIOrchestratorRunData $run): array
    {
        $context = $run->context;
        $mode = AiRewriteMode::tryFrom(is_string($context['mode'] ?? null) ? $context['mode'] : '') ?? AiRewriteMode::Fix;
        $instruction = is_string($context['instruction'] ?? null) ? $context['instruction'] : '';
        $options = is_array($context['options'] ?? null) ? $context['options'] : [];
        $fields = is_array($context['fields'] ?? null) ? $context['fields'] : [];
        $languageId = is_numeric($context['languageId'] ?? null) ? (int) $context['languageId'] : 0;
        $results = [];

        foreach ($fields as $key => $field) {
            if (! is_string($key) || ! is_array($field)) {
                continue;
            }

            $fieldOptions = is_array($field['options'] ?? null) ? $field['options'] : [];

            if (($fieldOptions['ai'] ?? true) === false) {
                continue;
            }

            $text = is_string($field['content'] ?? null) ? $field['content'] : '';

            if (trim($text) === '') {
                continue;
            }

            $results[$key] = RewriteContentAction::run(
                new ContentActionContext(content: $text, keywords: '', pageId: null, pageType: null, languageId: $languageId),
                $mode,
                $instruction,
                $options,
            );
        }

        return $results;
    }
}
