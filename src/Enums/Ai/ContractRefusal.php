<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum ContractRefusal: string
{
    case None = 'none';
    case IdentityMismatch = 'identity_mismatch';
    case StaleFence = 'stale_fence';
    case DeadlineExceeded = 'deadline_exceeded';
    case AllowanceExceeded = 'allowance_exceeded';
    case MissingUsage = 'missing_usage';
    case InvalidUsage = 'invalid_usage';
    case InvalidOutput = 'invalid_output';
    case SettlementMismatch = 'settlement_mismatch';
    case ProviderError = 'provider_error';
    case InProgress = 'in_progress';
    case RecoveryExpired = 'recovery_expired';
    case RetryExhausted = 'retry_exhausted';
    case ProviderOutcomeUnknown = 'provider_outcome_unknown';
}
