<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Manifest;

use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;

final class AiOrchestratorPruneScheduleContribution implements RunsScheduledExtensionJob
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
