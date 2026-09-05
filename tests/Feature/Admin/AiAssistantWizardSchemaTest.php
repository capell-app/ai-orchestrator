<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;

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
    $wizardSchema = new ReflectionMethod(AiAssistantPageResourceExtender::class, 'wizardSchema');
    $schema = $wizardSchema->invoke($extender);

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

    expect($schema)->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(Wizard::class);

    $steps = wizardSteps($schema[0]);

    expect($steps)->toHaveCount(3);

    foreach ($steps as $step) {
        expect($step)->toBeInstanceOf(Step::class);
    }
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

it('maps enabled fields to enum labels', function (): void {
    $extender = app(AiAssistantPageResourceExtender::class);
    $fieldOptions = new ReflectionMethod(AiAssistantPageResourceExtender::class, 'fieldOptions');

    $options = $fieldOptions->invoke($extender);

    throw_unless(is_array($options), RuntimeException::class, 'Expected AI assistant field options array.');

    expect(array_keys($options))->toBe(['title', 'content', 'meta']);
});

/**
 * Build a stub EditPage Livewire component exposing only the form-state shape
 * the extender reads: the `data` array, the integer `activeTab`, and getRecord().
 *
 * @param  array<string, mixed>  $data
 */
function stubEditPageLivewire(array $data, ?int $activeTab, ?Model $record = null): object
{
    return new class($data, $activeTab, $record)
    {
        /** @param  array<string, mixed>  $data */
        public function __construct(
            public array $data,
            public ?int $activeTab,
            private ?Model $record,
        ) {}

        public function getRecord(): ?Model
        {
            return $this->record;
        }
    };
}

function invokeExtenderMethod(string $method, mixed ...$arguments): mixed
{
    $extender = app(AiAssistantPageResourceExtender::class);

    return (new ReflectionMethod(AiAssistantPageResourceExtender::class, $method))
        ->invoke($extender, ...$arguments);
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

it('resolves the active translation positionally for an in-range tab', function (): void {
    $translations = uuidKeyedTranslations();

    // activeTab 2 must select the SECOND translation, not silently fall to the first.
    expect(invokeExtenderMethod('activeTranslationState', stubEditPageLivewire([], 2), $translations))
        ->toBeArray()->toHaveKey('language_id', 2)
        ->and(invokeExtenderMethod('activeTranslationState', stubEditPageLivewire([], 1), $translations))
        ->toBeArray()->toHaveKey('language_id', 1);
});

it('returns null for an out-of-range, zero, or negative active tab rather than the first translation', function (int $activeTab): void {
    expect(invokeExtenderMethod('activeTranslationState', stubEditPageLivewire([], $activeTab), uuidKeyedTranslations()))
        ->toBeNull();
})->with([
    'out of range' => 5,
    'zero' => 0,
    'negative' => -1,
]);

it('returns null when the active tab is not an integer', function (): void {
    expect(invokeExtenderMethod('activeTranslationState', stubEditPageLivewire([], null), uuidKeyedTranslations()))
        ->toBeNull();
});

it('returns null when there are no translations', function (): void {
    expect(invokeExtenderMethod('activeTranslationState', stubEditPageLivewire([], 1), []))
        ->toBeNull();
});

it('prefills the active translation language and keywords from form state', function (): void {
    $livewire = stubEditPageLivewire(['translations' => uuidKeyedTranslations()], 2);

    expect(invokeExtenderMethod('prefillFromActiveTranslation', $livewire))
        ->toBe(['targetLanguageId' => 2, 'keywords' => 'gamma, delta']);
});

it('does not leak a wrong target language when the active tab is out of range', function (): void {
    // Out-of-range tab => no active translation => no record => no default language.
    $livewire = stubEditPageLivewire(['translations' => uuidKeyedTranslations()], 9);

    expect(invokeExtenderMethod('prefillFromActiveTranslation', $livewire))
        ->toBe(['targetLanguageId' => null, 'keywords' => '']);
});
