<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Health;

use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\AIOrchestrator\Integrations\LayoutBuilder\PreviewLayoutBuilderLayoutPlanAction;
use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Support\AIOrchestratorModuleRegistry;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

final class AiOrchestratorHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->packageInstalledCheck(),
            $check->registryBindingCheck(),
            $check->moduleRegistryCheck(),
            $check->layoutBuilderModuleCheck(),
            $check->duplicateCapabilityProtectionCheck(),
            $check->runnableCapabilityActionsCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function packageInstalledCheck(): DoctorCheckResultData
    {
        $installed = CapellCore::isPackageInstalled(AIOrchestratorServiceProvider::$packageName);

        return new DoctorCheckResultData(
            label: $this->translation('capell-ai-orchestrator::package.health_package_installed_label'),
            passed: $installed,
            message: $installed
                ? $this->translation('capell-ai-orchestrator::package.health_package_installed_passed')
                : $this->translation('capell-ai-orchestrator::package.health_package_installed_failed'),
            remediation: $installed
                ? null
                : $this->translation('capell-ai-orchestrator::package.health_package_installed_remediation'),
        );
    }

    public function registryBindingCheck(): DoctorCheckResultData
    {
        $bound = $this->registryBindingIsHealthy();

        return new DoctorCheckResultData(
            label: $this->translation('capell-ai-orchestrator::package.health_registry_binding_label'),
            passed: $bound,
            message: $bound
                ? $this->translation('capell-ai-orchestrator::package.health_registry_binding_passed')
                : $this->translation('capell-ai-orchestrator::package.health_registry_binding_failed'),
            remediation: $bound
                ? null
                : $this->translation('capell-ai-orchestrator::package.health_registry_binding_remediation'),
        );
    }

    public function moduleRegistryCheck(): DoctorCheckResultData
    {
        $moduleCount = $this->moduleCount();

        return new DoctorCheckResultData(
            label: $this->translation('capell-ai-orchestrator::package.health_module_registry_label'),
            passed: $moduleCount > 0,
            message: $moduleCount > 0
                ? $this->translation('capell-ai-orchestrator::package.health_module_registry_passed', ['count' => (string) $moduleCount])
                : $this->translation('capell-ai-orchestrator::package.health_module_registry_failed'),
            remediation: $moduleCount > 0
                ? null
                : $this->translation('capell-ai-orchestrator::package.health_module_registry_remediation'),
        );
    }

    public function layoutBuilderModuleCheck(): DoctorCheckResultData
    {
        $available = $this->layoutBuilderModuleIsAvailable();

        return new DoctorCheckResultData(
            label: $this->translation('capell-ai-orchestrator::package.health_layout_builder_module_label'),
            passed: $available,
            message: $available
                ? $this->translation('capell-ai-orchestrator::package.health_layout_builder_module_passed')
                : $this->translation('capell-ai-orchestrator::package.health_layout_builder_module_failed'),
            remediation: $available
                ? null
                : $this->translation('capell-ai-orchestrator::package.health_layout_builder_module_remediation'),
        );
    }

    public function duplicateCapabilityProtectionCheck(): DoctorCheckResultData
    {
        $protected = $this->duplicateCapabilityProtectionIsHealthy();

        return new DoctorCheckResultData(
            label: $this->translation('capell-ai-orchestrator::package.health_duplicate_capability_label'),
            passed: $protected,
            message: $protected
                ? $this->translation('capell-ai-orchestrator::package.health_duplicate_capability_passed')
                : $this->translation('capell-ai-orchestrator::package.health_duplicate_capability_failed'),
            remediation: $protected
                ? null
                : $this->translation('capell-ai-orchestrator::package.health_duplicate_capability_remediation'),
        );
    }

    public function runnableCapabilityActionsCheck(): DoctorCheckResultData
    {
        $notRunnableCapabilities = $this->notRunnableCapabilities();

        return new DoctorCheckResultData(
            label: $this->translation('capell-ai-orchestrator::package.health_runnable_actions_label'),
            passed: $notRunnableCapabilities === [],
            message: $notRunnableCapabilities === []
                ? $this->translation('capell-ai-orchestrator::package.health_runnable_actions_passed')
                : $this->translation('capell-ai-orchestrator::package.health_runnable_actions_failed', ['capabilities' => implode(', ', $notRunnableCapabilities)]),
            remediation: $notRunnableCapabilities === []
                ? null
                : $this->translation('capell-ai-orchestrator::package.health_runnable_actions_remediation'),
        );
    }

    public function registryBindingIsHealthy(): bool
    {
        if (! app()->bound(AIOrchestratorModuleRegistry::class)) {
            return false;
        }

        try {
            return app()->make(AIOrchestratorModuleRegistry::class) instanceof AIOrchestratorModuleRegistry;
        } catch (Throwable) {
            return false;
        }
    }

    public function moduleCount(): int
    {
        try {
            return count(app()->make(AIOrchestratorModuleRegistry::class)->modules());
        } catch (Throwable) {
            return 0;
        }
    }

    public function layoutBuilderModuleIsAvailable(): bool
    {
        if (! CapellCore::isPackageInstalled('capell-app/layout-builder')) {
            return true;
        }

        try {
            return array_key_exists('layout-builder', app()->make(AIOrchestratorModuleRegistry::class)->modules());
        } catch (Throwable) {
            return false;
        }
    }

    public function duplicateCapabilityProtectionIsHealthy(): bool
    {
        try {
            (new AIOrchestratorModuleRegistry)->register(new class implements AIOrchestratorModule
            {
                public function key(): string
                {
                    return 'duplicate-capability-health';
                }

                public function label(): string
                {
                    return 'Duplicate capability health';
                }

                /**
                 * @return array<int, AIOrchestratorCapabilityData>
                 */
                public function capabilities(): array
                {
                    return [
                        new AIOrchestratorCapabilityData(
                            key: 'duplicate',
                            label: 'Duplicate',
                            description: 'First duplicate capability.',
                            actionClass: PreviewLayoutBuilderLayoutPlanAction::class,
                            approvalLevel: AIOrchestratorApprovalLevel::Draft,
                        ),
                        new AIOrchestratorCapabilityData(
                            key: 'duplicate',
                            label: 'Duplicate',
                            description: 'Second duplicate capability.',
                            actionClass: PreviewLayoutBuilderLayoutPlanAction::class,
                            approvalLevel: AIOrchestratorApprovalLevel::Draft,
                        ),
                    ];
                }
            });
        } catch (InvalidArgumentException) {
            return true;
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function notRunnableCapabilities(): array
    {
        try {
            $registry = app()->make(AIOrchestratorModuleRegistry::class);
        } catch (Throwable) {
            return [
                $this->translation('capell-ai-orchestrator::package.health_runnable_actions_registry_missing'),
            ];
        }

        if (! $registry instanceof AIOrchestratorModuleRegistry) {
            return [
                $this->translation('capell-ai-orchestrator::package.health_runnable_actions_registry_missing'),
            ];
        }

        $notRunnableCapabilities = [];

        foreach ($registry->modules() as $module) {
            foreach ($module->capabilities() as $capability) {
                if (class_exists($capability->actionClass) && method_exists($capability->actionClass, 'run')) {
                    continue;
                }

                $notRunnableCapabilities[] = sprintf('%s:%s', $module->key(), $capability->key);
            }
        }

        return $notRunnableCapabilities;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function translation(string $key, array $replace = []): string
    {
        $translation = resolve(Translator::class)->get($key, $replace);

        return is_string($translation) ? $translation : $key;
    }
}
