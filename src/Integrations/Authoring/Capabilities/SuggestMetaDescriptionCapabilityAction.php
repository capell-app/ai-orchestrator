<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Integrations\Authoring\Capabilities;

use Capell\AIOrchestrator\Actions\Ai\SuggestMetaDescriptionsAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Support\Ai\Context\ContentActionContext;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class SuggestMetaDescriptionCapabilityAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<int, string>
     */
    public function handle(AIOrchestratorRunData $run): array
    {
        $context = $this->contextFromRun($run);
        $options = is_array($run->context['options'] ?? null) ? $run->context['options'] : [];

        return SuggestMetaDescriptionsAction::run($context, $options);
    }

    private function contextFromRun(AIOrchestratorRunData $run): ContentActionContext
    {
        $context = $run->context;
        $pageId = $context['pageId'] ?? null;
        $pageType = $context['pageType'] ?? null;

        return new ContentActionContext(
            content: $this->stringValue($context['content'] ?? null),
            keywords: $this->stringValue($context['keywords'] ?? null),
            pageId: is_scalar($pageId) ? (int) $pageId : null,
            pageType: is_scalar($pageType) ? (string) $pageType : null,
            languageId: $this->intValue($context['languageId'] ?? null),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }
}
