<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures;

use Capell\AIOrchestrator\Data\Ai\ManagedProviderCallClaimData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;

final class ManagedProviderOverlapState
{
    public ?ProviderCallAuthorizationData $authorization = null;

    public ?ManagedProviderCallClaimData $claim = null;
}
