<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Filament\Settings\AIOrchestratorSettingsSchema;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;
use PHPUnit\Framework\Assert;

it('adds translated helper text to every AI prompt control', function (): void {
    $schema = aiOrchestratorSettingsSchema();
    $components = flattenAiOrchestratorSettingsComponents(array_values(array_filter(
        $schema->getComponents(withHidden: true),
        static fn (mixed $component): bool => $component instanceof Component,
    )));
    $helperTranslations = aiOrchestratorPromptHelperTranslations();

    foreach ($helperTranslations as $field => $translation) {
        expect($translation)
            ->not->toBeEmpty()
            ->not->toBe("capell-ai-orchestrator::package.settings_{$field}_info");

        $component = collect($components)->first(
            fn (mixed $component): bool => ($component instanceof Checkbox || $component instanceof Textarea)
                && $component->getName() === $field,
        );

        throw_unless(
            $component instanceof Checkbox || $component instanceof Textarea,
            RuntimeException::class,
            "Expected {$field} to be a checkbox or textarea.",
        );
        expect(aiOrchestratorHelperText($component))->toBe($translation);
    }
});

it('keeps prompt controls hidden until their corresponding capability is enabled', function (): void {
    $schema = aiOrchestratorSettingsSchema();
    $components = flattenAiOrchestratorSettingsComponents(array_values(array_filter(
        $schema->getComponents(withHidden: true),
        static fn (mixed $component): bool => $component instanceof Component,
    )));

    foreach (['title_generation', 'meta_description', 'content_generation'] as $field) {
        $checkbox = collect($components)->first(
            fn (mixed $component): bool => $component instanceof Checkbox && $component->getName() === $field,
        );
        $promptGrid = collect($components)->first(
            fn (mixed $component): bool => $component instanceof Grid && collect($component->getChildComponents())
                ->contains(fn (mixed $child): bool => $child instanceof Textarea && str_starts_with($child->getName(), "{$field}_")),
        );

        Assert::assertInstanceOf(Checkbox::class, $checkbox);
        Assert::assertInstanceOf(Grid::class, $promptGrid);

        $checkbox->state(false);
        expect($promptGrid->isVisible())->toBeFalse();

        $checkbox->state(true);
        expect($promptGrid->isVisible())->toBeTrue();
    }
});

function aiOrchestratorSettingsSchema(): Schema
{
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };
    $schema = Schema::make($livewire);
    $schema->components(array_values(array_filter(
        AIOrchestratorSettingsSchema::make($schema),
        static fn (mixed $component): bool => $component instanceof Component,
    )));

    return $schema;
}

/**
 * @return array<string, string>
 */
function aiOrchestratorPromptHelperTranslations(): array
{
    return [
        'title_generation' => 'Controls whether the Admin Assistant offers title suggestions. Direct capability calls are not disabled by this setting; when a suggestion runs, configured title prompts are sent to the external AI provider.',
        'title_generation_system' => 'System instructions sent to the external AI provider for title suggestions. Settings are resolved once per process; restart long-lived queue workers after changing them.',
        'title_generation_user_template' => 'User prompt template for title suggestions. Supported tokens: {{content}}, {{current_title}}, and {{keywords}}. Leave tokens unchanged so runtime substitution can provide their values.',
        'meta_description' => 'Controls whether the Admin Assistant offers meta description suggestions. Direct capability calls are not disabled by this setting; when a suggestion runs, configured prompts are sent to the external AI provider.',
        'meta_description_system' => 'System instructions sent to the external AI provider for meta description suggestions. Settings are resolved once per process; restart long-lived queue workers after changing them.',
        'meta_description_user_template' => 'User prompt template for meta description suggestions. Supported tokens: {{content}} and {{keywords}}. Leave tokens unchanged so runtime substitution can provide their values.',
        'content_generation' => 'Controls whether the Admin Assistant offers content generation and refactoring. Direct capability calls are not disabled by this setting; when generation runs, configured prompts are sent to the external AI provider.',
        'content_generation_system' => 'System instructions sent to the external AI provider for content generation and refactoring. Settings are resolved once per process; restart long-lived queue workers after changing them.',
        'content_generation_user_template' => 'User prompt template for content generation and refactoring. Supported tokens: {{content}}, {{current_title}}, {{keywords}}, {{target_length}}, and {{refactor}}. Leave tokens unchanged so runtime substitution can provide their values.',
    ];
}

function aiOrchestratorHelperText(Checkbox|Textarea $component): string
{
    $belowContent = $component->getChildComponents('below_content');

    Assert::assertCount(1, $belowContent);
    Assert::assertInstanceOf(Text::class, $belowContent[0]);

    $content = $belowContent[0]->getContent();
    Assert::assertIsString($content);

    return $content;
}

/**
 * @param  array<array-key, Component>  $components
 * @return list<Component>
 */
function flattenAiOrchestratorSettingsComponents(array $components): array
{
    /** @var list<Component> $flattenedComponents */
    $flattenedComponents = [];

    foreach ($components as $component) {
        $flattenedComponents[] = $component;
        $childComponents = [];
        foreach ($component->getChildSchemas(withHidden: true) as $childSchema) {
            $childComponents = array_merge(
                $childComponents,
                array_values(array_filter(
                    $childSchema->getComponents(withHidden: true),
                    static fn (mixed $childComponent): bool => $childComponent instanceof Component,
                )),
            );
        }

        array_push($flattenedComponents, ...flattenAiOrchestratorSettingsComponents($childComponents));
    }

    return $flattenedComponents;
}
