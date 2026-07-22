<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum ManagedProviderCallState: string
{
    case Running = 'running';
    case Terminal = 'terminal';
}
