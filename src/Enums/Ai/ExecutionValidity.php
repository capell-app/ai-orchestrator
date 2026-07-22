<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum ExecutionValidity: string
{
    case Validated = 'validated';
    case Invalid = 'invalid';
    case Refused = 'refused';
    case Failed = 'failed';
}
