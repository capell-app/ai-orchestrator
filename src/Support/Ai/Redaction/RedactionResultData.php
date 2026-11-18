<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Redaction;

final readonly class RedactionResultData
{
    /**
     * @param  list<string>  $texts  The redacted texts, in the order they were given.
     * @param  array<string, string>  $replacements  Placeholder => original value.
     */
    public function __construct(
        public array $texts,
        public array $replacements = [],
    ) {}

    public function restore(string $text): string
    {
        return $this->replacements === [] ? $text : strtr($text, $this->replacements);
    }
}
