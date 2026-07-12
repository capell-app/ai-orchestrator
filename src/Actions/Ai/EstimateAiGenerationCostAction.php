<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static array{cost_micros: int, currency: string} run(?string $model, int $promptTokens = 0, int $completionTokens = 0, int $totalTokens = 0, array<string, mixed> $metadata = [])
 */
final class EstimateAiGenerationCostAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{cost_micros: int, currency: string}
     */
    public function handle(?string $model, int $promptTokens = 0, int $completionTokens = 0, int $totalTokens = 0, array $metadata = []): array
    {
        $currency = $this->currency($metadata);
        $explicitCost = $this->explicitCostMicros($metadata);

        if ($explicitCost !== null) {
            return ['cost_micros' => $explicitCost, 'currency' => $currency];
        }

        $price = $this->priceForModel($model);

        if ($price === []) {
            return ['cost_micros' => 0, 'currency' => $currency];
        }

        $flatCost = $this->positiveInt($price['flat_cost_micros'] ?? null);

        if ($flatCost !== null && $promptTokens === 0 && $completionTokens === 0 && $totalTokens === 0) {
            return ['cost_micros' => $flatCost, 'currency' => $currency];
        }

        $promptCost = $promptTokens * ($this->positiveInt($price['prompt_micros_per_million_tokens'] ?? null) ?? 0);
        $completionCost = $completionTokens * ($this->positiveInt($price['completion_micros_per_million_tokens'] ?? null) ?? 0);

        return [
            'cost_micros' => (int) round(($promptCost + $completionCost) / 1_000_000),
            'currency' => $currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function currency(array $metadata): string
    {
        $metadataCurrency = $metadata['cost_currency'] ?? null;

        if (is_string($metadataCurrency) && preg_match('/^[A-Z]{3}$/', $metadataCurrency) === 1) {
            return $metadataCurrency;
        }

        $configuredCurrency = config('capell-ai-orchestrator.ai_costs.currency', 'USD');

        return is_string($configuredCurrency) && preg_match('/^[A-Z]{3}$/', $configuredCurrency) === 1
            ? $configuredCurrency
            : 'USD';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function explicitCostMicros(array $metadata): ?int
    {
        return $this->positiveInt($metadata['cost_micros'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function priceForModel(?string $model): array
    {
        if ($model === null || trim($model) === '') {
            return [];
        }

        $prices = config('capell-ai-orchestrator.ai_costs.models', []);

        if (! is_array($prices)) {
            return [];
        }

        $key = strtolower(trim($model));
        $price = $prices[$key] ?? null;

        return is_array($price) ? $price : [];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
