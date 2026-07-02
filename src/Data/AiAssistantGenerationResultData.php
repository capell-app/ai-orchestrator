<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data;

use Spatie\LaravelData\Data;

/**
 * Outcome of an AI Assistant generation pass: the raw output per generated
 * field, plus the per-field failure messages for capabilities that threw.
 * Failures are returned rather than surfaced as side effects so the caller
 * (the Filament extender) owns how they are presented to the user.
 */
class AiAssistantGenerationResultData extends Data
{
    /**
     * @param  array<string, mixed>  $generated  Raw capability output keyed by short field key.
     * @param  array<string, string>  $failures  Failure message keyed by short field key.
     */
    public function __construct(
        public array $generated = [],
        public array $failures = [],
    ) {}
}
