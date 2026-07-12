<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures\Autoload;

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use RuntimeException;

final class FailingAIOrchestratorRunActionFixture
{
    public static function run(AIOrchestratorRunData $run): never
    {
        throw new RuntimeException('The AI Orchestrator fixture failed for ' . $run->capabilityKey . '.');
    }
}
