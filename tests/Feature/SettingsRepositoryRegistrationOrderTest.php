<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Providers\AIOrchestratorServiceProvider;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettingsRepository;
use Illuminate\Config\Repository;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

// A fresh `artisan config:cache` boot registered this provider before
// spatie/laravel-settings merged its defaults. Writing the repository during
// registration created `settings` with only this key, and the shallow
// mergeConfigFrom then dropped the `database` repository for the whole boot.
/** Replace the config with one where spatie has not merged `settings` yet. */
function withoutSettingsConfig(): void
{
    $items = config()->all();
    unset($items['settings']);
    app()->instance('config', new Repository($items));
}

it('does not write settings config while providers are still registering', function (): void {
    withoutSettingsConfig();

    (new AIOrchestratorServiceProvider(app()))->registeringPackage();

    expect(config()->has('settings'))->toBeFalse();
});

it('adds its repository without replacing the database repository', function (): void {
    $database = config('settings.repositories.database');

    if (! is_array($database) || $database === []) {
        throw new RuntimeException('spatie/laravel-settings did not provide a database repository.');
    }

    (new AIOrchestratorServiceProvider(app()))->registerSettingsRepository();

    expect(config('settings.repositories.database'))->toBe($database)
        ->and(config('settings.repositories.ai-orchestrator'))->toBe([
            ...$database,
            'type' => AIOrchestratorSettingsRepository::class,
        ]);
});

it('keeps the database repository when spatie merges its defaults after this provider registers', function (): void {
    withoutSettingsConfig();

    (new AIOrchestratorServiceProvider(app()))->registeringPackage();
    (new LaravelSettingsServiceProvider(app()))->register();

    expect(config('settings.repositories.database'))->toBeArray()->not->toBeEmpty();
});
