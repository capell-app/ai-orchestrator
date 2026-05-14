<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Integrations\LayoutBuilder;

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

class PreviewLayoutBuilderLayoutPlanAction
{
    use AsObject;

    public function handle(AIOrchestratorRunData $run): mixed
    {
        $actionClass = $this->previewLayoutPlanActionClass();

        throw_if(! class_exists($actionClass) || ! method_exists($actionClass, 'run'), RuntimeException::class, 'LayoutBuilder preview planning is not available.');

        return $actionClass::run($run->prompt, $run->context);
    }

    private function previewLayoutPlanActionClass(): string
    {
        return implode('\\', ['Capell', 'LayoutBuilder', 'Actions', 'PreviewLayoutPlanAction']);
    }
}
