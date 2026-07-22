<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class ProviderCallAllowanceData extends CanonicalContract
{
    public function __construct(public int $requestCount, public int $inputTokens, public int $outputTokens)
    {
        self::nonNegative($requestCount, 'request_count');
        self::nonNegative($inputTokens, 'input_tokens');
        self::nonNegative($outputTokens, 'output_tokens');
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(self::integer($payload, 'request_count'), self::integer($payload, 'input_tokens'), self::integer($payload, 'output_tokens'));
    }

    /** @return array<string, int> */
    public function toCanonicalArray(): array
    {
        return ['input_tokens' => $this->inputTokens, 'output_tokens' => $this->outputTokens, 'request_count' => $this->requestCount, 'schema_version' => $this->schemaVersion()];
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        if (! isset($payload[$key]) || ! is_int($payload[$key])) {
            throw new InvalidArgumentException("{$key} must be an integer.");
        }

        return $payload[$key];
    }
}
