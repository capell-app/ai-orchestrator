<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Jobs\RunAiAssistantGenerationJob;
use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Models\AiGenerationRequest;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\Tests\Support\SchemaStateHarness;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    test()->registerAndMigrateSettings(
        [
            '2026_05_10_190871_01_create_ai-orchestrator_settings',
            '2026_09_04_213000_01_encrypt_ai_orchestrator_api_key',
        ],
        dirname(__DIR__, 3) . '/database/settings',
    );

    // Fake the AI provider at the chat boundary so the real capability pipeline
    // (and its AIGenerationHistory write) runs deterministically.
    app()->instance(PrismProvider::class, new class([]) extends PrismProvider
    {
        public function chat(array $params): AiResponse
        {
            return new AiResponse(
                content: "- Alpha title\n- Beta title\n- Gamma title",
                tokensUsed: 12,
                model: 'fake-model',
                duration: 0.01,
            );
        }
    });
});

/** @param array<string, mixed> $data */
function stubApplyLivewire(array $data): SchemaStateHarness
{
    $livewire = new SchemaStateHarness;
    $livewire->data = $data;

    return $livewire;
}

/** @param array<string, mixed> $state */
function aiAssistantStep(SchemaStateHarness $livewire, array $state, int $index): Step
{
    $livewire->data = [...$livewire->data, ...$state];
    $action = resolve(AiAssistantPageResourceExtender::class)->actions()[0]->livewire($livewire);
    $schema = $action->getSchema(Schema::make($livewire)->statePath('data'));
    $wizard = $schema?->getComponents(withHidden: true)[0] ?? null;
    throw_unless($wizard instanceof Wizard, RuntimeException::class);
    $step = $wizard->getChildSchema()?->getComponents(withHidden: true)[$index] ?? null;
    throw_unless($step instanceof Step, RuntimeException::class);

    return $step;
}

/**
 * @param  array<string, mixed>  $inputs
 * @return array<array-key, mixed>
 */
function generateAiAssistantPayload(array $inputs, SchemaStateHarness $livewire): array
{
    aiAssistantStep($livewire, $inputs, 1)->callAfterValidation();
    $generated = $livewire->data['generated'] ?? null;
    throw_unless(is_array($generated), RuntimeException::class);

    return $generated;
}

/**
 * @param  array<string, mixed>  $generated
 * @return array<array-key, mixed>
 */
function aiAssistantReviewComponents(array $generated): array
{
    return aiAssistantStep(new SchemaStateHarness, ['generated' => $generated], 2)->getChildSchema()?->getComponents(withHidden: true) ?? [];
}

/** @param array<string, mixed> $data */
function applyAiAssistantSelections(SchemaStateHarness $livewire, array $data): void
{
    resolve(AiAssistantPageResourceExtender::class)->actions()[0]->livewire($livewire)->call(['data' => $data]);
}

/**
 * @return array<array-key, mixed>
 */
function asArray(mixed $value): array
{
    return is_array($value) ? $value : [];
}

/**
 * @return array<string, array<string, mixed>>
 */
function applyFormTranslations(): array
{
    return [
        'uuid-en' => ['language_id' => 1, 'title' => 'Old EN', 'content' => 'Old EN body', 'meta' => ['description' => 'Old EN meta']],
        'uuid-fr' => ['language_id' => 2, 'title' => 'Old FR', 'content' => 'Old FR body', 'meta' => ['description' => 'Old FR meta']],
    ];
}

it('queues the selected capability and returns the completed result on the next review attempt', function (): void {
    $livewire = stubApplyLivewire(['translations' => applyFormTranslations()]);

    $generated = generateAiAssistantPayload([
        'fields' => ['title'],
        'targetLanguageId' => 2,
        'keywords' => 'seo, marketing',
    ], $livewire);

    expect($generated)->toBe([]);
    Queue::assertPushed(RunAiAssistantGenerationJob::class, 1);
    expect(AIGenerationHistory::query()->count())->toBe(0);

    $request = AiGenerationRequest::query()->sole();
    $requestId = $request->getKey();
    throw_unless(is_int($requestId), RuntimeException::class, 'Expected an integer generation request key.');
    new RunAiAssistantGenerationJob($requestId)->handle();

    $completed = generateAiAssistantPayload([
        'fields' => ['title'],
        'targetLanguageId' => 2,
        'keywords' => 'seo, marketing',
    ], $livewire);

    expect($completed)->toMatchArray(['title' => ['Alpha title', 'Beta title', 'Gamma title']]);

    // The pipeline records exactly one history row for the single capability run.
    expect(AIGenerationHistory::query()->count())->toBe(1);
});

it('builds review components for title, content, and meta suggestions', function (): void {
    $components = aiAssistantReviewComponents([
        'title' => ['Title A', 'Title B'],
        'content' => '<p>Generated body</p>',
        'meta' => ['Meta A'],
    ]);

    expect($components)->toHaveCount(3);

    $title = $components[0] ?? null;
    $content = $components[1] ?? null;
    $meta = $components[2] ?? null;

    expect($title)->toBeInstanceOf(Radio::class)
        ->and($content)->toBeInstanceOf(Textarea::class)
        ->and($meta)->toBeInstanceOf(Radio::class);

    if ($title instanceof Radio && $content instanceof Textarea && $meta instanceof Radio) {
        expect($title->getName())->toBe('apply.title')
            ->and($content->getName())->toBe('apply.content')
            ->and($meta->getName())->toBe('apply.meta');
    }
});

it('shows an empty-state placeholder when nothing was generated', function (): void {
    $components = aiAssistantReviewComponents([]);

    expect($components)->toHaveCount(1);

    $placeholder = $components[0] ?? null;

    expect($placeholder)->toBeInstanceOf(Placeholder::class);

    if ($placeholder instanceof Placeholder) {
        expect($placeholder->getName())->toBe('review_empty');
    }
});

it('applies the reviewed selections to the translation matching the target language', function (): void {
    $livewire = stubApplyLivewire(['translations' => applyFormTranslations()]);

    applyAiAssistantSelections($livewire, [
        'targetLanguageId' => 2,
        'fields' => ['title', 'meta'],
        'apply' => ['title' => 'New FR Title', 'meta' => 'New FR Meta'],
    ]);

    $translations = asArray($livewire->data['translations'] ?? null);
    $french = asArray($translations['uuid-fr'] ?? null);
    $english = asArray($translations['uuid-en'] ?? null);

    expect($french['title'])->toBe('New FR Title')
        ->and(asArray($french['meta'])['description'])->toBe('New FR Meta')
        // content was not selected, so it is untouched...
        ->and($french['content'])->toBe('Old FR body')
        // ...and the EN sibling is never touched.
        ->and($english['title'])->toBe('Old EN')
        ->and(asArray($english['meta'])['description'])->toBe('Old EN meta');
});

it('applies nothing when no translation matches the target language', function (): void {
    $livewire = stubApplyLivewire(['translations' => applyFormTranslations()]);

    applyAiAssistantSelections($livewire, [
        'targetLanguageId' => 99,
        'fields' => ['title'],
        'apply' => ['title' => 'Should not land'],
    ]);

    $translations = asArray($livewire->data['translations'] ?? null);

    expect(asArray($translations['uuid-en'] ?? null)['title'])->toBe('Old EN')
        ->and(asArray($translations['uuid-fr'] ?? null)['title'])->toBe('Old FR');
});
