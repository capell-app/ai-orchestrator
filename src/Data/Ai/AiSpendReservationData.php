<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Spatie\LaravelData\Data;

final class AiSpendReservationData extends Data
{
    public function __construct(
        public string $id,
        public ?int $siteId,
        public string $model,
        public int $estimatedCostMicros,
    ) {}
}
