<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Actions\Ai\PruneAiGenerationPayloadsAction;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
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

/**
 * Build a stub EditPage Livewire component holding only the `data` form state.
 * No getRecord() is defined, so the extender resolves a null record.
 *
 * @param  array<string, mixed>  $data
 * @return object{data: array<string, mixed>}
 */
function stubApplyLivewire(array $data): object
{
    return new class($data)
    {
        /** @param  array<string, mixed>  $data */
        public function __construct(public array $data) {}
    };
}

function invokeExtender(string $method, mixed ...$arguments): mixed
{
    $extender = app(AiAssistantPageResourceExtender::class);

    return (new ReflectionMethod(AiAssistantPageResourceExtender::class, $method))
        ->invoke($extender, ...$arguments);
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

it('runs the selected capability through the orchestrator and records generation history', function (): void {
    $livewire = stubApplyLivewire(['translations' => applyFormTranslations()]);

    $generated = invokeExtender('generatePayload', [
        'fields' => ['title'],
        'targetLanguageId' => 2,
        'keywords' => 'seo, marketing',
    ], $livewire);

    expect($generated)->toBeArray()
        ->toMatchArray(['title' => ['Alpha title', 'Beta title', 'Gamma title']]);

    // The pipeline records exactly one history row for the single capability run.
    expect(AIGenerationHistory::query()->count())->toBe(1);
});

it('encrypts persisted generation prompts, output, and metadata', function (): void {
    $history = AIGenerationHistory::query()->create([
        'action' => 'test',
        'input' => 'prompt-secret',
        'output' => 'output-secret',
        'error_message' => 'provider-secret',
        'metadata' => ['token' => 'metadata-secret'],
    ]);
    $rawHistory = DB::table('ai_generation_histories')->where('id', $history->getKey())->first();

    expect($rawHistory)
        ->not->toBeNull()
        ->and((string) $rawHistory->input)->not->toContain('prompt-secret')
        ->and((string) $rawHistory->output)->not->toContain('output-secret')
        ->and((string) $rawHistory->error_message)->not->toContain('provider-secret')
        ->and((string) $rawHistory->metadata)->not->toContain('metadata-secret')
        ->and($history->refresh()->input)->toBe('prompt-secret')
        ->and($history->output)->toBe('output-secret')
        ->and($history->metadata)->toBe(['token' => 'metadata-secret']);
});

it('prunes retained generation payloads without deleting aggregate audit metrics', function (): void {
    $history = AIGenerationHistory::query()->create([
        'action' => 'test',
        'input' => 'expired-prompt',
        'output' => 'expired-output',
        'metadata' => ['expired' => true],
        'cost_micros' => 42,
        'created_at' => now()->subDays(31),
    ]);

    expect(PruneAiGenerationPayloadsAction::run(30))->toBe(1)
        ->and($history->refresh()->input)->toBeNull()
        ->and($history->output)->toBeNull()
        ->and($history->metadata)->toBeNull()
        ->and($history->cost_micros)->toBe(42);
});

it('builds review components for title, content, and meta suggestions', function (): void {
    $components = asArray(invokeExtender('reviewComponents', [
        'title' => ['Title A', 'Title B'],
        'content' => '<p>Generated body</p>',
        'meta' => ['Meta A'],
    ]));

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
    $components = asArray(invokeExtender('reviewComponents', []));

    expect($components)->toHaveCount(1);

    $placeholder = $components[0] ?? null;

    expect($placeholder)->toBeInstanceOf(Placeholder::class);

    if ($placeholder instanceof Placeholder) {
        expect($placeholder->getName())->toBe('review_empty');
    }
});

it('applies the reviewed selections to the translation matching the target language', function (): void {
    $livewire = stubApplyLivewire(['translations' => applyFormTranslations()]);

    invokeExtender('applySelections', $livewire, [
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

    invokeExtender('applySelections', $livewire, [
        'targetLanguageId' => 99,
        'fields' => ['title'],
        'apply' => ['title' => 'Should not land'],
    ]);

    $translations = asArray($livewire->data['translations'] ?? null);

    expect(asArray($translations['uuid-en'] ?? null)['title'])->toBe('Old EN')
        ->and(asArray($translations['uuid-fr'] ?? null)['title'])->toBe('Old FR');
});
