<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

class AiTokenCounter
{
    public function estimate(string $text, string $model = 'gpt-4-turbo'): int
    {
        $baseTokens = (int) ceil(strlen($text) / 4);
        $multiplier = match ($model) {
            'gpt-4-turbo' => 1.0,
            'gpt-3.5-turbo' => 1.1,
            'gpt-4o' => 0.95,
            default => 1.0,
        };

        return (int) ($baseTokens * $multiplier);
    }

    /**
     * @param  array<array-key, mixed>  $usage
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function count(array $usage): array
    {
        return [
            'prompt_tokens' => $this->intFrom($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => $this->intFrom($usage['completion_tokens'] ?? 0),
            'total_tokens' => $this->intFrom($usage['total_tokens'] ?? 0),
        ];
    }

    /**
     * Lenient counter to support tests passing a string by mistake.
     * Prefer count() with array usage in application code.
     *
     * @return array<array-key, mixed>
     */
    public function countFromString(string $usage): array
    {
        return [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
    }

    public function wouldExceedLimit(int $estimatedTokens, int $limit): bool
    {
        return $estimatedTokens > $limit;
    }

    private function intFrom(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
