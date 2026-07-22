<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums\Ai;

enum SettlementReplayStatus: string
{
    case Applied = 'applied';
    case Replayed = 'replayed';
    case Refused = 'refused';
}
