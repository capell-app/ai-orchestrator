<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

class PromptRepository
{
    /**
     * @param  array<array-key, mixed>  $prompts
     */
    public function __construct(private array $prompts = []) {}

    /**
     * @return array<array-key, mixed>
     */
    public function get(string $key): ?array
    {
        return $this->prompts[$key] ?? null;
    }
}
