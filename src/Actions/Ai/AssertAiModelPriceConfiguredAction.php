<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/** @method static bool run(string $model) */
final class AssertAiModelPriceConfiguredAction
{
    use AsAction;

    public function handle(string $model): bool
    {
        $normalizedModel = strtolower(trim($model));
        $prices = config('capell-ai-orchestrator.ai_costs.models', []);

        if ($normalizedModel === ''
            || ! is_array($prices)
            || ! array_key_exists($normalizedModel, $prices)
            || ! is_array($prices[$normalizedModel])) {
            throw new RuntimeException("AI model [{$model}] has no configured price map entry.");
        }

        return true;
    }
}
