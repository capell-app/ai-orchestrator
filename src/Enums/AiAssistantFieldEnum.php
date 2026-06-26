<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiAssistantFieldEnum: string implements HasLabel
{
    case Title = 'title';
    case Content = 'content';
    case Meta = 'meta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Title => __('Title'),
            self::Content => __('Content'),
            self::Meta => __('Meta description'),
        };
    }
}
