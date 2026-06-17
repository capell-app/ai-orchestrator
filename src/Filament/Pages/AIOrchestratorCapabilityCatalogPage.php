<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Filament\Pages;

use BackedEnum;
use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;

final class AIOrchestratorCapabilityCatalogPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $slug = 'ai-orchestrator/capability-catalog';

    protected static ?int $navigationSort = 60;

    protected string $view = 'capell-ai-orchestrator::filament.pages.capability-catalog';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-ai-orchestrator::package.catalog_navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return (string) __('capell-admin::navigation.group_system');
    }

    #[Override]
    public function getTitle(): string
    {
        return (string) __('capell-ai-orchestrator::package.catalog_title');
    }

    #[Override]
    public function getSubheading(): string
    {
        return (string) __('capell-ai-orchestrator::package.catalog_subheading');
    }

    /**
     * @return list<array{moduleKey: string, moduleLabel: string, key: string, label: string, description: string, approvalLevel: string, requiredAbility: string|null, actionClass: string}>
     */
    public function capabilities(): array
    {
        return collect(resolve(AIOrchestratorModuleRegistry::class)->modules())
            ->flatMap(
                fn (AIOrchestratorModule $module): array => array_map(
                    fn (AIOrchestratorCapabilityData $capability): array => [
                        'moduleKey' => $module->key(),
                        'moduleLabel' => $module->label(),
                        'key' => $capability->key,
                        'label' => $capability->label,
                        'description' => $capability->description,
                        'approvalLevel' => $capability->approvalLevel->value,
                        'requiredAbility' => $capability->requiredAbility,
                        'actionClass' => $capability->actionClass,
                    ],
                    $module->capabilities(),
                ),
            )
            ->values()
            ->all();
    }
}
