<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Filament\Settings;

use Capell\Admin\Filament\Contracts\HasSchema;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AIOrchestratorSettingsSchema implements HasSchema
{
    public static function make(Schema $configurator): array
    {
        return [
            Grid::make()
                ->statePath('prompts')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('model')
                        ->label(__('capell-ai-orchestrator::package.settings_model'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_model_info'))
                        ->placeholder(is_scalar($defaultModel = config('capell-ai-orchestrator.prism.model')) ? (string) $defaultModel : null),
                    TextInput::make('rate_limiting_requests_per_minute')
                        ->label(__('capell-ai-orchestrator::package.settings_rate_limiting'))
                        ->helperText(__('capell-ai-orchestrator::package.settings_rate_limiting_info'))
                        ->placeholder(is_scalar($requestsPerMinute = config('capell-ai-orchestrator.rate_limiting.requests_per_minute')) ? (string) $requestsPerMinute : null),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('title_generation')
                                ->label(__('capell-admin::form.enabled'))
                                ->reactive(),
                            Grid::make()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('title_generation') === true)
                                ->schema([
                                    Textarea::make('title_generation_system')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_system'))
                                        ->rows(4),
                                    Textarea::make('title_generation_user_template')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_user_template'))
                                        ->rows(4),
                                ]),
                        ]),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('meta_description')
                                ->label(__('capell-admin::form.enabled'))
                                ->reactive(),
                            Grid::make()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('meta_description') === true)
                                ->schema([
                                    Textarea::make('meta_description_system')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_system'))
                                        ->rows(4),
                                    Textarea::make('meta_description_user_template')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_user_template'))
                                        ->rows(4),
                                ]),
                        ]),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('content_generation')
                                ->label(__('capell-admin::form.enabled'))
                                ->reactive(),
                            Grid::make()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('content_generation') === true)
                                ->schema([
                                    Textarea::make('content_generation_system')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_system'))
                                        ->rows(4),
                                    Textarea::make('content_generation_user_template')
                                        ->label(__('capell-ai-orchestrator::package.settings_prompt_user_template'))
                                        ->rows(4),
                                ]),
                        ]),
                ]),
        ];
    }
}
