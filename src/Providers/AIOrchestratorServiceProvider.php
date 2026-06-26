<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Providers;

use Capell\AIOrchestrator\Integrations\LayoutBuilder\LayoutBuilderAIOrchestratorModule;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\AIOrchestrator\Support\AIOrchestratorPolicyGuardrailRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Override;
use Spatie\LaravelPackageTools\Package;

final class AIOrchestratorServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-ai-orchestrator';

    public static string $packageName = 'capell-app/ai-orchestrator';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile(self::$name)
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasMigrations([
                '2026_05_10_190870_02_create_ai_generation_histories_table',
                '2026_06_08_000001_add_cost_fields_to_ai_generation_histories_table',
            ]);
    }

    public function registeringPackage(): void
    {
        $this
            ->registerBindings();

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->registerServices();
        });
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }

    private function registerBindings(): self
    {
        $this->app->singleton(AIOrchestratorModuleRegistry::class);
        $this->app->singleton(AIOrchestratorPolicyGuardrailRegistry::class);

        return $this;
    }

    private function registerServices(): self
    {
        if ($this->app->resolved(AIOrchestratorModuleRegistry::class)) {
            $this->registerLayoutBuilderModule($this->app->make(AIOrchestratorModuleRegistry::class));

            return $this;
        }

        $this->app->afterResolving(
            AIOrchestratorModuleRegistry::class,
            function (AIOrchestratorModuleRegistry $registry): void {
                $this->registerLayoutBuilderModule($registry);
            },
        );

        return $this;
    }

    private function registerLayoutBuilderModule(AIOrchestratorModuleRegistry $registry): void
    {
        if (array_key_exists('layout-builder', $registry->modules())) {
            return;
        }

        $registry->register(new LayoutBuilderAIOrchestratorModule);
    }
}
