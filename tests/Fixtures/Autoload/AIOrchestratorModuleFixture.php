<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Tests\Fixtures\Autoload;

use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use RuntimeException;

final class AIOrchestratorModuleFixture implements AIOrchestratorModule
{
    public function __construct(
        private readonly string $moduleKey = 'test-module',
        private readonly string $capabilityKey = 'test-capability',
        private readonly string $actionClass = AIOrchestratorRunActionFixture::class,
        private readonly ?string $requiredAbility = null,
    ) {}

    public function key(): string
    {
        return $this->moduleKey;
    }

    public function label(): string
    {
        return 'Test module';
    }

    public function capabilities(): array
    {
        throw_unless(class_exists($this->actionClass), RuntimeException::class, 'The configured AI Orchestrator action fixture does not exist.');

        return [
            new AIOrchestratorCapabilityData(
                key: $this->capabilityKey,
                label: 'Test capability',
                description: 'Create a test AI Orchestrator result.',
                actionClass: $this->actionClass,
                approvalLevel: AIOrchestratorApprovalLevel::Draft,
                requiredAbility: $this->requiredAbility,
            ),
        ];
    }
}
