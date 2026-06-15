<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorRunStatus;
use Capell\AIOrchestrator\Events\AIOrchestratorCapabilityRunRecorded;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
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

        $this->ensureActionIsAuthorized($run, $capability);
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

    private function ensureActionIsAuthorized(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): void
    {
        if ($capability->requiredAbility === null || $capability->requiredAbility === '') {
            return;
        }

        throw_unless(
            $run->actor !== null,
            AuthorizationException::class,
            sprintf(
                'AIOrchestrator capability [%s:%s] requires ability [%s] but no actor was provided.',
                $run->moduleKey,
                $run->capabilityKey,
                $capability->requiredAbility,
            ),
        );

        throw_unless(
            Gate::forUser($run->actor)->allows($capability->requiredAbility, [$run, $capability]),
            AuthorizationException::class,
            sprintf(
                'AIOrchestrator capability [%s:%s] is not authorized for ability [%s].',
                $run->moduleKey,
                $run->capabilityKey,
                $capability->requiredAbility,
            ),
        );
    }
}
