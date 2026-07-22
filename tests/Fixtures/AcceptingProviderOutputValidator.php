<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures;

use Capell\AIOrchestrator\Contracts\ProviderOutputValidator;
use Capell\AIOrchestrator\Data\Ai\ValidatedProviderOutputData;

final readonly class AcceptingProviderOutputValidator implements ProviderOutputValidator
{
    public function __construct(private ?string $requiredContent = null, private ?string $returnedIdentity = null, private string $policy = 'accepting:v1') {}

    public function policyIdentity(): string
    {
        return $this->policy;
    }

    public function policyDigest(): string
    {
        return hash('sha256', $this->policy . '|' . ($this->requiredContent ?? '*'));
    }

    public function validate(string $outputIdentity, string $providerOutput): ?ValidatedProviderOutputData
    {
        if ($providerOutput === '' || ($this->requiredContent !== null && $providerOutput !== $this->requiredContent)) {
            return null;
        }

        return ValidatedProviderOutputData::fromValidatedContent($this->returnedIdentity ?? $outputIdentity, $providerOutput);
    }
}
