<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures\Autoload;

use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;

final class DuplicateCapabilityAIOrchestratorModuleFixture implements AIOrchestratorModule
{
    public function key(): string
    {
        return 'duplicate-capability-module';
    }

    public function label(): string
    {
        return 'Duplicate capability module';
    }

    public function capabilities(): array
    {
        return [
            new AIOrchestratorCapabilityData(
                key: 'duplicate-capability',
                label: 'First duplicate capability',
                description: 'First duplicate capability.',
                actionClass: AIOrchestratorRunActionFixture::class,
            ),
            new AIOrchestratorCapabilityData(
                key: 'duplicate-capability',
                label: 'Second duplicate capability',
                description: 'Second duplicate capability.',
                actionClass: AIOrchestratorRunActionFixture::class,
            ),
        ];
    }
}
