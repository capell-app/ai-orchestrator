<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

class PromptRepository
{
    /**
     * @param  array<array-key, mixed>  $prompts
     */
    public function __construct(private array $prompts = [], private readonly BrandVoice $brandVoice = new BrandVoice) {}

    /**
     * @return array<array-key, mixed>
     */
    public function get(string $key): ?array
    {
        $prompt = $this->prompts[$key] ?? null;

        if (! is_array($prompt)) {
            return null;
        }

        // The brand voice goes into the system prompt of every prompt the orchestrator sends.
        if (is_string($prompt['system'] ?? null)) {
            $prompt['system'] = $this->brandVoice->applyTo($prompt['system']);
        }

        return $prompt;
    }
}
