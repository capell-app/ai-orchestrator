<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Override;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

final class AIOrchestratorSettingsRepository extends DatabaseSettingsRepository
{
    private bool $invalidApiKeyWarningLogged = false;

    /** @return array<string, mixed> */
    #[Override]
    public function getPropertiesInGroup(string $group): array
    {
        $properties = parent::getPropertiesInGroup($group);

        if ($group === AIOrchestratorSettings::group() && array_key_exists('ai_api_key', $properties)) {
            $properties['ai_api_key'] = $this->tolerateInvalidApiKeyPayload($properties['ai_api_key']);
        }

        return $properties;
    }

    #[Override]
    public function getPropertyPayload(string $group, string $name): mixed
    {
        $payload = parent::getPropertyPayload($group, $name);

        if ($group !== AIOrchestratorSettings::group() || $name !== 'ai_api_key') {
            return $payload;
        }

        return $this->tolerateInvalidApiKeyPayload($payload);
    }

    private function tolerateInvalidApiKeyPayload(mixed $payload): string
    {
        if (is_string($payload) && $payload !== '') {
            try {
                Crypt::decrypt($payload);

                return $payload;
            } catch (DecryptException) {
                // An optional package may have stored this before encryption was enabled.
            }
        }

        if (! $this->invalidApiKeyWarningLogged) {
            Log::warning('AI Orchestrator API key setting could not be decrypted; treating it as unset.', [
                'group' => AIOrchestratorSettings::group(),
                'property' => 'ai_api_key',
            ]);
            $this->invalidApiKeyWarningLogged = true;
        }

        return Crypt::encrypt('');
    }
}
