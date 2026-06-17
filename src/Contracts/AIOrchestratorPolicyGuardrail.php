<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Contracts;

use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;

interface AIOrchestratorPolicyGuardrail
{
    public function allows(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): bool;

    public function denialMessage(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): string;
}
