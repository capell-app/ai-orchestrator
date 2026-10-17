<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Admin\AiAssistantPageResourceExtender;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Spatie\LaravelSettings\Models\SettingsProperty;

it('encrypts a legacy plaintext api key in place, keeping it readable', function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
        dirname(__DIR__, 3) . '/database/settings',
    );

    /** @var SettingsMigrator $settingsMigrator */
    $settingsMigrator = resolve(SettingsMigrator::class);

    // Seed a plaintext row directly, matching how every ai_api_key row was
    // written before this migration existed (add() persists the raw value,
    // with no encryption).
    $settingsMigrator->deleteIfExists('ai-orchestrator.ai_api_key');
    $settingsMigrator->add('ai-orchestrator.ai_api_key', 'sk-legacy-plaintext-key');

    $plaintextPayload = SettingsProperty::get('ai-orchestrator.ai_api_key');

    $migration = require __DIR__ . '/../../../database/settings/2026_09_04_213000_01_encrypt_ai_orchestrator_api_key.php';
    $migration->up();

    $encryptedPayload = SettingsProperty::get('ai-orchestrator.ai_api_key');

    expect($encryptedPayload)->not->toBe($plaintextPayload)
        ->and(resolve(AIOrchestratorSettings::class)->ai_api_key)->toBe('sk-legacy-plaintext-key');
});

it('is idempotent -- running the migration twice does not corrupt an already-encrypted value', function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
        dirname(__DIR__, 3) . '/database/settings',
    );

    /** @var SettingsMigrator $settingsMigrator */
    $settingsMigrator = resolve(SettingsMigrator::class);

    $settingsMigrator->deleteIfExists('ai-orchestrator.ai_api_key');
    $settingsMigrator->add('ai-orchestrator.ai_api_key', 'sk-idempotent-check-key');

    $migration = require __DIR__ . '/../../../database/settings/2026_09_04_213000_01_encrypt_ai_orchestrator_api_key.php';
    $migration->up();

    $onceEncrypted = SettingsProperty::get('ai-orchestrator.ai_api_key');

    $migration->up();

    $twiceEncrypted = SettingsProperty::get('ai-orchestrator.ai_api_key');

    expect($twiceEncrypted)->toBe($onceEncrypted)
        ->and(resolve(AIOrchestratorSettings::class)->ai_api_key)->toBe('sk-idempotent-check-key');
});

it('encrypts an empty default api key too, so a fresh unconfigured install still loads', function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
        dirname(__DIR__, 3) . '/database/settings',
    );

    /** @var SettingsMigrator $settingsMigrator */
    $settingsMigrator = resolve(SettingsMigrator::class);

    $settingsMigrator->deleteIfExists('ai-orchestrator.ai_api_key');
    $settingsMigrator->add('ai-orchestrator.ai_api_key', '');

    $migration = require __DIR__ . '/../../../database/settings/2026_09_04_213000_01_encrypt_ai_orchestrator_api_key.php';
    $migration->up();

    expect(resolve(AIOrchestratorSettings::class)->ai_api_key)->toBe('');
});

it('treats an invalid empty api key payload as unset during capability resolution', function (): void {
    test()->registerAndMigrateSettings(
        ['2026_05_10_190871_01_create_ai-orchestrator_settings'],
        dirname(__DIR__, 3) . '/database/settings',
    );

    /** @var SettingsMigrator $settingsMigrator */
    $settingsMigrator = resolve(SettingsMigrator::class);

    $settingsMigrator->deleteIfExists('ai-orchestrator.ai_api_key');
    $settingsMigrator->add('ai-orchestrator.ai_api_key', '');
    $settingsMigrator->deleteIfExists('ai-orchestrator.prompts');
    $settingsMigrator->add('ai-orchestrator.prompts', [
        'title_generation' => false,
        'content_generation' => false,
        'meta_description' => false,
    ]);

    $logs = new TestHandler;
    Log::swap(new LaravelLogger(new Logger('settings', [$logs])));

    $actionVisible = (new AiAssistantPageResourceExtender)->actions()[0]->isVisible();

    expect($actionVisible)->toBeFalse()
        ->and(resolve(AIOrchestratorSettings::class)->ai_api_key)->toBe('')
        ->and($logs->hasWarningRecords())->toBeTrue();
});
