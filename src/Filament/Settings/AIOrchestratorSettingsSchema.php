<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Filament\Settings;

use Capell\Admin\Filament\Contracts\HasSchema;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Override;

class AIOrchestratorSettingsSchema implements HasSchema
{
    #[Override]
    public static function make(Schema $configurator): array
    {
        return [
            Select::make('ai_provider')
                ->label(__('capell-ai-orchestrator::package.settings_provider'))
                ->helperText(__('capell-ai-orchestrator::package.settings_provider_info'))
                ->options(['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini', 'ollama' => 'Ollama'])
                ->required(),
            TextInput::make('ai_api_key')
                ->label(__('capell-ai-orchestrator::package.settings_api_key'))
                ->helperText(__('capell-ai-orchestrator::package.settings_api_key_info'))
                ->password()
                ->revealable()
                ->autocomplete(false)
                // The saved key is never sent to the browser, and a blank field keeps it.
                ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Grid::make()
                ->statePath('prompts')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('model')
                        ->label(__('capell-ai-orchestrator::package.settings_model'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_model_info'))
                        ->placeholder(is_scalar($defaultModel = config('capell-ai-orchestrator.prism.model')) ? (string) $defaultModel : null),
                    Textarea::make('brand_voice_tone')
                        ->label(__('capell-ai-orchestrator::package.settings_brand_voice_tone'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_brand_voice_tone_info'))
                        ->rows(2),
                    KeyValue::make('brand_voice_glossary')
                        ->label(__('capell-ai-orchestrator::package.settings_brand_voice_glossary'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_brand_voice_glossary_info'))
                        ->keyLabel(__('capell-ai-orchestrator::package.settings_brand_voice_glossary_term'))
                        ->valueLabel(__('capell-ai-orchestrator::package.settings_brand_voice_glossary_preferred')),
                    TagsInput::make('brand_voice_banned_words')
                        ->label(__('capell-ai-orchestrator::package.settings_brand_voice_banned_words'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_brand_voice_banned_words_info')),
                    Textarea::make('brand_voice_examples')
                        ->label(__('capell-ai-orchestrator::package.settings_brand_voice_examples'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_brand_voice_examples_info'))
                        ->rows(4),
                    TagsInput::make('allowed_models')
                        ->label(__('capell-ai-orchestrator::package.settings_allowed_models'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_allowed_models_info')),
                    TextInput::make('retention_generation_payload_days')
                        ->label(__('capell-ai-orchestrator::package.settings_retention_days'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_retention_days_info'))
                        ->numeric()
                        ->minValue(1)
                        ->placeholder(is_scalar($days = config('capell-ai-orchestrator.retention.generation_payload_days')) ? (string) $days : null),
                    Checkbox::make('redact_personal_data')
                        ->label(__('capell-ai-orchestrator::package.settings_redact_personal_data'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_redact_personal_data_info'))
                        ->default(true),
                    Checkbox::make('restore_redacted_output')
                        ->label(__('capell-ai-orchestrator::package.settings_restore_redacted_output'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_restore_redacted_output_info')),
                    TextInput::make('rate_limiting_requests_per_minute')
                        ->label(__('capell-ai-orchestrator::package.settings_rate_limiting'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_rate_limiting_info'))
                        ->placeholder(is_scalar($requestsPerMinute = config('capell-ai-orchestrator.rate_limiting.requests_per_minute')) ? (string) $requestsPerMinute : null),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('title_generation')
                                ->label(__('capell-admin::form.enabled'))
                                ->helperText(__('capell-ai-orchestrator::package.settings_title_generation_info'))
                                ->reactive(),
                            Grid::make()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('title_generation') === true)
                                ->schema([
                                    Textarea::make('title_generation_system')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_system'))
                                        ->helperText(__('capell-ai-orchestrator::package.settings_title_generation_system_info'))
                                        ->rows(4),
                                    Textarea::make('title_generation_user_template')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_user_template'))
                                        ->helperText(__('capell-ai-orchestrator::package.settings_title_generation_user_template_info'))
                                        ->rows(4),
                                ]),
                        ]),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('meta_description')
                                ->label(__('capell-admin::form.enabled'))
                                ->helperText(__('capell-ai-orchestrator::package.settings_meta_description_info'))
                                ->reactive(),
                            Grid::make()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('meta_description') === true)
                                ->schema([
                                    Textarea::make('meta_description_system')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_system'))
                                        ->helperText(__('capell-ai-orchestrator::package.settings_meta_description_system_info'))
                                        ->rows(4),
                                    Textarea::make('meta_description_user_template')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_user_template'))
                                        ->helperText(__('capell-ai-orchestrator::package.settings_meta_description_user_template_info'))
                                        ->rows(4),
                                ]),
                        ]),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('content_generation')
                                ->label(__('capell-admin::form.enabled'))
                                ->helperText(__('capell-ai-orchestrator::package.settings_content_generation_info'))
                                ->reactive(),
                            Grid::make()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('content_generation') === true)
                                ->schema([
                                    Textarea::make('content_generation_system')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_system'))
                                        ->helperText(__('capell-ai-orchestrator::package.settings_content_generation_system_info'))
                                        ->rows(4),
                                    Textarea::make('content_generation_user_template')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_user_template'))
                                        ->helperText(__('capell-ai-orchestrator::package.settings_content_generation_user_template_info'))
                                        ->rows(4),
                                ]),
                        ]),
                ]),
        ];
    }
}
