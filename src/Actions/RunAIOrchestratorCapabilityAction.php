<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorRunStatus;
use Capell\AIOrchestrator\Events\AIOrchestratorCapabilityRunRecorded;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use Throwable;

class RunAIOrchestratorCapabilityAction
{
    use AsObject;

    public function handle(AIOrchestratorRunData $run): mixed
    {
        $capability = resolve(AIOrchestratorModuleRegistry::class)
            ->capability($run->moduleKey, $run->capabilityKey);

        $this->ensureActionIsRunnable($run, $capability);

        try {
            $result = $capability->actionClass::run($run);
        } catch (Throwable $exception) {
            event(new AIOrchestratorCapabilityRunRecorded(
                run: $run,
                capability: $capability,
                status: AIOrchestratorRunStatus::Failed,
                exception: $exception,
            ));

            throw $exception;
        }

        event(new AIOrchestratorCapabilityRunRecorded(
            run: $run,
            capability: $capability,
            status: AIOrchestratorRunStatus::Succeeded,
            result: $result,
        ));

        return $result;
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
