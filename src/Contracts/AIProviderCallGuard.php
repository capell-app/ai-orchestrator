<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Contracts;

/**
 * Checked immediately before a request leaves for the AI provider, for every caller,
 * including those that do not go through a registered capability.
 */
interface AIProviderCallGuard
{
    /**
     * Returns a message explaining why the call must not go ahead, or null to allow it.
     */
    public function denialFor(string $model, int|string|null $userId): ?string;
}
