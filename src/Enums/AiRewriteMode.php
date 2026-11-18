<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums;

enum AiRewriteMode: string
{
    case Shorten = 'shorten';
    case Expand = 'expand';
    case Tone = 'tone';
    case Fix = 'fix';

    public function instruction(): string
    {
        return match ($this) {
            self::Shorten => 'Make the text shorter while keeping every key point.',
            self::Expand => 'Make the text fuller with more useful detail, without inventing facts.',
            self::Tone => 'Rewrite the text in the site brand voice.',
            self::Fix => 'Fix spelling, grammar and punctuation only.',
        };
    }
}
