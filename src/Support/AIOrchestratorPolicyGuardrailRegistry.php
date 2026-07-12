<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support;

use Capell\AIOrchestrator\Contracts\AIOrchestratorPolicyGuardrail;
use InvalidArgumentException;

class AIOrchestratorPolicyGuardrailRegistry
{
    /** @var array<string, AIOrchestratorPolicyGuardrail> */
    private array $guardrails = [];

    public function register(string $key, AIOrchestratorPolicyGuardrail $guardrail): void
    {
        if (array_key_exists($key, $this->guardrails)) {
            throw new InvalidArgumentException(sprintf('AIOrchestrator policy guardrail [%s] is already registered.', $key));
        }

        $this->guardrails[$key] = $guardrail;
    }

    /**
     * @return array<string, AIOrchestratorPolicyGuardrail>
     */
    public function guardrails(): array
    {
        return $this->guardrails;
    }
}
