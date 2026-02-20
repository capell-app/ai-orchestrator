<?php

declare(strict_types=1);

namespace Capell\Assistant\Events;

class AiGenerationStarted
{
    public function __construct(public string $actionClass, public array $args) {}
}
