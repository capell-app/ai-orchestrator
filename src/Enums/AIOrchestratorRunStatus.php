<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums;

enum AIOrchestratorRunStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
