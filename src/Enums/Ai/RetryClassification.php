<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum RetryClassification: string
{
    case NotApplicable = 'not_applicable';
    case Retryable = 'retryable';
    case Terminal = 'terminal';
}
