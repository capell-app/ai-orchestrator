<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Filament\Pages\AIOrchestratorCapabilityCatalogPage;
use Capell\AIOrchestrator\Manifest\AiOrchestratorAdminPageContribution;
use Capell\AIOrchestrator\Settings\AIOrchestratorSettings;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Illuminate\Support\Facades\File;

it('positions the package as headless ai orchestration infrastructure', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = ai_orchestrator_json_file_array($packagePath . '/capell.json');
    $composer = ai_orchestrator_json_file_array($packagePath . '/composer.json');
    $screenshotContract = ai_orchestrator_json_file_array($packagePath . '/docs/screenshots.json');
    $betaArtwork = File::get($packagePath . '/docs/assets/marketplace/beta-contract.svg');
    $readme = File::get($packagePath . '/README.md');
    $overview = File::get($packagePath . '/docs/overview.md');

    expect($manifest['description'])->toContain('AI Assistant')
        ->and($manifest['description'])->toContain('brand voice')
        ->and(data_get($manifest, 'marketplace.summary'))->toBeString()->not->toBeEmpty()
        ->and($composer['description'])->toContain('AI Assistant')
        ->and(data_get($manifest, 'dependencies.requires'))->toBe([
            'capell-app/admin',
            'capell-app/core',
        ])
        ->and(data_get($manifest, 'dependencies.supports'))->toContain('capell-app/layout-builder')
        ->and(data_get($composer, 'require.capell-app/layout-builder'))->toBeNull()
        ->and(data_get($composer, 'suggest.capell-app/layout-builder'))->toBe(
            'Adds the optional AI-assisted layout planning module.',
        )
        ->and(data_get($manifest, 'marketplace.screenshots'))->toHaveCount(1)
        ->and(data_get($manifest, 'marketplace.screenshots.0.path'))->toBe('docs/assets/marketplace/beta-contract.svg')
        ->and(data_get($manifest, 'marketplace.screenshots.0.alt'))->toBeString()->not->toBeEmpty()
        ->and(data_get($manifest, 'marketplace.screenshots.0.caption'))->toBeString()->not->toBeEmpty()
        ->and(data_get($manifest, 'providers.admin'))->toBe([])
        ->and(data_get($manifest, 'providers.frontend'))->toBe([])
        ->and(data_get($manifest, 'database.migrations'))->toBeTrue()
        ->and(data_get($manifest, 'database.settings'))->toBeTrue()
        ->and(data_get($manifest, 'database.requiredTables'))->toBe([
            'ai_generation_histories',
            'ai_generation_requests',
            'ai_managed_provider_calls',
        ])
        ->and(data_get($manifest, 'settings'))->toBe([AIOrchestratorSettings::class])
        ->and(data_get($manifest, 'security.externalHttpClients.requiresTimeouts'))->toBeTrue()
        ->and(data_get($manifest, 'security.externalHttpClients.requiresSecretRedaction'))->toBeTrue()
        ->and(data_get($manifest, 'security.externalHttpClients.clients'))->toBe([PrismProvider::class])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'admin-page',
            'class' => AiOrchestratorAdminPageContribution::class,
            'pageClass' => AIOrchestratorCapabilityCatalogPage::class,
            'labelKey' => 'capell-ai-orchestrator::package.catalog_title',
        ])
        ->and($readme)->toContain('AI Assistant')
        ->and($readme)->toContain('RunAIOrchestratorCapabilityAction')
        ->and($readme)->toContain('Public routes: none declared.')
        ->and($overview)->toContain('AI Assistant')
        ->and($overview)->toContain('explicitly labelled as illustrative Beta contract artwork')
        ->and(File::exists($packagePath . '/docs-move-refs.txt'))->toBeFalse();

    $entries = ai_orchestrator_array_value($screenshotContract, 'entries');
    $entry = $entries[0] ?? null;
    throw_unless(is_array($entry), RuntimeException::class, 'AI Orchestrator must declare its screenshot entry.');
    $useCase = $entry['useCase'] ?? null;
    unset($entry['useCase']);
    throw_unless(is_string($useCase), RuntimeException::class, 'AI Orchestrator screenshot use case must be a string.');

    // The use case is public caption copy: it must exist and avoid capture-pipeline wording.
    expect(trim($useCase))->not->toBe('')
        ->and(preg_match('/\b(runner|buyer|selecting)\b/i', $useCase))->toBe(0);

    expect($screenshotContract['requiredEvidencePolicy'] ?? null)->toBe('distinct-required-surfaces')
        ->and(count($entries))->toBe(1)
        ->and($entry)->toBe([
            'id' => 'ai-orchestrator-beta-contract',
            'colorSchemes' => ['light'],
            'title' => 'AI Orchestrator beta contract artwork.',
            'package' => 'ai-orchestrator',
            'surface' => 'marketplace',
            'targetType' => 'marketplace-asset',
            'target' => 'beta-contract',
            'required' => false,
            'path' => 'docs/assets/marketplace/beta-contract.svg',
            'notes' => 'Optional illustrative Beta Marketplace artwork; no raster product screenshot is claimed. Product-screen proof belongs to consuming packages that render the registered capabilities, approvals, and execution results.',
        ])
        ->and(File::exists($packagePath . '/docs/assets/marketplace/beta-contract.svg'))->toBeTrue()
        ->and($betaArtwork)->toContain('BETA / CONTRACT ARTWORK', 'ILLUSTRATION ONLY', 'no product-screen claim');
});

it('advertises and documents the managed provider contract v1 boundary', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = ai_orchestrator_json_file_array($packagePath . '/capell.json');
    $contract = File::get($packagePath . '/docs/managed-provider-contract-v1.md');

    expect(data_get($manifest, 'capabilities'))->toBe(['ai-orchestrator', 'ai-orchestrator-admin'])
        ->and($contract)->toContain(
            'ProviderCallAuthorizationData',
            'ProviderCallAllowanceData',
            'ProviderIdempotencyHeaderData',
            'ProviderExecutionResultData',
            'MeasuredUsageData',
            'ValidatedProviderOutputData',
            'UsableVersionAttachmentData',
            'TerminalSettlementResultData',
        )
        ->and($contract)->toContain('The consuming application remains the sole financial authority.')
        ->and($contract)->toContain('Schema version: `1`');
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
