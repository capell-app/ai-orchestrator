<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Models\AiGenerationRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/** @method static int run(?int $retentionDays = null) */
final class PruneAiGenerationPayloadsAction
{
    use AsAction;

    public function handle(?int $retentionDays = null): int
    {
        $days = max(1, $retentionDays ?? $this->retentionDays());

        $expiredRequests = AiGenerationRequest::query()
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->delete();

        $prunedHistories = AIGenerationHistory::query()
            ->where('created_at', '<', CarbonImmutable::now()->subDays($days))
            ->where(static function (Builder $query): void {
                $query->whereNotNull('input')
                    ->orWhereNotNull('output')
                    ->orWhereNotNull('error_message')
                    ->orWhereNotNull('metadata');
            })
            ->update([
                'input' => null,
                'output' => null,
                'error_message' => null,
                'metadata' => null,
                'updated_at' => now(),
            ]);

        return $expiredRequests + $prunedHistories;
    }

    private function retentionDays(): int
    {
        $value = config('capell-ai-orchestrator.retention.generation_payload_days', 30);

        return is_numeric($value) ? (int) $value : 30;
    }
}
