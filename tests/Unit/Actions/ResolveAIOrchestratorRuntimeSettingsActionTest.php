<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\ResolveAIOrchestratorRuntimeSettingsAction;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;

it('uses persisted provider model prompts and rate limits as one runtime source', function (): void {
    $settings = Mockery::mock(AIOrchestratorSettings::class)->makePartial();
    $settings->ai_provider = 'anthropic';
    $settings->ai_model = 'fallback-model';
    $settings->ai_api_key = 'secret-key';
    $settings->prompts = [
        'model' => 'saved-model',
        'rate_limiting_requests_per_minute' => 7,
        'title_generation' => true,
        'title_generation_system' => 'Saved system prompt',
        'title_generation_user_template' => 'Saved {{content}}',
    ];

    $runtime = app(ResolveAIOrchestratorRuntimeSettingsAction::class)->handle($settings);

    expect($runtime->provider)->toMatchArray(['provider' => 'anthropic', 'model' => 'saved-model', 'api_key' => 'secret-key'])
        ->and($runtime->rateLimiting)->toMatchArray(['enabled' => true, 'requests_per_minute' => 7])
        ->and($runtime->prompts['title_generation'])->toMatchArray([
            'enabled' => true,
            'system' => 'Saved system prompt',
            'user_template' => 'Saved {{content}}',
        ]);
});
