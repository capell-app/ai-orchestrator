<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Enums\AiAssistantFieldEnum;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Capell\AIOrchestrator\Tests\Fixtures\AiAssistantFormHost;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

beforeEach(function (): void {
    test()->registerAndMigrateSettings(
        [
            '2026_05_10_190871_01_create_ai-orchestrator_settings',
            '2026_09_04_213000_01_encrypt_ai_orchestrator_api_key',
        ],
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
 * @return array<int, Wizard>
 */
function invokeWizardSchema(): array
{
    $extender = app(AiAssistantPageResourceExtender::class);
    $schema = $extender->actions()[0]->getSchema(Schema::make())?->getComponents();

    throw_unless(is_array($schema), RuntimeException::class, 'Expected AI assistant wizard schema array.');

    $wizards = [];

    foreach ($schema as $component) {
        throw_unless($component instanceof Wizard, RuntimeException::class, 'Expected AI assistant wizard schema to contain Wizard components.');

        $wizards[] = $component;
    }

    return $wizards;
}

/**
 * @return array<int, Step>
 */
function wizardSteps(Wizard $wizard): array
{
    $steps = $wizard->getDefaultChildComponents();

    throw_unless(is_array($steps), RuntimeException::class, 'Expected AI assistant wizard steps array.');

    $wizardSteps = [];

    foreach ($steps as $step) {
        throw_unless($step instanceof Step, RuntimeException::class, 'Expected AI assistant wizard step.');

        $wizardSteps[] = $step;
    }

    return $wizardSteps;
}

/**
 * @return array<int, object>
 */
function stepComponents(Step $step): array
{
    $components = $step->getDefaultChildComponents();

    throw_unless(is_array($components), RuntimeException::class, 'Expected AI assistant step components array.');

    return array_values(array_filter($components, static fn (mixed $component): bool => is_object($component)));
}

it('builds a wizard with exactly three steps', function (): void {
    $schema = invokeWizardSchema();

    expect($schema)->toHaveCount(1);

    $steps = wizardSteps($schema[0]);

    expect($steps)->toHaveCount(3);

});

it('puts the language select and field checkbox list in the choose step', function (): void {
    $schema = invokeWizardSchema();
    $steps = wizardSteps($schema[0]);

    $components = stepComponents($steps[0]);

    $checkboxList = collect($components)->first(
        fn (object $component): bool => $component instanceof CheckboxList && $component->getName() === 'fields',
    );
    $select = collect($components)->first(
        fn (object $component): bool => $component instanceof Select && $component->getName() === 'targetLanguageId',
    );

    throw_unless($checkboxList instanceof CheckboxList, RuntimeException::class, 'Expected fields checkbox list.');

    expect($checkboxList)->not->toBeNull()
        ->and($select)->not->toBeNull()
        ->and(array_keys($checkboxList->getOptions()))->toBe(['title', 'content', 'meta']);
});

it('puts the keywords textarea in the inputs step', function (): void {
    $schema = invokeWizardSchema();
    $steps = wizardSteps($schema[0]);

    $components = stepComponents($steps[1]);

    $keywords = collect($components)->first(
        fn (object $component): bool => $component instanceof Textarea && $component->getName() === 'keywords',
    );

    expect($keywords)->not->toBeNull();
});

it('maps enabled fields to their translated labels in the assistant form', function (): void {
    $steps = wizardSteps(invokeWizardSchema()[0]);
    $fields = collect(stepComponents($steps[0]))->first(fn (object $component): bool => $component instanceof CheckboxList);
    throw_unless($fields instanceof CheckboxList, RuntimeException::class);

    expect($fields->getOptions())->toBe([
        'title' => AiAssistantFieldEnum::Title->getLabel(),
        'content' => AiAssistantFieldEnum::Content->getLabel(),
        'meta' => AiAssistantFieldEnum::Meta->getLabel(),
    ]);
});

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @return array<string, mixed>
 */
function mountAssistantForm(array $translations, ?int $activeTab): array
{
    $host = new AiAssistantFormHost;
    $host->data = ['translations' => $translations];
    $host->activeTab = $activeTab;
    $action = app(AiAssistantPageResourceExtender::class)->actions()[0]->livewire($host);
    $schema = $action->getSchema(Schema::make($host)->statePath('assistantData'));
    $action->mount(['schema' => $schema]);

    return $host->assistantData;
}

/**
 * @return array<string, array<string, mixed>>
 */
function uuidKeyedTranslations(): array
{
    return [
        'uuid-en' => ['language_id' => 1, 'title' => 'Hello', 'meta' => ['keywords' => 'alpha, beta']],
        'uuid-fr' => ['language_id' => 2, 'title' => 'Bonjour', 'meta' => ['keywords' => 'gamma, delta']],
    ];
}

it('prefills the selected translation when the assistant opens', function (int $tab, int $language, string $keywords): void {
    expect(mountAssistantForm(uuidKeyedTranslations(), $tab))->toMatchArray([
        'targetLanguageId' => $language,
        'keywords' => $keywords,
    ]);
})->with(['English' => [1, 1, 'alpha, beta'], 'French' => [2, 2, 'gamma, delta']]);

it('opens without selecting a different translation for an invalid active tab', function (?int $tab): void {
    expect(mountAssistantForm(uuidKeyedTranslations(), $tab))->toMatchArray([
        'targetLanguageId' => null,
        'keywords' => '',
    ]);
})->with(['out of range' => 5, 'zero' => 0, 'negative' => -1, 'unset' => null]);

it('opens without a target language when the page has no translations', function (): void {
    expect(mountAssistantForm([], 1))->toMatchArray(['targetLanguageId' => null, 'keywords' => '']);
});
