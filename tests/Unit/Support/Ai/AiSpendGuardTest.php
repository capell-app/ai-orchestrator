<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Exceptions\AiSpendBudgetExceededException;
use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Support\Ai\AiSpendGuard;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    config()->set('capell-ai-orchestrator.ai_costs.models', [
        'test-model' => ['flat_cost_micros' => 600],
    ]);
});

function testAiSpendGuard(): AiSpendGuard
{
    return new AiSpendGuard('array', [
        'enabled' => true,
        'monthly_limit_micros' => 1_000,
        'per_request_limit_micros' => 700,
        'reservation_ttl_seconds' => 3_600,
    ]);
}

it('reserves estimated spend atomically per site', function (): void {
    $guard = testAiSpendGuard();

    $first = $guard->reserve(10, 'test-model', 0, 0, 'request-one');
    $sameRequest = $guard->reserve(10, 'test-model', 0, 0, 'request-one');
    $otherSite = $guard->reserve(20, 'test-model', 0, 0, 'request-two');

    expect($first)->not->toBeNull()
        ->and($sameRequest?->id)->toBe($first?->id)
        ->and($otherSite)->not->toBeNull()
        ->and(fn (): mixed => $guard->reserve(10, 'test-model', 0, 0, 'request-three'))
        ->toThrow(AiSpendBudgetExceededException::class);
});

it('counts recorded monthly spend and releases abandoned reservations', function (): void {
    $guard = testAiSpendGuard();

    AIGenerationHistory::query()->create([
        'action' => 'existing-generation',
        'model' => 'test-model',
        'cost_micros' => 300,
        'site_id' => 10,
    ]);

    $reservation = $guard->reserve(10, 'test-model', 0, 0, 'request-one');

    expect(fn (): mixed => $guard->reserve(10, 'test-model', 0, 0, 'request-two'))
        ->toThrow(AiSpendBudgetExceededException::class);

    $guard->release($reservation?->id);

    expect($guard->reserve(10, 'test-model', 0, 0, 'request-two'))->not->toBeNull();
});

it('rejects a single request above its configured ceiling', function (): void {
    config()->set('capell-ai-orchestrator.ai_costs.models.test-model.flat_cost_micros', 701);

    expect(fn (): mixed => testAiSpendGuard()->reserve(10, 'test-model', 0, 0, 'request-one'))
        ->toThrow(AiSpendBudgetExceededException::class, 'per-request');
});
