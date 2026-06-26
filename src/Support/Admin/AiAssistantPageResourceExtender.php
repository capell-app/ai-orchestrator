<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Admin;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

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
                ->label(__('AI Assistant'))
                ->icon(Heroicon::OutlinedSparkles)
                ->slideOver()
                ->schema([])
                ->visible(fn (): bool => $this->anyCapabilityEnabled()),
        ];
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
