<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\PruneAiGenerationPayloadsAction;
use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;

it('clears generation text older than the retention days saved in the settings', function (): void {
    $history = new AIGenerationHistory;
    $history->forceFill(['action' => 'SuggestPageTitlesAction', 'input' => 'private input', 'output' => 'private output', 'prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2, 'duration' => 0.1, 'failed' => false])->save();
    AIGenerationHistory::query()->whereKey($history->getKey())->toBase()->update(['created_at' => now()->subDays(4)]);

    // The 30 day default keeps a four day old row.
    PruneAiGenerationPayloadsAction::run();
    expect(AIGenerationHistory::query()->whereKey($history->getKey())->value('input'))->toBe('private input');

    $settings = Mockery::mock(AIOrchestratorSettings::class)->makePartial();
    $settings->prompts = ['retention_generation_payload_days' => 3];
    app()->instance(AIOrchestratorSettings::class, $settings);

    PruneAiGenerationPayloadsAction::run();

    expect(AIGenerationHistory::query()->whereKey($history->getKey())->value('input'))->toBeNull();
});
