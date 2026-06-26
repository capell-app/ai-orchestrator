<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Admin;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\AIOrchestrator\Actions\RunAIOrchestratorCapabilityAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AiAssistantFieldEnum;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

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

    /**
     * The AI Orchestrator module that owns the authoring capabilities.
     */
    private const MODULE_KEY = 'ai-authoring';

    /**
     * Map of short field keys to their AI Orchestrator capability keys.
     *
     * @var array<string, string>
     */
    private const CAPABILITY_KEYS = [
        'title' => 'suggest-title',
        'content' => 'generate-content',
        'meta' => 'suggest-meta-description',
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
                ->action(function (array $data, mixed $livewire): void {
                    $this->applySelections($livewire, $data);
                })
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
                    ])
                    ->afterValidation(function (Get $get, Set $set, mixed $livewire): void {
                        $set('generated', $this->generatePayload($this->inputsFromState($get), $livewire));
                    }),

                Step::make(__('capell-ai-orchestrator::package.ai_assistant_step_review'))
                    ->schema(fn (Get $get): array => $this->reviewComponents($this->generatedState($get))),
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
        return $this->selectedFieldsFrom($get('fields'));
    }

    /**
     * @return array<int, string>
     */
    private function selectedFieldsFrom(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, 'is_string'));
    }

    /**
     * Normalise the Inputs-step form state into a plain array for generation.
     *
     * @return array<string, mixed>
     */
    private function inputsFromState(Get $get): array
    {
        return [
            'fields' => $this->selectedFields($get),
            'targetLanguageId' => $this->intOrNull($get('targetLanguageId')),
            'keywords' => is_string($get('keywords')) ? $get('keywords') : '',
            'titleIncludeCurrent' => $get('titleIncludeCurrent') === true,
            'contentRefactor' => $get('contentRefactor') === true,
            'contentTargetLength' => $this->intOrNull($get('contentTargetLength')),
            'metaIncludeCurrent' => $get('metaIncludeCurrent') === true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generatedState(Get $get): array
    {
        $generated = $get('generated');

        return is_array($generated) ? $generated : [];
    }

    /**
     * Run each selected capability once and collect its raw output, keyed by
     * field. A failing capability notifies the user and is skipped so the
     * wizard never aborts mid-generation.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function generatePayload(array $inputs, mixed $livewire): array
    {
        $selectedFields = $this->selectedFieldsFrom($inputs['fields'] ?? null);
        $targetLanguageId = $this->intOrNull($inputs['targetLanguageId'] ?? null);

        $sourceTranslation = $targetLanguageId !== null
            ? $this->translationStateForLanguage($livewire, $targetLanguageId)
            : null;

        $record = $this->resolveRecord($livewire);
        $context = [
            'content' => $this->stringFrom($sourceTranslation, 'content'),
            'keywords' => is_string($inputs['keywords'] ?? null) ? $inputs['keywords'] : '',
            'pageId' => $record?->getKey(),
            'pageType' => $record?->getMorphClass(),
            'languageId' => $targetLanguageId ?? 0,
        ];
        $currentTitle = $this->stringFrom($sourceTranslation, 'title');

        $generated = [];

        foreach ($selectedFields as $field) {
            try {
                $generated[$field] = $this->runCapability(
                    $field,
                    $context,
                    $this->optionsForField($field, $inputs, $currentTitle),
                    Auth::user(),
                );
            } catch (Throwable $exception) {
                Notification::make()
                    ->title(__('capell-ai-orchestrator::package.ai_assistant_generation_failed'))
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            }
        }

        return $generated;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     */
    private function runCapability(string $field, array $context, array $options, ?Authenticatable $actor): mixed
    {
        $context['options'] = $options;

        return RunAIOrchestratorCapabilityAction::run(new AIOrchestratorRunData(
            moduleKey: self::MODULE_KEY,
            capabilityKey: self::CAPABILITY_KEYS[$field],
            prompt: $this->stringFrom($context, 'keywords'),
            context: $context,
            actor: $actor,
        ));
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function optionsForField(string $field, array $inputs, string $currentTitle): array
    {
        $userId = Auth::id();
        $options = is_int($userId) ? ['user_id' => $userId] : [];

        return match ($field) {
            'title' => ($inputs['titleIncludeCurrent'] ?? false) === true && $currentTitle !== ''
                ? $options + ['current_title' => $currentTitle]
                : $options,
            'content' => $options + array_filter(
                [
                    'current_title' => $currentTitle !== '' ? $currentTitle : null,
                    'target_length' => $this->intOrNull($inputs['contentTargetLength'] ?? null),
                    'refactor' => ($inputs['contentRefactor'] ?? false) === true,
                ],
                static fn (mixed $value): bool => $value !== null,
            ),
            default => $options,
        };
    }

    /**
     * Build the Review-step components from the generated payload: a Radio of
     * options for title/meta, and an editable preview for content.
     *
     * @param  array<string, mixed>  $generated
     * @return array<int, Placeholder|Radio|Textarea>
     */
    private function reviewComponents(array $generated): array
    {
        $components = [];

        $titleOptions = $this->stringList($generated['title'] ?? null);

        if ($titleOptions !== []) {
            $components[] = Radio::make('apply.title')
                ->label(__('capell-ai-orchestrator::package.ai_assistant_review_title'))
                ->options($titleOptions);
        }

        $content = $generated['content'] ?? null;

        if (is_string($content) && $content !== '') {
            $components[] = Textarea::make('apply.content')
                ->label(__('capell-ai-orchestrator::package.ai_assistant_review_content'))
                ->default($content)
                ->rows(8);
        }

        $metaOptions = $this->stringList($generated['meta'] ?? null);

        if ($metaOptions !== []) {
            $components[] = Radio::make('apply.meta')
                ->label(__('capell-ai-orchestrator::package.ai_assistant_review_meta'))
                ->options($metaOptions);
        }

        if ($components === []) {
            $components[] = Placeholder::make('review_empty')
                ->content(__('capell-ai-orchestrator::package.ai_assistant_review_empty'));
        }

        return $components;
    }

    /**
     * @return array<string, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $options = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $options[$value] = $value;
            }
        }

        return $options;
    }

    /**
     * Write the reviewed selections back into the target translation's form
     * state, resolved by language id (never by tab position).
     *
     * @param  array<string, mixed>  $formState
     */
    private function applySelections(mixed $livewire, array $formState): void
    {
        $targetLanguageId = $this->intOrNull($formState['targetLanguageId'] ?? null);
        $translationKey = $targetLanguageId === null
            ? null
            : $this->translationKeyForLanguage($livewire, $targetLanguageId);

        if ($translationKey === null || ! is_object($livewire) || ! isset($livewire->data) || ! is_array($livewire->data)) {
            Notification::make()
                ->title(__('capell-ai-orchestrator::package.ai_assistant_apply_no_translation'))
                ->danger()
                ->send();

            return;
        }

        $selectedFields = $this->selectedFieldsFrom($formState['fields'] ?? null);
        $apply = is_array($formState['apply'] ?? null) ? $formState['apply'] : [];
        $data = $livewire->data;
        $appliedCount = 0;

        foreach ([
            'title' => "translations.{$translationKey}.title",
            'content' => "translations.{$translationKey}.content",
            'meta' => "translations.{$translationKey}.meta.description",
        ] as $field => $path) {
            if (! in_array($field, $selectedFields, true)) {
                continue;
            }

            $value = $apply[$field] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            data_set($data, $path, $value);
            $appliedCount++;
        }

        $livewire->data = $data;

        Notification::make()
            ->title(trans_choice('capell-ai-orchestrator::package.ai_assistant_applied', $appliedCount, ['count' => $appliedCount]))
            ->success()
            ->send();
    }

    /**
     * Resolve the translations-repeater item key (UUID) whose language_id
     * matches the requested language, or null when none match.
     */
    private function translationKeyForLanguage(mixed $livewire, int $languageId): ?string
    {
        foreach ($this->translationsState($livewire) as $key => $translation) {
            if (is_array($translation) && $this->intOrNull($translation['language_id'] ?? null) === $languageId) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function translationStateForLanguage(mixed $livewire, int $languageId): ?array
    {
        foreach ($this->translationsState($livewire) as $translation) {
            if (is_array($translation) && $this->intOrNull($translation['language_id'] ?? null) === $languageId) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>|null  $source
     */
    private function stringFrom(?array $source, string $key): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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
