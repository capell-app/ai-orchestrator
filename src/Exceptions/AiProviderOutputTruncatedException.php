<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Exceptions;

use RuntimeException;

/**
 * The provider stopped at the output-token limit, so a structured response is incomplete.
 * Asking again would pay for the same truncation, so callers must not retry it.
 */
final class AiProviderOutputTruncatedException extends RuntimeException
{
    public function __construct(public readonly string $model)
    {
        parent::__construct('The provider stopped at the output-token limit before the structured response was complete.');
    }
}
