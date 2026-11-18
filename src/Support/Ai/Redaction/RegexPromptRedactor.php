<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Redaction;

use Override;

final class RegexPromptRedactor implements PromptRedactor
{
    private const string EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    private const string PHONE = '/(?<![\w.])\+?\(?\d(?:[ .\-()]{0,2}\d){8,14}(?!\w)/';

    #[Override]
    public function redact(string ...$texts): RedactionResultData
    {
        /** @var array<string, string> $placeholders original => placeholder */
        $placeholders = [];
        $counts = ['email' => 0, 'phone' => 0];

        $mask = function (string $kind, string $value) use (&$placeholders, &$counts): string {
            $key = $kind . ':' . mb_strtolower($value);

            if (! isset($placeholders[$key])) {
                $counts[$kind]++;
                $placeholders[$key] = '[' . $kind . '-' . $counts[$kind] . ']';
            }

            return $placeholders[$key];
        };

        $redacted = [];
        $replacements = [];

        foreach ($texts as $text) {
            $text = (string) preg_replace_callback(self::EMAIL, function (array $match) use ($mask, &$replacements): string {
                $placeholder = $mask('email', $match[0]);
                $replacements[$placeholder] ??= $match[0];

                return $placeholder;
            }, $text);

            $redacted[] = (string) preg_replace_callback(self::PHONE, function (array $match) use ($mask, &$replacements): string {
                // Only number-like runs with enough digits are phone numbers; dates and short numbers stay.
                if (strlen((string) preg_replace('/\D/', '', $match[0])) < 9 || $this->isYearList($match[0])) {
                    return $match[0];
                }

                $placeholder = $mask('phone', $match[0]);
                $replacements[$placeholder] ??= $match[0];

                return $placeholder;
            }, $text);
        }

        return new RedactionResultData($redacted, $replacements);
    }

    /** A run made only of four-digit years, such as "2023 2024 2025", is not a phone number. */
    private function isYearList(string $run): bool
    {
        $groups = preg_split('/\D+/', $run, -1, PREG_SPLIT_NO_EMPTY);

        return $groups !== false && $groups !== []
            && array_all($groups, static fn (string $group): bool => preg_match('/^(19|20)\d{2}$/', $group) === 1);
    }
}
