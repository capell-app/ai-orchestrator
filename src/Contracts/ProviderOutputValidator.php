<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Contracts;

use Capell\AIOrchestrator\Data\Ai\ValidatedProviderOutputData;

interface ProviderOutputValidator
{
    public function policyIdentity(): string;

    public function policyDigest(): string;

    public function validate(string $outputIdentity, string $providerOutput): ?ValidatedProviderOutputData;
}
