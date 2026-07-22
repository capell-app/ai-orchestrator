<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use InvalidArgumentException;

final readonly class ProviderIdempotencyHeaderData
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('The provider idempotency header must be a SHA-256 digest.');
        }
    }
}
