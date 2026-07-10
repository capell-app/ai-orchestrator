<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums;

enum AiGenerationRequestStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
