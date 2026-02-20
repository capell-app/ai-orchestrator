<?php

declare(strict_types=1);

namespace Capell\Assistant\Filament\Widgets;

use Capell\Assistant\Models\AIGenerationHistory;
use Filament\Widgets\Widget;

class AiUsageWidget extends Widget
{
    protected string $view = 'capell-assistant::filament.widgets.ai-usage';

    protected function getViewData(): array
    {
        $count = AIGenerationHistory::query()->count();
        $tokens = AIGenerationHistory::query()->sum('total_tokens');

        return [
            'generationCount' => $count,
            'totalTokens' => $tokens,
        ];
    }
}
