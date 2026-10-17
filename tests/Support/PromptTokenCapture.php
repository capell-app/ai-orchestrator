<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Support;

final class PromptTokenCapture
{
    /** @var list<array{role: string, content: string}> */
    public array $messages = [];
}
