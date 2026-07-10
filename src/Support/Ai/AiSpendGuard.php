<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Actions\Ai\EstimateAiGenerationCostAction;
use Capell\AIOrchestrator\Data\Ai\AiSpendReservationData;
use Capell\AIOrchestrator\Exceptions\AiSpendBudgetExceededException;
use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class AiSpendGuard
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $driver,
        private readonly array $config,
    ) {}

    public function reserve(
        ?int $siteId,
        string $model,
        int $promptTokens,
        int $completionTokens,
        string $idempotencyKey,
    ): ?AiSpendReservationData {
        if (! $this->enabled()) {
            return null;
        }

        $estimatedCost = EstimateAiGenerationCostAction::run(
            model: $model,
            promptTokens: max(0, $promptTokens),
            completionTokens: max(0, $completionTokens),
        )['cost_micros'];

        if ($estimatedCost > $this->perRequestLimit()) {
            throw new AiSpendBudgetExceededException(sprintf(
                'AI request estimate [%d micros] exceeds the per-request limit [%d micros].',
                $estimatedCost,
                $this->perRequestLimit(),
            ));
        }

        $reservationId = hash('sha256', implode('|', [
            $siteId === null ? 'global' : (string) $siteId,
            strtolower($model),
            $idempotencyKey,
        ]));
        $repository = Cache::driver($this->driver);
        $bucketKey = $this->bucketKey($siteId);

        return $repository->withoutOverlapping(
            "{$bucketKey}:lock",
            fn (): AiSpendReservationData => $this->reserveLocked(
                $repository,
                $reservationId,
                $bucketKey,
                $siteId,
                $model,
                $estimatedCost,
            ),
            lockFor: 10,
            waitFor: 5,
        );
    }

    public function release(?string $reservationId): void
    {
        if ($reservationId === null || $reservationId === '') {
            return;
        }

        $repository = Cache::driver($this->driver);
        $reservationKey = $this->reservationKey($reservationId);
        $reservation = $repository->get($reservationKey);

        if (! is_array($reservation)) {
            return;
        }

        $bucketKey = $reservation['bucket_key'] ?? null;
        $amount = $reservation['amount'] ?? null;

        if (! is_string($bucketKey) || ! is_numeric($amount)) {
            $repository->forget($reservationKey);

            return;
        }

        $repository->withoutOverlapping(
            "{$bucketKey}:lock",
            function () use ($repository, $reservationKey, $bucketKey, $amount): void {
                $reserved = $repository->get($bucketKey, 0);
                $reservedAmount = is_numeric($reserved) ? (int) $reserved : 0;

                $repository->put(
                    $bucketKey,
                    max(0, $reservedAmount - (int) $amount),
                    $this->reservationTtl(),
                );
                $repository->forget($reservationKey);
            },
            lockFor: 10,
            waitFor: 5,
        );
    }

    private function reserveLocked(
        Repository $repository,
        string $reservationId,
        string $bucketKey,
        ?int $siteId,
        string $model,
        int $estimatedCost,
    ): AiSpendReservationData {
        $reservationKey = $this->reservationKey($reservationId);
        $existing = $repository->get($reservationKey);

        if (is_array($existing) && is_numeric($existing['amount'] ?? null)) {
            return new AiSpendReservationData(
                id: $reservationId,
                siteId: $siteId,
                model: $model,
                estimatedCostMicros: (int) $existing['amount'],
            );
        }

        $reservedValue = $repository->get($bucketKey, 0);
        $reserved = is_numeric($reservedValue) ? (int) $reservedValue : 0;
        $spent = $this->recordedSpend($siteId);

        if (($spent + $reserved + $estimatedCost) > $this->monthlyLimit()) {
            throw new AiSpendBudgetExceededException(sprintf(
                'AI site monthly spend limit [%d micros] would be exceeded.',
                $this->monthlyLimit(),
            ));
        }

        $repository->put($bucketKey, $reserved + $estimatedCost, $this->reservationTtl());
        $repository->put($reservationKey, [
            'bucket_key' => $bucketKey,
            'amount' => $estimatedCost,
        ], $this->reservationTtl());

        return new AiSpendReservationData(
            id: $reservationId,
            siteId: $siteId,
            model: $model,
            estimatedCostMicros: $estimatedCost,
        );
    }

    private function recordedSpend(?int $siteId): int
    {
        if (! Schema::hasTable('ai_generation_histories') || ! Schema::hasColumn('ai_generation_histories', 'site_id')) {
            return 0;
        }

        $cost = AIGenerationHistory::query()
            ->when(
                $siteId === null,
                fn (Builder $query): Builder => $query->whereNull('site_id'),
                fn (Builder $query): Builder => $query->where('site_id', $siteId),
            )
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_micros');

        return is_numeric($cost) ? (int) $cost : 0;
    }

    private function bucketKey(?int $siteId): string
    {
        $scope = $siteId === null ? 'global' : "site:{$siteId}";

        return 'ai-spend:' . $scope . ':' . now()->format('Y-m');
    }

    private function reservationKey(string $reservationId): string
    {
        return "ai-spend:reservation:{$reservationId}";
    }

    private function enabled(): bool
    {
        return ($this->config['enabled'] ?? true) === true;
    }

    private function monthlyLimit(): int
    {
        return $this->positiveInt('monthly_limit_micros', 50_000_000);
    }

    private function perRequestLimit(): int
    {
        return $this->positiveInt('per_request_limit_micros', 5_000_000);
    }

    private function reservationTtl(): int
    {
        return max(60, $this->positiveInt('reservation_ttl_seconds', 3_600));
    }

    private function positiveInt(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) ? max(0, (int) $value) : $default;
    }
}
