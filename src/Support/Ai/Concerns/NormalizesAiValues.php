<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Concerns;

/**
 * Narrows the loosely-typed values that flow through AI pipelines (config,
 * prompt templates, pipeline payloads) into the concrete scalar/array shapes
 * the downstream DTOs and providers require.
 */
trait NormalizesAiValues
{
    private function aiString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function aiInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function aiArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<int, array{role: string, content: string}>|null
     */
    private function aiMessages(mixed $messages): ?array
    {
        if (! is_array($messages)) {
            return null;
        }

        $normalized = [];
        foreach ($messages as $message) {
            if (is_array($message)) {
                $normalized[] = [
                    'role' => $this->aiString($message['role'] ?? ''),
                    'content' => $this->aiString($message['content'] ?? ''),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function aiParams(mixed $params): ?array
    {
        if (! is_array($params)) {
            return null;
        }

        $normalized = [];
        foreach ($params as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
