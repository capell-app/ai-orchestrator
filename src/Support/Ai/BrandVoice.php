<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

/**
 * The site's editorial voice, written once in the settings and added to every prompt the
 * orchestrator sends, so generated copy sounds like the site rather than like the model.
 */
final readonly class BrandVoice
{
    /**
     * @param  array<string, string>  $glossary  Term to avoid => preferred term.
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public string $tone = '',
        public array $glossary = [],
        public array $bannedWords = [],
        public string $examples = '',
    ) {}

    /**
     * @param  array<array-key, mixed>  $saved  The brand_voice_* entries of the saved prompt settings.
     */
    public static function fromSettings(array $saved): self
    {
        $glossary = [];
        $rawGlossary = $saved['brand_voice_glossary'] ?? [];

        foreach (is_array($rawGlossary) ? $rawGlossary : [] as $term => $preferred) {
            if (is_string($term) && trim($term) !== '' && is_string($preferred) && trim($preferred) !== '') {
                $glossary[trim($term)] = trim($preferred);
            }
        }

        $rawBanned = $saved['brand_voice_banned_words'] ?? [];
        $banned = is_array($rawBanned)
            ? array_values(array_unique(array_filter(array_map(static fn (mixed $word): string => is_string($word) ? trim($word) : '', $rawBanned), static fn (string $word): bool => $word !== '')))
            : [];

        return new self(
            tone: is_string($saved['brand_voice_tone'] ?? null) ? trim($saved['brand_voice_tone']) : '',
            glossary: $glossary,
            bannedWords: $banned,
            examples: is_string($saved['brand_voice_examples'] ?? null) ? trim($saved['brand_voice_examples']) : '',
        );
    }

    public function isEmpty(): bool
    {
        return $this->tone === '' && $this->glossary === [] && $this->bannedWords === [] && $this->examples === '';
    }

    /**
     * Add the voice to a system prompt. A prompt with no voice configured is returned unchanged.
     */
    public function applyTo(string $systemPrompt): string
    {
        if ($this->isEmpty()) {
            return $systemPrompt;
        }

        $lines = ['Brand voice for this site. Follow it in everything you write:'];

        if ($this->tone !== '') {
            $lines[] = 'Tone: ' . $this->tone;
        }

        foreach ($this->glossary as $term => $preferred) {
            $lines[] = 'Write "' . $preferred . '" instead of "' . $term . '".';
        }

        if ($this->bannedWords !== []) {
            $lines[] = 'Never use these words or phrases: ' . implode(', ', $this->bannedWords) . '.';
        }

        if ($this->examples !== '') {
            $lines[] = "Examples of the voice:\n" . $this->examples;
        }

        return mb_rtrim($systemPrompt) . "\n\n" . implode("\n", $lines);
    }
}
