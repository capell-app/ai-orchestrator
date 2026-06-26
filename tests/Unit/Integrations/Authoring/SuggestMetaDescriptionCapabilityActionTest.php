<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\SuggestMetaDescriptionsAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Integrations\Authoring\Capabilities\SuggestMetaDescriptionCapabilityAction;
use Capell\AIOrchestrator\Support\Ai\Context\ContentActionContext;
use Mockery\MockInterface;

it('maps run data to the meta description action and returns descriptions', function (): void {
    $fakeDescriptions = ['Desc one', 'Desc two'];

    $this->mock(SuggestMetaDescriptionsAction::class, function (MockInterface $mock) use ($fakeDescriptions): void {
        $mock->shouldReceive('handle')
            ->once()
            ->with(
                Mockery::on(fn (ContentActionContext $context): bool => $context->getContent() === 'Page body text'
                    && $context->getKeywords() === 'widgets, gadgets'
                    && $context->getPageId() === 10
                    && $context->getPageType() === 'page'
                    && $context->getLanguageId() === 1),
                ['current_title' => 'Old Meta'],
            )
            ->andReturn($fakeDescriptions);
    });

    $run = AIOrchestratorRunData::from([
        'moduleKey' => 'ai-authoring',
        'capabilityKey' => 'suggest-meta-description',
        'prompt' => '',
        'context' => [
            'content' => 'Page body text',
            'keywords' => 'widgets, gadgets',
            'pageId' => 10,
            'pageType' => 'page',
            'languageId' => 1,
            'options' => ['current_title' => 'Old Meta'],
        ],
        'actor' => null,
    ]);

    $result = app(SuggestMetaDescriptionCapabilityAction::class)->handle($run);

    expect($result)->toBe($fakeDescriptions);
});
