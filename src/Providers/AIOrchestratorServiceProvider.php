<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Providers;

use Capell\AIOrchestrator\Integrations\LayoutBuilder\LayoutBuilderAIOrchestratorModule;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Override;
use Spatie\LaravelPackageTools\Package;

class AIOrchestratorServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-ai-orchestrator';

    public static string $packageName = 'capell-app/ai-orchestrator';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasTranslations();
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
        return CapellCore::isPackageInstalled(static::$packageName);
    }

    private function registerBindings(): self
    {
        $this->app->singleton(AIOrchestratorModuleRegistry::class);

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
            fn (AIOrchestratorModuleRegistry $registry): mixed => $this->registerLayoutBuilderModule($registry),
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
