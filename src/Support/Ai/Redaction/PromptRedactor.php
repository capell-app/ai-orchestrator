<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Redaction;

/**
 * Masks personal data in prompt text before it is sent to the provider.
 */
interface PromptRedactor
{
    /**
     * Redact several texts together so one value gets the same placeholder in all of them.
     */
    public function redact(string ...$texts): RedactionResultData;
}
