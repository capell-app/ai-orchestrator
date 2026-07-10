<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\AssertAiModelPriceConfiguredAction;
use Capell\AIOrchestrator\Actions\Ai\EstimateAiGenerationCostAction;

it('estimates token and flat image costs from configured pricing', function (): void {
    $textCost = EstimateAiGenerationCostAction::run(
        model: 'gpt-4o',
        promptTokens: 1_000_000,
        completionTokens: 1_000_000,
    );

    $imageCost = EstimateAiGenerationCostAction::run(model: 'dall-e-3');

    expect($textCost)->toBe([
        'cost_micros' => 20_000_000,
        'currency' => 'USD',
    ])->and($imageCost)->toBe([
        'cost_micros' => 40_000,
        'currency' => 'USD',
    ]);
});

it('honours explicit provider cost metadata', function (): void {
    $cost = EstimateAiGenerationCostAction::run(
        model: 'unknown-provider-model',
        metadata: ['cost_micros' => 1234, 'cost_currency' => 'GBP'],
    );

    expect($cost)->toBe([
        'cost_micros' => 1234,
        'currency' => 'GBP',
    ]);
});

it('rejects dispatch for models without an explicit price map entry', function (): void {
    config()->set('capell-ai-orchestrator.ai_costs.models', [
        'known-model' => ['flat_cost_micros' => 0],
    ]);

    expect(fn (): bool => AssertAiModelPriceConfiguredAction::run('missing-model'))
        ->toThrow(RuntimeException::class, 'missing-model')
        ->and(AssertAiModelPriceConfiguredAction::run('KNOWN-MODEL'))->toBeTrue();
});
