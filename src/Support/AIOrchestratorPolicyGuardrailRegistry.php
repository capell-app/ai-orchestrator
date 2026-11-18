<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support;

use Capell\AIOrchestrator\Contracts\AIOrchestratorPolicyGuardrail;
use Capell\AIOrchestrator\Contracts\AIProviderCallGuard;
use InvalidArgumentException;

class AIOrchestratorPolicyGuardrailRegistry
{
    /** @var array<string, AIOrchestratorPolicyGuardrail> */
    private array $guardrails = [];

    /** @var array<string, AIProviderCallGuard> */
    private array $providerCallGuards = [];

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

    public function registerProviderCallGuard(string $key, AIProviderCallGuard $guard): void
    {
        if (array_key_exists($key, $this->providerCallGuards)) {
            throw new InvalidArgumentException(sprintf('AIOrchestrator provider call guard [%s] is already registered.', $key));
        }

        $this->providerCallGuards[$key] = $guard;
    }

    /**
     * @return array<string, AIProviderCallGuard>
     */
    public function providerCallGuards(): array
    {
        return $this->providerCallGuards;
    }
}
