<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Exceptions\AiSpendBudgetExceededException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/** @method static bool run(string $model, int $budgetCents, int $promptTokens = 0, int $completionTokens = 0) */
final class AssertAiRequestBudgetAction
{
    use AsAction;

    private const int MICROS_PER_CENT = 10_000;

    public function handle(
        string $model,
        int $budgetCents,
        int $promptTokens = 0,
        int $completionTokens = 0,
    ): bool {
        if ($budgetCents < 0) {
            throw new InvalidArgumentException('An AI request budget cannot be negative.');
        }

        AssertAiModelPriceConfiguredAction::run($model);

        $estimatedCost = EstimateAiGenerationCostAction::run(
            model: $model,
            promptTokens: max(0, $promptTokens),
            completionTokens: max(0, $completionTokens),
        )['cost_micros'];
        $budgetMicros = $budgetCents * self::MICROS_PER_CENT;

        if ($estimatedCost > $budgetMicros) {
            throw new AiSpendBudgetExceededException(sprintf(
                'AI model [%s] estimate [%d micros] exceeds the request budget [%d micros].',
                $model,
                $estimatedCost,
                $budgetMicros,
            ));
        }

        return true;
    }
}
