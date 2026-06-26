<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\SuggestPageTitlesAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Integrations\Authoring\Capabilities\SuggestTitleCapabilityAction;
use Mockery\MockInterface;

it('maps run data to the title suggestion action and returns titles', function (): void {
    $fakeTitles = ['First SEO Title', 'Second SEO Title'];

    $this->mock(SuggestPageTitlesAction::class, function (MockInterface $mock) use ($fakeTitles): void {
        $mock->shouldReceive('handle')
            ->once()
            ->andReturn($fakeTitles);
    });

    $run = AIOrchestratorRunData::from([
        'moduleKey' => 'ai-authoring',
        'capabilityKey' => 'suggest-title',
        'prompt' => '',
        'context' => [
            'content' => 'Page body text',
            'keywords' => 'widgets, gadgets',
            'pageId' => 10,
            'pageType' => 'page',
            'languageId' => 1,
            'options' => ['current_title' => 'Old Title'],
        ],
        'actor' => null,
    ]);

    $result = app(SuggestTitleCapabilityAction::class)->handle($run);

    expect($result)->toBe($fakeTitles);
});
