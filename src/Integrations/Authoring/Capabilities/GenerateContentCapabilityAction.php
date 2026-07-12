<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Integrations\Authoring\Capabilities;

use Capell\AIOrchestrator\Actions\Ai\GeneratorPageContentAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Support\Ai\Context\ContentActionContext;
use Lorisleiva\Actions\Concerns\AsObject;

class GenerateContentCapabilityAction
{
    use AsObject;

    public function __construct(private readonly GeneratorPageContentAction $generatorPageContentAction) {}

    public function handle(AIOrchestratorRunData $run): string
    {
        $context = $this->contextFromRun($run);
        $options = $this->optionsFromRun($run);

        return $this->generatorPageContentAction->handle($context, $options);
    }

    /**
     * @return array{user_id?:int|null,site_id?:int|null,current_title?:string|null,target_length?:int|null,refactor?:bool|null}
     */
    private function optionsFromRun(AIOrchestratorRunData $run): array
    {
        $rawOptions = is_array($run->context['options'] ?? null) ? $run->context['options'] : [];

        $options = [];

        if (array_key_exists('user_id', $rawOptions)) {
            $userId = $rawOptions['user_id'];
            $options['user_id'] = is_scalar($userId) ? (int) $userId : null;
        }

        if (array_key_exists('site_id', $rawOptions)) {
            $siteId = $rawOptions['site_id'];
            $options['site_id'] = is_scalar($siteId) ? (int) $siteId : null;
        }

        if (array_key_exists('current_title', $rawOptions)) {
            $currentTitle = $rawOptions['current_title'];
            $options['current_title'] = is_scalar($currentTitle) ? (string) $currentTitle : null;
        }

        if (array_key_exists('target_length', $rawOptions)) {
            $targetLength = $rawOptions['target_length'];
            $options['target_length'] = is_scalar($targetLength) ? (int) $targetLength : null;
        }

        if (array_key_exists('refactor', $rawOptions)) {
            $options['refactor'] = (bool) $rawOptions['refactor'];
        }

        return $options;
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
