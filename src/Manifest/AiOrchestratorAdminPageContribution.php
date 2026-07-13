<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class AiOrchestratorAdminPageContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
