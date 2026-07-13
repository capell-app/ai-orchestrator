<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AIOrchestratorRuntimeSettingsData;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class ResolveAIOrchestratorRuntimeSettingsAction
{
    use AsAction;

    public function handle(?AIOrchestratorSettings $settings = null): AIOrchestratorRuntimeSettingsData
    {
        $provider = $this->arrayConfig('capell-ai-orchestrator.prism');
        $prompts = $this->arrayConfig('capell-ai-orchestrator.prompts');
        $rateLimiting = $this->arrayConfig('capell-ai-orchestrator.rate_limiting');

        try {
            $settings ??= resolve(AIOrchestratorSettings::class);
            $saved = is_array($settings->prompts) ? $settings->prompts : [];
            $provider['provider'] = $settings->ai_provider ?: ($provider['provider'] ?? 'openai');
            $provider['model'] = $this->stringValue($saved['model'] ?? $settings->ai_model ?? $provider['model'] ?? null, 'gpt-4o');
            $provider['api_key'] = $settings->ai_api_key;
            $rateLimiting['requests_per_minute'] = max(1, $this->intValue($saved['rate_limiting_requests_per_minute'] ?? $rateLimiting['requests_per_minute'] ?? null, 60));
            $rateLimiting['enabled'] = true;

            foreach (['title_generation', 'meta_description', 'content_generation'] as $key) {
                $base = is_array($prompts[$key] ?? null) ? $prompts[$key] : [];
                $prompts[$key] = [...$base,
                    'enabled' => (bool) ($saved[$key] ?? true),
                    'system' => $this->stringValue($saved[$key . '_system'] ?? $base['system'] ?? null),
                    'user_template' => $this->stringValue($saved[$key . '_user_template'] ?? $base['user_template'] ?? null),
                ];
            }
        } catch (Throwable) {
        }

        return new AIOrchestratorRuntimeSettingsData($provider, $prompts, $rateLimiting);
    }

    /** @return array<string, mixed> */
    private function arrayConfig(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? $value : [];
    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }

    private function intValue(mixed $value, int $default): int
    {
        return is_int($value) ? $value : $default;
    }
}
