<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Events\Ai;

use Throwable;

class AiGenerationFailed
{
    public function __construct(public string $actionClass, public Throwable $exception) {}
}
