<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Console\Commands;

use Capell\AIOrchestrator\Actions\Ai\PruneAiGenerationPayloadsAction;
use Illuminate\Console\Command;

final class PruneAiGenerationPayloadsCommand extends Command
{
    protected $signature = 'capell:ai-orchestrator:prune-generation-payloads {--days= : Retention window in days}';

    protected $description = 'Remove expired encrypted AI generation payloads while retaining aggregate audit metrics.';

    public function handle(): int
    {
        $days = $this->option('days');
        $retentionDays = is_numeric($days) ? (int) $days : null;
        $pruned = PruneAiGenerationPayloadsAction::run($retentionDays);

        $this->components->info((string) __('capell-ai-orchestrator::package.prune_generation_payloads', ['count' => $pruned]));

        return self::SUCCESS;
    }
}
