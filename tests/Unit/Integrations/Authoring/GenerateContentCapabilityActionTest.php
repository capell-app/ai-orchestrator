<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\GeneratorPageContentAction;
use Capell\AIOrchestrator\Data\AIOrchestratorRunData;
use Capell\AIOrchestrator\Integrations\Authoring\Capabilities\GenerateContentCapabilityAction;
use Capell\AIOrchestrator\Support\Ai\Context\ContentActionContext;
use Mockery\MockInterface;

it('maps run data to the content generation action and returns generated html', function (): void {
    $generatedHtml = '<p>Generated body</p>';

    $this->mock(GeneratorPageContentAction::class, function (MockInterface $mock) use ($generatedHtml): void {
        $mock->shouldReceive('handle')
            ->once()
            ->with(
                Mockery::type(ContentActionContext::class),
                ['current_title' => 'X', 'target_length' => 800, 'refactor' => true],
            )
            ->andReturn($generatedHtml);
    });

    $run = AIOrchestratorRunData::from([
        'moduleKey' => 'ai-authoring',
        'capabilityKey' => 'generate-content',
        'prompt' => '',
        'context' => [
            'content' => 'Page body text',
            'keywords' => 'widgets, gadgets',
            'pageId' => 10,
            'pageType' => 'page',
            'languageId' => 1,
            'options' => ['current_title' => 'X', 'target_length' => 800, 'refactor' => true],
        ],
        'actor' => null,
    ]);

    $result = app(GenerateContentCapabilityAction::class)->handle($run);

    expect($result)->toBe($generatedHtml);
});
