<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Providers;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\AIOrchestrator\Actions\ResolveAIOrchestratorRuntimeSettingsAction;
use Capell\AIOrchestrator\Console\Commands\PruneAiGenerationPayloadsCommand;
use Capell\AIOrchestrator\Data\AIOrchestratorRuntimeSettingsData;
use Capell\AIOrchestrator\Events\Ai\AiGenerationCompleted;
use Capell\AIOrchestrator\Events\Ai\AiGenerationFailed;
use Capell\AIOrchestrator\Filament\Settings\AIOrchestratorSettingsSchema;
use Capell\AIOrchestrator\Integrations\Authoring\AIAuthoringModule;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\LayoutBuilderAIOrchestratorModule;
use Capell\AIOrchestrator\Listeners\Ai\LogAiGeneration;
use Capell\AIOrchestrator\Listeners\Ai\NotifyAiFailure;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Capell\AIOrchestrator\Support\Ai\AIGenerationCache;
use Capell\AIOrchestrator\Support\Ai\AiRateLimiter;
use Capell\AIOrchestrator\Support\Ai\AiResponseParser;
use Capell\AIOrchestrator\Support\Ai\AiTokenCounter;
use Capell\AIOrchestrator\Support\Ai\Cache\RateLimitCache;
use Capell\AIOrchestrator\Support\Ai\Concerns\NormalizesAiValues;
use Capell\AIOrchestrator\Support\Ai\ManagedProviderCallRepository;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Support\Ai\PromptRepository;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\AIOrchestrator\Support\AIOrchestratorPolicyGuardrailRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Spatie\LaravelPackageTools\Package;

final class AIOrchestratorServiceProvider extends AbstractPackageServiceProvider
{
    use NormalizesAiValues;

    public static string $name = 'capell-ai-orchestrator';

    public static string $packageName = 'capell-app/ai-orchestrator';

    /**
     * @return list<string>
     */
    public static function getSettingMigrations(): array
    {
        return [
            '2026_05_10_190871_01_create_ai-orchestrator_settings',
        ];
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile(self::$name)
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasCommand(PruneAiGenerationPayloadsCommand::class)
            ->hasMigrations([
                '2026_05_10_190870_02_create_ai_generation_histories_table',
                '2026_06_08_000001_add_cost_fields_to_ai_generation_histories_table',
                '2026_07_10_000001_add_site_id_to_ai_generation_histories_table',
                '2026_07_10_000002_create_ai_generation_requests_table',
                '2026_07_10_000003_encrypt_ai_generation_requests_table',
                '2026_07_20_000001_create_ai_managed_provider_calls_table',
            ]);
    }

    public function registeringPackage(): void
    {
        parent::registeringPackage();

        $this
            ->registerBindings()
            ->registerAiEngineBindings();

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this
                ->registerServices()
                ->registerAiEventListeners()
                ->registerSettingsSchema()
                ->registerPayloadPruningSchedule();
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
        $this->app->singleton(ManagedProviderCallRepository::class);

        $this->app->tag([AiAssistantPageResourceExtender::class], ResourceHeaderActionExtender::TAG);

        return $this;
    }

    private function registerAiEngineBindings(): self
    {
        $this->app->singleton(AIOrchestratorRuntimeSettingsData::class, fn (): AIOrchestratorRuntimeSettingsData => ResolveAIOrchestratorRuntimeSettingsAction::run());
        $this->app->singleton(PrismProvider::class, fn (Application $app): PrismProvider => new PrismProvider(
            $app->make(AIOrchestratorRuntimeSettingsData::class)->provider,
            $app->make(AIGenerationCache::class),
            $app->make(ManagedProviderCallRepository::class),
        ));

        $this->app->singleton(PromptRepository::class, fn (Application $app): PromptRepository => new PromptRepository($app->make(AIOrchestratorRuntimeSettingsData::class)->prompts));

        $this->app->singleton(AiResponseParser::class, fn (): AiResponseParser => new AiResponseParser);

        $this->app->singleton(AiRateLimiter::class, fn (Application $app): AiRateLimiter => new AiRateLimiter(
            $app->make(RateLimitCache::class),
            $app->make(AIOrchestratorRuntimeSettingsData::class)->rateLimiting,
        ));

        $this->app->singleton(AiTokenCounter::class, fn (): AiTokenCounter => new AiTokenCounter);

        $this->app->singleton(AIGenerationCache::class, fn (Application $app): AIGenerationCache => new AIGenerationCache(
            $this->aiString(config('cache.default')),
            $this->aiInt(config('capell-ai-orchestrator.cache.ttl', 86400)),
        ));

        $this->app->singleton(RateLimitCache::class, fn (Application $app): RateLimitCache => new RateLimitCache($this->aiString(config('cache.default'))));

        return $this;
    }

    private function registerAiEventListeners(): self
    {
        $events = $this->app->make(Dispatcher::class);
        $events->listen(
            AiGenerationFailed::class,
            NotifyAiFailure::class,
        );
        $events->listen(
            AiGenerationCompleted::class,
            LogAiGeneration::class,
        );

        return $this;
    }

    private function registerSettingsSchema(): self
    {
        $this->surface()->settingsSchema('ai-orchestrator', AIOrchestratorSettingsSchema::class);
        $this->surface()->settingsClass('ai-orchestrator', AIOrchestratorSettings::class);

        return $this;
    }

    private function registerPayloadPruningSchedule(): self
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('capell:ai-orchestrator:prune-generation-payloads')
                ->daily()
                ->withoutOverlapping()
                ->onOneServer();
        });

        return $this;
    }

    private function registerServices(): self
    {
        if ($this->app->resolved(AIOrchestratorModuleRegistry::class)) {
            $this->registerOrchestratorModules($this->app->make(AIOrchestratorModuleRegistry::class));

            return $this;
        }

        $this->app->afterResolving(
            AIOrchestratorModuleRegistry::class,
            function (AIOrchestratorModuleRegistry $registry): void {
                $this->registerOrchestratorModules($registry);
            },
        );

        return $this;
    }

    private function registerOrchestratorModules(AIOrchestratorModuleRegistry $registry): void
    {
        $this->registerLayoutBuilderModule($registry);
        $this->registerAuthoringModule($registry);
    }

    private function registerLayoutBuilderModule(AIOrchestratorModuleRegistry $registry): void
    {
        if (! CapellCore::isPackageInstalled('capell-app/layout-builder')) {
            return;
        }

        if (array_key_exists('layout-builder', $registry->modules())) {
            return;
        }

        $registry->register(new LayoutBuilderAIOrchestratorModule);
    }

    private function registerAuthoringModule(AIOrchestratorModuleRegistry $registry): void
    {
        if (array_key_exists('ai-authoring', $registry->modules())) {
            return;
        }

        $registry->register(new AIAuthoringModule);
    }
}
