<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Enums\AIOrchestratorRunStatus;
use Capell\AIOrchestrator\Events\AIOrchestratorCapabilityRunRecorded;
use Capell\AIOrchestrator\Exceptions\AIOrchestratorPolicyGuardrailException;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\AIOrchestrator\Support\AIOrchestratorPolicyGuardrailRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use Throwable;

class RunAIOrchestratorCapabilityAction
{
    use AsFake;
    use AsObject;

    public function handle(AIOrchestratorRunData $run): mixed
    {
        $capability = resolve(AIOrchestratorModuleRegistry::class)
            ->capability($run->moduleKey, $run->capabilityKey);

        $this->ensureActionIsAuthorized($run, $capability);
        $this->ensurePolicyGuardrailsAllow($run, $capability);
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

        if ($run->actor === null && $run->internal) {
            return;
        }

        throw_unless(
            $run->actor !== null,
            AuthorizationException::class,
            (string) __('capell-ai-orchestrator::package.capability_requires_user', ['capability' => $capability->label]),
        );

        throw_unless(
            Gate::forUser($run->actor)->allows($capability->requiredAbility, [$run, $capability]),
            AuthorizationException::class,
            (string) __('capell-ai-orchestrator::package.capability_not_permitted', ['capability' => $capability->label]),
        );
    }

    private function ensurePolicyGuardrailsAllow(AIOrchestratorRunData $run, AIOrchestratorCapabilityData $capability): void
    {
        foreach (resolve(AIOrchestratorPolicyGuardrailRegistry::class)->guardrails() as $guardrail) {
            if ($guardrail->allows($run, $capability)) {
                continue;
            }

            throw new AIOrchestratorPolicyGuardrailException($guardrail->denialMessage($run, $capability));
        }
    }
}
