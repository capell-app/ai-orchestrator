<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\RegisterAIOrchestratorModuleAction;
use Capell\AIOrchestrator\Filament\Pages\AIOrchestratorCapabilityCatalogPage;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorModuleFixture;
use Capell\AIOrchestrator\Tests\Fixtures\Autoload\AIOrchestratorRunActionFixture;

it('lists registered capabilities for admin catalog review', function (): void {
    RegisterAIOrchestratorModuleAction::run(new AIOrchestratorModuleFixture(
        moduleKey: 'catalog-module',
        capabilityKey: 'catalog-capability',
        requiredAbility: 'ai-orchestrator.catalog-run',
    ));

    $rows = (new AIOrchestratorCapabilityCatalogPage)->capabilities();

    expect($rows)->toContain([
        'moduleKey' => 'catalog-module',
        'moduleLabel' => 'Test module',
        'key' => 'catalog-capability',
        'label' => 'Test capability',
        'description' => 'Create a test AI Orchestrator result.',
        'approvalLevel' => 'draft',
        'requiredAbility' => 'ai-orchestrator.catalog-run',
        'actionClass' => AIOrchestratorRunActionFixture::class,
    ]);
});

it('exposes translated catalog navigation metadata', function (): void {
    $page = new AIOrchestratorCapabilityCatalogPage;

    expect(AIOrchestratorCapabilityCatalogPage::getNavigationLabel())->toBe('AI Orchestrator')
        ->and($page->getTitle())->toBe('AI Orchestrator Capability Catalog')
        ->and($page->getSubheading())->toContain('registered AI modules');
});
