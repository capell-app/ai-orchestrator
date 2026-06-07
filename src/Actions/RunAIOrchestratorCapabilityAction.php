<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

class RunAIOrchestratorCapabilityAction
{
    use AsObject;

    public function handle(AIOrchestratorRunData $run): mixed
    {
        $capability = resolve(AIOrchestratorModuleRegistry::class)
            ->capability($run->moduleKey, $run->capabilityKey);

        $this->ensureActionIsRunnable($run, $capability);

        return $capability->actionClass::run($run);
    }

    private function ensureActionIsRunnable(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): void
    {
        throw_if(
            ! class_exists($capability->actionClass) || ! method_exists($capability->actionClass, 'run'),
            RuntimeException::class,
            sprintf(
                'AIOrchestrator capability [%s:%s] action [%s] is not runnable.',
                $run->moduleKey,
                $run->capabilityKey,
                $capability->actionClass,
            ),
        );
    }
}
