<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Integrations\Authoring;

use Capell\AIOrchestrator\Contracts\AIOrchestratorModule;
use Capell\AIOrchestrator\Data\AIOrchestratorCapabilityData;
use Capell\AIOrchestrator\Enums\AIOrchestratorApprovalLevel;
use Capell\AIOrchestrator\Integrations\Authoring\Capabilities\GenerateContentCapabilityAction;
use Capell\AIOrchestrator\Integrations\Authoring\Capabilities\SuggestMetaDescriptionCapabilityAction;
use Capell\AIOrchestrator\Integrations\Authoring\Capabilities\SuggestTitleCapabilityAction;

class AIAuthoringModule implements AIOrchestratorModule
{
    public function key(): string
    {
        return 'ai-authoring';
    }

    public function label(): string
    {
        return 'AI Authoring';
    }

    /**
     * @return array<int, AIOrchestratorCapabilityData>
     */
    public function capabilities(): array
    {
        return [
            new AIOrchestratorCapabilityData(
                key: 'suggest-title',
                label: 'Suggest title',
                description: 'Suggest page titles from the current content.',
                actionClass: SuggestTitleCapabilityAction::class,
                approvalLevel: AIOrchestratorApprovalLevel::Draft,
            ),
            new AIOrchestratorCapabilityData(
                key: 'generate-content',
                label: 'Generate content',
                description: 'Generate page content from a natural-language prompt.',
                actionClass: GenerateContentCapabilityAction::class,
                approvalLevel: AIOrchestratorApprovalLevel::Draft,
            ),
            new AIOrchestratorCapabilityData(
                key: 'suggest-meta-description',
                label: 'Suggest meta description',
                description: 'Suggest a meta description from the current content.',
                actionClass: SuggestMetaDescriptionCapabilityAction::class,
                approvalLevel: AIOrchestratorApprovalLevel::Draft,
            ),
        ];
    }
}
