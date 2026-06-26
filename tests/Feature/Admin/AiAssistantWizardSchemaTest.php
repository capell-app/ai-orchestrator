<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;

beforeEach(function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
        dirname(__DIR__, 3) . '/database/settings',
    );

    $settings = app(AIOrchestratorSettings::class);
    $settings->prompts = array_merge($settings->prompts, [
        'title_generation' => true,
        'content_generation' => true,
        'meta_description' => true,
    ]);
    $settings->save();
});

/**
 * @return array<int, mixed>
 */
function invokeWizardSchema(): array
{
    $extender = app(AiAssistantPageResourceExtender::class);
    $wizardSchema = new ReflectionMethod(AiAssistantPageResourceExtender::class, 'wizardSchema');

    return $wizardSchema->invoke($extender);
}

it('builds a wizard with exactly three steps', function (): void {
    $schema = invokeWizardSchema();

    expect($schema)->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(Wizard::class);

    $steps = $schema[0]->getDefaultChildComponents();

    expect($steps)->toHaveCount(3);

    foreach ($steps as $step) {
        expect($step)->toBeInstanceOf(Step::class);
    }
});

it('puts the language select and field checkbox list in the choose step', function (): void {
    $schema = invokeWizardSchema();
    $steps = $schema[0]->getDefaultChildComponents();

    $components = $steps[0]->getDefaultChildComponents();

    $checkboxList = collect($components)->first(
        fn (object $component): bool => $component instanceof CheckboxList && $component->getName() === 'fields',
    );
    $select = collect($components)->first(
        fn (object $component): bool => $component instanceof Select && $component->getName() === 'targetLanguageId',
    );

    expect($checkboxList)->not->toBeNull()
        ->and($select)->not->toBeNull()
        ->and(array_keys($checkboxList->getOptions()))->toBe(['title', 'content', 'meta']);
});

it('puts the keywords textarea in the inputs step', function (): void {
    $schema = invokeWizardSchema();
    $steps = $schema[0]->getDefaultChildComponents();

    $components = $steps[1]->getDefaultChildComponents();

    $keywords = collect($components)->first(
        fn (object $component): bool => $component instanceof Textarea && $component->getName() === 'keywords',
    );

    expect($keywords)->not->toBeNull();
});

it('maps enabled fields to enum labels', function (): void {
    $extender = app(AiAssistantPageResourceExtender::class);
    $fieldOptions = new ReflectionMethod(AiAssistantPageResourceExtender::class, 'fieldOptions');

    $options = $fieldOptions->invoke($extender);

    expect(array_keys($options))->toBe(['title', 'content', 'meta']);
});
