<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Events\Ai;

class AiGenerationStarted
{
    /**
     * @param  array<array-key, mixed>  $args
     */
    public function __construct(public string $actionClass, public array $args) {}
}
