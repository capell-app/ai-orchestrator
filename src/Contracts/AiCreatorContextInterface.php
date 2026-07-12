<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Contracts;

/**
 * Minimal read contract the AI-orchestrator generation DTOs need from a
 * Creator-supplied context. The concrete Creator data object lives in the
 * higher-level seo-suite package and implements this interface, so the moved
 * (lower-level) DTOs avoid an upward dependency back into seo-suite.
 */
interface AiCreatorContextInterface
{
    public function getSiteId(): int;

    public function getUserId(): int;
}
