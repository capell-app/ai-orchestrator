<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelData\Data;

class AIOrchestratorRunData extends Data
{
    /**
     * `$internal` marks a trusted, system-initiated run with no acting user
     * (for example a scheduled scan). Only server-side code may set it; it
     * must never be derived from request input.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $moduleKey,
        public string $capabilityKey,
        public string $prompt,
        public array $context = [],
        public ?Authenticatable $actor = null,
        public bool $internal = false,
    ) {}
}
