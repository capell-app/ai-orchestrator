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
            self::Title => __('capell-ai-orchestrator::package.ai_assistant_field_title'),
            self::Content => __('capell-ai-orchestrator::package.ai_assistant_field_content'),
            self::Meta => __('capell-ai-orchestrator::package.ai_assistant_field_meta'),
        };
    }
}
