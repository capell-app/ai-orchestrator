<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Integrations\LayoutBuilder;

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\Core\LayoutBuilder\Actions\PreviewLayoutPlanAction;
use Capell\Core\LayoutBuilder\Data\LayoutPlanResultData;
use Lorisleiva\Actions\Concerns\AsObject;

class PreviewLayoutBuilderLayoutPlanAction
{
    use AsObject;

    public function handle(AIOrchestratorRunData $run): LayoutPlanResultData
    {
        return PreviewLayoutPlanAction::run($run->prompt, $run->context);
    }
}
