<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/ai-orchestrator', __DIR__ . '/../../database/migrations', [
                '2026_05_10_190870_02_create_ai_generation_histories_table' => 'ai_generation_histories',
                '2026_07_10_000002_create_ai_generation_requests_table' => 'ai_generation_requests',
                '2026_07_20_000001_create_ai_managed_provider_calls_table' => 'ai_managed_provider_calls',
            ]);
        });
    }
}
