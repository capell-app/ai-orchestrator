<?php

declare(strict_types=1);

use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;

beforeEach(function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
        dirname(__DIR__, 3) . '/database/settings',
    );
});

it('exposes a single AI Assistant action for the page edit screen', function (): void {
    $extender = app(AiAssistantPageResourceExtender::class);

    expect($extender->supports(EditPage::class))->toBeTrue();
    expect($extender->supports(stdClass::class))->toBeFalse();
    expect($extender->actions())->toHaveCount(1);
    expect($extender->actions()[0]->getName())->toBe('ai-assistant');
});

it('hides the action when no authoring capability is enabled', function (): void {
    $settings = app(AIOrchestratorSettings::class);
    $settings->prompts = array_merge($settings->prompts, [
        'title_generation' => false,
        'content_generation' => false,
        'meta_description' => false,
    ]);
    $settings->save();

    $action = app(AiAssistantPageResourceExtender::class)->actions()[0];

    expect($action->isVisible())->toBeFalse();
});

it('shows the action when at least one authoring capability is enabled', function (): void {
    $settings = app(AIOrchestratorSettings::class);
    $settings->prompts = array_merge($settings->prompts, [
        'title_generation' => true,
        'content_generation' => false,
        'meta_description' => false,
    ]);
    $settings->save();

    $action = app(AiAssistantPageResourceExtender::class)->actions()[0];

    expect($action->isVisible())->toBeTrue();
});
