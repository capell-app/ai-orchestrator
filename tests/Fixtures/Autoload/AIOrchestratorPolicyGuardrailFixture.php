<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures\Autoload;

use Capell\AIOrchestrator\Contracts\AIOrchestratorPolicyGuardrail;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;

final class AIOrchestratorPolicyGuardrailFixture implements AIOrchestratorPolicyGuardrail
{
    public function allows(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): bool
    {
        return (bool) ($run->context['guardrail_allowed'] ?? true);
    }

    public function denialMessage(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): string
    {
        return sprintf('Policy guardrail denied [%s:%s].', $run->moduleKey, $capability->key);
    }
}
