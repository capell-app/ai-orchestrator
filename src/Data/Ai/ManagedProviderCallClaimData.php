<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ManagedProviderClaimStatus;
use InvalidArgumentException;

final readonly class ManagedProviderCallClaimData
{
    /** @param list<ProviderAttemptEvidenceData> $attempts */
    public function __construct(public ManagedProviderClaimStatus $status, public ?string $leaseToken = null, public ?ProviderExecutionResultData $replayedResult = null, public array $attempts = [])
    {
        if (($status === ManagedProviderClaimStatus::Acquired) !== ($leaseToken !== null) || ($status === ManagedProviderClaimStatus::Replayed) !== ($replayedResult !== null)) {
            throw new InvalidArgumentException('Managed provider claim fields do not match its status.');
        }
        if ($status !== ManagedProviderClaimStatus::Acquired && $attempts !== []) {
            throw new InvalidArgumentException('Only an acquired claim may carry prior attempts.');
        }
    }
}
