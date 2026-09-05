<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('ai-orchestrator.ai_api_key')) {
            return;
        }

        // Idempotent: a value that already decrypts cleanly is left untouched,
        // so running this twice (or against a row already migrated by a prior
        // deploy) is a no-op rather than a double-encrypt that would then fail
        // to decrypt at read time. Only null is safe to skip -- Crypto::decrypt()
        // in the settings mapper's read path only bypasses decrypt() for null,
        // so an empty string (the default this setting was created with) must
        // still be encrypted or every fresh install with no configured key
        // would throw a DecryptException the first time settings are loaded.
        $this->migrator->update('ai-orchestrator.ai_api_key', function ($payload) {
            if ($payload === null) {
                return $payload;
            }

            try {
                Crypt::decrypt($payload);

                return $payload;
            } catch (DecryptException) {
                return encrypt($payload);
            }
        });
    }
};
