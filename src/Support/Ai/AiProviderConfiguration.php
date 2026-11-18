<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Data\AIOrchestratorRuntimeSettingsData;
use Capell\AIOrchestrator\Enums\AiProviderProblem;

/**
 * Says whether the configured AI provider can be called, and if not, why.
 */
final readonly class AiProviderConfiguration
{
    /** Providers that run without an API key. */
    private const array KEYLESS_PROVIDERS = ['ollama'];

    /** Model name prefixes that belong to one provider. */
    private const array MODEL_PREFIXES = [
        'claude' => 'anthropic',
        'gpt-' => 'openai',
        'o1' => 'openai',
        'o3' => 'openai',
        'o4' => 'openai',
        'gemini' => 'gemini',
    ];

    public function __construct(private AIOrchestratorRuntimeSettingsData $settings) {}

    /** @return list<AiProviderProblem> */
    public function problems(): array
    {
        $provider = strtolower($this->string('provider', 'openai'));
        $model = $this->string('model');
        $problems = [];

        if (! in_array($provider, self::KEYLESS_PROVIDERS, true) && trim($this->string('api_key')) === '') {
            $problems[] = AiProviderProblem::MissingKey;
        }

        $allowed = $this->allowedModels();

        if ($model === '' || ($allowed !== [] && ! in_array($model, $allowed, true))) {
            $problems[] = AiProviderProblem::UnknownModel;
        }

        foreach (self::MODEL_PREFIXES as $prefix => $owner) {
            if ($model !== '' && str_starts_with(strtolower($model), $prefix) && $owner !== $provider) {
                $problems[] = AiProviderProblem::ProviderMismatch;

                break;
            }
        }

        return $problems;
    }

    public function isUsable(): bool
    {
        return $this->problems() === [];
    }

    /** @return list<string> */
    public function allowedModels(): array
    {
        $allowed = $this->settings->provider['allowed_models'] ?? [];

        return is_array($allowed)
            ? array_values(array_filter($allowed, static fn (mixed $model): bool => is_string($model) && $model !== ''))
            : [];
    }

    public function allows(string $model): bool
    {
        $allowed = $this->allowedModels();

        return $allowed === [] || in_array($model, $allowed, true);
    }

    private function string(string $key, string $default = ''): string
    {
        $value = $this->settings->provider[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
