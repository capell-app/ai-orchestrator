<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum ManagedProviderClaimStatus: string
{
    case Acquired = 'acquired';
    case Replayed = 'replayed';
    case InProgress = 'in_progress';
    case IdentityMismatch = 'identity_mismatch';
    case RecoveryExpired = 'recovery_expired';
}
