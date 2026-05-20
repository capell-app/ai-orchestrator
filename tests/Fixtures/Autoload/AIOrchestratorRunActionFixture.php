<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures\Autoload;

use Capell\AIOrchestrator\Data\AIOrchestratorRunData;

class AIOrchestratorRunActionFixture
{
    /**
     * @return array<string, string>
     */
    public static function run(AIOrchestratorRunData $run): array
    {
        return [
            'prompt' => $run->prompt,
            'page' => (string) $run->context['page'],
        ];
    }
}
