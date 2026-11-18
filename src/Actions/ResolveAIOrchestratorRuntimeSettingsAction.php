<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions;

use Capell\AIOrchestrator\Data\AIOrchestratorRuntimeSettingsData;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Ai\BrandVoice;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class ResolveAIOrchestratorRuntimeSettingsAction
{
    use AsFake;
    use AsObject;

    public function handle(?AIOrchestratorSettings $settings = null): AIOrchestratorRuntimeSettingsData
    {
        $provider = $this->arrayConfig('capell-ai-orchestrator.prism');
        $prompts = $this->arrayConfig('capell-ai-orchestrator.prompts');
        $rateLimiting = $this->arrayConfig('capell-ai-orchestrator.rate_limiting');

        $saved = [];

        try {
            $settings ??= resolve(AIOrchestratorSettings::class);
            $saved = is_array($settings->prompts) ? $settings->prompts : [];
            $provider['provider'] = $settings->ai_provider ?: ($provider['provider'] ?? 'openai');
            $provider['model'] = $this->stringValue($saved['model'] ?? $settings->ai_model ?? $provider['model'] ?? null, 'gpt-4o');
            $provider['api_key'] = $settings->ai_api_key;
            $rateLimiting['requests_per_minute'] = max(1, $this->intValue($saved['rate_limiting_requests_per_minute'] ?? $rateLimiting['requests_per_minute'] ?? null, 60));
            $provider['allowed_models'] = $this->modelList($saved['allowed_models'] ?? $provider['allowed_models'] ?? []);
            $rateLimiting['enabled'] = true;
            $provider['redact_personal_data'] = (bool) ($saved['redact_personal_data'] ?? $provider['redact_personal_data'] ?? true);
            $provider['restore_redacted_output'] = (bool) ($saved['restore_redacted_output'] ?? $provider['restore_redacted_output'] ?? false);

            foreach (['title_generation', 'meta_description', 'content_generation', 'rewrite_content'] as $key) {
                $base = is_array($prompts[$key] ?? null) ? $prompts[$key] : [];
                $prompts[$key] = [...$base,
                    'enabled' => (bool) ($saved[$key] ?? true),
                    'system' => $this->stringValue($saved[$key . '_system'] ?? $base['system'] ?? null),
                    'user_template' => $this->stringValue($saved[$key . '_user_template'] ?? $base['user_template'] ?? null),
                ];
            }
        } catch (Throwable) {
        }

        return new AIOrchestratorRuntimeSettingsData($provider, $prompts, $rateLimiting, BrandVoice::fromSettings($saved));
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

    /** @return list<string> */
    private function modelList(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_unique(array_filter(array_map(static fn (mixed $model): string => is_string($model) ? trim($model) : '', $value), static fn (string $model): bool => $model !== '')))
            : [];
    }

    private function intValue(mixed $value, int $default): int
    {
        return is_int($value) ? $value : $default;
    }
}
