<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Filament\Pages\AIOrchestratorCapabilityCatalogPage;
use Capell\AIOrchestrator\Manifest\AiOrchestratorAdminPageContribution;
use Illuminate\Support\Facades\File;

it('positions the package as headless ai orchestration infrastructure', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = ai_orchestrator_json_file_array($packagePath . '/capell.json');
    $composer = ai_orchestrator_json_file_array($packagePath . '/composer.json');
    $screenshotContract = ai_orchestrator_json_file_array($packagePath . '/docs/screenshots.json');
    $readme = File::get($packagePath . '/README.md');
    $overview = File::get($packagePath . '/docs/overview.md');

    $expectedSummary = 'A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows.';

    expect($manifest['description'])->toBe($expectedSummary)
        ->and(data_get($manifest, 'marketplace.summary'))->toBe($expectedSummary)
        ->and($composer['description'])->toBe(rtrim($expectedSummary, '.'))
        ->and(data_get($manifest, 'marketplace.screenshots'))->toBe([])
        ->and(data_get($manifest, 'providers.admin'))->toBe([])
        ->and(data_get($manifest, 'providers.frontend'))->toBe([])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'admin-page',
            'class' => AiOrchestratorAdminPageContribution::class,
            'pageClass' => AIOrchestratorCapabilityCatalogPage::class,
            'labelKey' => 'capell-ai-orchestrator::package.catalog_title',
        ])
        ->and($readme)->toContain($expectedSummary)
        ->and($readme)->toContain('Public routes: none detected in package route files')
        ->and($overview)->toContain($expectedSummary)
        ->and($overview)->toContain('Marketplace screenshots intentionally remain empty');

    foreach (ai_orchestrator_array_value($screenshotContract, 'entries') as $entry) {
        expect($entry)->toBeArray();

        $entryData = is_array($entry) ? $entry : [];

        expect($entryData['required'] ?? null)->toBeFalse()
            ->and($entryData['target'] ?? null)->toBeNull()
            ->and($entryData['notes'] ?? '')->toContain('consuming');
    }
});

/**
 * @return array<array-key, mixed>
 */
function ai_orchestrator_json_file_array(string $path): array
{
    $decodedJson = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

    throw_unless(is_array($decodedJson), RuntimeException::class, sprintf('Expected %s to decode to an array.', $path));

    return $decodedJson;
}

/**
 * @param  array<array-key, mixed>  $values
 * @return array<array-key, mixed>
 */
function ai_orchestrator_array_value(array $values, string $key): array
{
    $value = $values[$key] ?? null;

    throw_unless(is_array($value), RuntimeException::class, sprintf('Expected %s to be an array.', $key));

    return $value;
}
