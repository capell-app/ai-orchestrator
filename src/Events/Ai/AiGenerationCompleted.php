<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Events\Ai;

class AiGenerationCompleted
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(public string $actionClass, public mixed $result, public array $metadata = []) {}
}
