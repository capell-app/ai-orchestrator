<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Contracts;

interface VersionedContract
{
    public function schemaVersion(): int;

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array;

    public function canonicalJson(): string;

    public function digest(): string;
}
