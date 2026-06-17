<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Events;

use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorRunStatus;
use Throwable;

final class AIOrchestratorCapabilityRunRecorded
{
    public function __construct(
        public readonly AIOrchestratorRunData $run,
        public readonly AIOrchestratorCapabilityData $capability,
        public readonly AIOrchestratorRunStatus $status,
        public readonly mixed $result = null,
        public readonly ?Throwable $exception = null,
    ) {}
}
