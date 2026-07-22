<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum ProviderAttemptOutcome: string
{
    /** Durable handoff intent: the provider may or may not have received the request, so takeover must not redispatch. */
    case HandoffStarted = 'handoff_started';
    case Reported = 'reported';
    case FailedBeforeUsage = 'failed_before_usage';
    case OutcomeUnknown = 'outcome_unknown';
    case InvalidUsage = 'invalid_usage';
}
