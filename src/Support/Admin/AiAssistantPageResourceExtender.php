<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Admin;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\AIOrchestrator\Enums\AiAssistantFieldEnum;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class AiAssistantPageResourceExtender implements ResourceHeaderActionExtender
{
    /**
     * Map of short capability keys to their per-capability prompt flag keys.
     *
     * @var array<string, string>
     */
    private const CAPABILITY_FLAGS = [
        'title' => 'title_generation',
        'content' => 'content_generation',
        'meta' => 'meta_description',
    ];

    public function supports(string $pageClass): bool
    {
        return $pageClass === EditPage::class;
    }

    /**
     * @return array<int, Action>
     */
    public function actions(): array
    {
        return [
            Action::make('ai-assistant')
                ->label(__('capell-ai-orchestrator::package.ai_assistant_action'))
                ->icon(Heroicon::OutlinedSparkles)
                ->slideOver()
                ->fillForm(fn (array $arguments, mixed $livewire): array => $this->prefillFromActiveTranslation($livewire))
                ->schema(fn (): array => $this->wizardSchema())
                ->visible(fn (): bool => $this->anyCapabilityEnabled()),
        ];
    }

    /**
     * @return array<int, Wizard>
     */
    private function wizardSchema(): array
    {
        return [
            Wizard::make([
                Step::make(__('capell-ai-orchestrator::package.ai_assistant_step_choose'))
                    ->schema([
                        Select::make('targetLanguageId')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_target_language'))
                            ->options(fn (mixed $livewire): array => $this->languageOptions($livewire))
                            ->default(fn (mixed $livewire): ?int => $this->defaultLanguageId($livewire))
                            ->required(),
                        CheckboxList::make('fields')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_fields'))
                            ->options($this->fieldOptions())
                            ->required()
                            ->minItems(1),
                    ]),

                Step::make(__('capell-ai-orchestrator::package.ai_assistant_step_inputs'))
                    ->schema([
                        Textarea::make('keywords')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_keywords'))
                            ->rows(2),
                        Radio::make('titleIncludeCurrent')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_title_include_current'))
                            ->boolean()
                            ->default(true)
                            ->visible(fn (Get $get): bool => in_array('title', $this->selectedFields($get), true)),
                        Checkbox::make('contentRefactor')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_content_refactor'))
                            ->visible(fn (Get $get): bool => in_array('content', $this->selectedFields($get), true)),
                        TextInput::make('contentTargetLength')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_content_target_length'))
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(2000)
                            ->visible(fn (Get $get): bool => in_array('content', $this->selectedFields($get), true)),
                        Radio::make('metaIncludeCurrent')
                            ->label(__('capell-ai-orchestrator::package.ai_assistant_meta_include_current'))
                            ->boolean()
                            ->default(true)
                            ->visible(fn (Get $get): bool => in_array('meta', $this->selectedFields($get), true)),
                    ]),

                Step::make(__('capell-ai-orchestrator::package.ai_assistant_step_review'))
                    ->schema([]),
            ]),
        ];
    }

    /**
     * Map enabled short capability keys to their enum labels.
     *
     * @return array<string, string>
     */
    private function fieldOptions(): array
    {
        $options = [];

        foreach ($this->enabledFields() as $fieldKey) {
            $options[$fieldKey] = AiAssistantFieldEnum::from($fieldKey)->getLabel();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function selectedFields(Get $get): array
    {
        $selectedFields = $get('fields');

        if (! is_array($selectedFields)) {
            return [];
        }

        return array_values(array_filter($selectedFields, 'is_string'));
    }

    /**
     * Build the target-language options from the edited page's translations.
     *
     * @return array<int, string>
     */
    private function languageOptions(mixed $livewire): array
    {
        $record = $this->resolveRecord($livewire);

        if ($record === null) {
            return [];
        }

        $options = [];

        foreach ($this->translationsOf($record) as $translation) {
            $options[$this->languageIdOf($translation)] = $this->languageName($translation);
        }

        return $options;
    }

    /**
     * Resolve the default target language: the active tab, else the default
     * language translation, else the first available translation.
     */
    private function defaultLanguageId(mixed $livewire): ?int
    {
        $activeLanguageId = $this->activeTranslationLanguageId($livewire);

        if ($activeLanguageId !== null) {
            return $activeLanguageId;
        }

        $record = $this->resolveRecord($livewire);

        if ($record === null) {
            return null;
        }

        $firstLanguageId = null;

        foreach ($this->translationsOf($record) as $translation) {
            $languageId = $this->languageIdOf($translation);
            $firstLanguageId ??= $languageId;

            $language = $translation->getAttribute('language');

            if (is_object($language) && ($language->default ?? false) === true) {
                return $languageId;
            }
        }

        return $firstLanguageId;
    }

    private function languageIdOf(Model $translation): int
    {
        $languageId = $translation->getAttribute('language_id');

        return is_scalar($languageId) ? (int) $languageId : 0;
    }

    /**
     * @return array<int, Model>
     */
    private function translationsOf(Model $record): array
    {
        $translations = $record->getAttribute('translations');

        if (! $translations instanceof Collection) {
            return [];
        }

        return array_values(array_filter(
            $translations->all(),
            static fn (mixed $translation): bool => $translation instanceof Model,
        ));
    }

    /**
     * Best-effort prefill: the active translation's language and keywords.
     *
     * @return array<string, mixed>
     */
    private function prefillFromActiveTranslation(mixed $livewire): array
    {
        $activeLanguageId = $this->activeTranslationLanguageId($livewire) ?? $this->defaultLanguageId($livewire);

        $keywords = '';
        $translations = $this->translationsState($livewire);
        $activeTranslation = $this->activeTranslationState($livewire, $translations);

        if ($activeTranslation !== null) {
            $meta = $activeTranslation['meta'] ?? null;
            $metaKeywords = is_array($meta) ? ($meta['keywords'] ?? null) : null;
            $keywords = is_string($metaKeywords) ? $metaKeywords : '';
        }

        return [
            'targetLanguageId' => $activeLanguageId,
            'keywords' => $keywords,
        ];
    }

    /**
     * Resolve the language_id of the currently active translation tab from
     * the EditPage form state.
     */
    private function activeTranslationLanguageId(mixed $livewire): ?int
    {
        $translations = $this->translationsState($livewire);
        $activeTranslation = $this->activeTranslationState($livewire, $translations);

        if ($activeTranslation === null) {
            return null;
        }

        $languageId = $activeTranslation['language_id'] ?? null;

        return is_scalar($languageId) ? (int) $languageId : null;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<array-key, mixed>|null
     */
    private function activeTranslationState(mixed $livewire, array $translations): ?array
    {
        if ($translations === []) {
            return null;
        }

        if (! is_object($livewire) || ! isset($livewire->activeTab) || ! is_int($livewire->activeTab)) {
            return null;
        }

        $activeTab = $livewire->activeTab;

        // activeTab is a 1-indexed tab position over the translations repeater
        // (RepeaterTabs). Map it positionally to the item key, but never coerce
        // an out-of-range tab onto the first translation — return null so the
        // caller falls back to the default-language translation instead.
        if ($activeTab < 1) {
            return null;
        }

        $translationKeys = array_keys($translations);
        $activeKey = $translationKeys[$activeTab - 1] ?? null;

        if ($activeKey === null) {
            return null;
        }

        $activeTranslation = $translations[$activeKey] ?? null;

        return is_array($activeTranslation) ? $activeTranslation : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function translationsState(mixed $livewire): array
    {
        if (! is_object($livewire) || ! isset($livewire->data) || ! is_array($livewire->data)) {
            return [];
        }

        $translations = $livewire->data['translations'] ?? null;

        return is_array($translations) ? $translations : [];
    }

    private function resolveRecord(mixed $livewire): ?Model
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getRecord')) {
            return null;
        }

        $record = $livewire->getRecord();

        return $record instanceof Model ? $record : null;
    }

    private function languageName(Model $translation): string
    {
        $language = $translation->getAttribute('language');

        if (is_object($language)) {
            if (method_exists($language, 'getLabel')) {
                return (string) $language->getLabel();
            }

            if (isset($language->name) && is_scalar($language->name)) {
                return (string) $language->name;
            }
        }

        return (string) $this->languageIdOf($translation);
    }

    /**
     * Return the short capability keys whose per-capability prompt flag is enabled.
     *
     * @return array<int, string>
     */
    private function enabledFields(): array
    {
        $prompts = resolve(AIOrchestratorSettings::class)->prompts;

        $enabledFields = [];

        foreach (self::CAPABILITY_FLAGS as $fieldKey => $flagKey) {
            if (isset($prompts[$flagKey]) && $prompts[$flagKey] === true) {
                $enabledFields[] = $fieldKey;
            }
        }

        return $enabledFields;
    }

    private function anyCapabilityEnabled(): bool
    {
        return $this->enabledFields() !== [];
    }
}
