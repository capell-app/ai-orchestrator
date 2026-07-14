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
    $betaArtwork = File::get($packagePath . '/docs/screenshots/beta-contract.svg');
    $readme = File::get($packagePath . '/README.md');
    $overview = File::get($packagePath . '/docs/overview.md');

    $expectedSummary = 'A shared AI capability registry and execution contract for Capell packages, designed for governed prompts, approvals, and package-owned AI workflows.';

    expect($manifest['description'])->toBe($expectedSummary)
        ->and(data_get($manifest, 'marketplace.summary'))->toBe($expectedSummary)
        ->and($composer['description'])->toBe(rtrim($expectedSummary, '.'))
        ->and(data_get($manifest, 'marketplace.screenshots'))->toBe([[
            'path' => 'docs/screenshots/beta-contract.svg',
            'alt' => 'Illustrative AI Orchestrator Beta contract from capability registration to consumer-owned UI',
            'caption' => 'Illustrative Beta contract artwork: AI Orchestrator owns registration, governed execution, and approval boundaries while consuming packages own the screens.',
        ]])
        ->and(data_get($manifest, 'providers.admin'))->toBe([])
        ->and(data_get($manifest, 'providers.frontend'))->toBe([])
        ->and(data_get($manifest, 'database.migrations'))->toBeTrue()
        ->and(data_get($manifest, 'database.settings'))->toBeTrue()
        ->and(data_get($manifest, 'database.requiredTables'))->toBe(['ai_generation_histories', 'ai_generation_requests'])
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
        ->and($readme)->toContain('AI Orchestrator provides a shared capability registry, governed execution path, generation history, and request queue for AI features owned by other Capell packages.')
        ->and($readme)->toContain('Public routes: none declared.')
        ->and($overview)->toContain($expectedSummary)
        ->and($overview)->toContain('explicitly labelled as illustrative Beta contract artwork')
        ->and(File::exists($packagePath . '/docs-move-refs.txt'))->toBeFalse();

    expect($screenshotContract['requiredEvidencePolicy'] ?? null)->toBe('distinct-required-surfaces')
        ->and(ai_orchestrator_array_value($screenshotContract, 'entries'))->toBe([[
            'id' => 'ai-orchestrator-beta-contract',
            'title' => 'AI Orchestrator beta contract artwork.',
            'package' => 'ai-orchestrator',
            'surface' => 'marketplace',
            'targetType' => 'marketplace-asset',
            'target' => 'beta-contract',
            'required' => true,
            'path' => 'docs/assets/marketplace/beta-contract.svg',
            'screenshotPath' => 'packages/ai-orchestrator/docs/screenshots/beta-contract.svg',
            'notes' => 'Committed Beta Marketplace artwork that labels itself as an illustration and describes the headless registration, governance, approval, and consumer-UI boundary.',
            'useCase' => 'A buyer evaluates the Beta orchestration contract without mistaking a generic consuming screen for package-owned product evidence.',
        ]])
        ->and(File::exists($packagePath . '/docs/screenshots/beta-contract.svg'))->toBeTrue()
        ->and($betaArtwork)->toContain('BETA / CONTRACT ARTWORK', 'ILLUSTRATION ONLY', 'no product-screen claim');
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
