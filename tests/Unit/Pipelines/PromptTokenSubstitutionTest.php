<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\RecordAiGenerationAction;
use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Data\Ai\AiGenerationInputData;
use Capell\AIOrchestrator\Models\AIGenerationHistory;
use Capell\AIOrchestrator\Support\Ai\AiRateLimiter;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\AiResponseParser;
use Capell\AIOrchestrator\Support\Ai\Cache\RateLimitCache;
use Capell\AIOrchestrator\Support\Ai\Pipelines\GenerateContentPipeline;
use Capell\AIOrchestrator\Support\Ai\Pipelines\SuggestMetaDescriptionsPipeline;
use Capell\AIOrchestrator\Support\Ai\Pipelines\SuggestTitlesPipeline;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Support\Ai\PromptRepository;
use Mockery\MockInterface;

final class AIOrchestratorTokenCapture
{
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];
}

beforeEach(function (): void {
    $this->mock(RecordAiGenerationAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')->andReturn(new AIGenerationHistory);
    });
});

it('substitutes every title prompt token before calling the provider', function (): void {
    $capture = new AIOrchestratorTokenCapture;
    $pipeline = new SuggestTitlesPipeline(
        new PromptRepository(['title_generation' => [
            'system' => 'Title system',
            'user_template' => '{{content}}|{{current_title}}|{{keywords}}',
            'model' => 'test-model',
        ]]),
        aiOrchestratorTokenProvider($capture, "- Title\n- Alternative"),
        new AiResponseParser,
        new AiRateLimiter(resolve(RateLimitCache::class), ['enabled' => false]),
    );

    $pipeline->execute(AiGenerationInputData::forContextAction(
        'SuggestPageTitlesAction',
        aiOrchestratorTokenContext(),
        ['current_title' => 'Current title'],
    ));

    expect($capture->messages[1]['content'])->toStartWith('Page body|Current title|widgets');
});

it('substitutes every meta description prompt token before calling the provider', function (): void {
    $capture = new AIOrchestratorTokenCapture;
    $pipeline = new SuggestMetaDescriptionsPipeline(
        new PromptRepository(['meta_description' => [
            'system' => 'Meta system',
            'user_template' => '{{content}}|{{keywords}}',
            'model' => 'test-model',
        ]]),
        aiOrchestratorTokenProvider($capture, "- Description\n- Alternative"),
        new AiResponseParser,
        new AiRateLimiter(resolve(RateLimitCache::class), ['enabled' => false]),
    );

    $pipeline->execute(AiGenerationInputData::forContextAction(
        'SuggestMetaDescriptionsAction',
        aiOrchestratorTokenContext(),
    ));

    expect($capture->messages[1]['content'])->toStartWith('Page body|widgets');
});

it('substitutes every content prompt token before calling the provider', function (): void {
    $capture = new AIOrchestratorTokenCapture;
    $pipeline = new GenerateContentPipeline(
        new PromptRepository(['content_generation' => [
            'system' => 'Content system',
            'user_template' => '{{content}}|{{current_title}}|{{keywords}}|{{target_length}}|{{refactor}}',
            'model' => 'test-model',
        ]]),
        aiOrchestratorTokenProvider($capture, '<p>Generated content</p>'),
        new AiRateLimiter(resolve(RateLimitCache::class), ['enabled' => false]),
    );

    $pipeline->execute(AiGenerationInputData::forContextAction(
        'GeneratorPageContentAction',
        aiOrchestratorTokenContext(),
        ['current_title' => 'Current title', 'target_length' => 800, 'refactor' => true],
    ));

    expect($capture->messages[1]['content'])->toBe('Page body|Current title|widgets|800|yes');
});

function aiOrchestratorTokenProvider(AIOrchestratorTokenCapture $capture, string $response): PrismProvider
{
    return new class($capture, $response) extends PrismProvider
    {
        public function __construct(
            private readonly AIOrchestratorTokenCapture $capture,
            private readonly string $response,
        ) {
            parent::__construct(['max_retries' => 1]);
        }

        /** @param array<array-key, mixed> $params */
        public function chat(array $params): AiResponse
        {
            $rawMessages = $params['messages'] ?? null;
            throw_unless(is_array($rawMessages), InvalidArgumentException::class, 'Provider messages must be an array.');

            $messages = [];
            foreach ($rawMessages as $message) {
                throw_unless(is_array($message), InvalidArgumentException::class, 'Provider message must be an array.');
                throw_unless(is_string($message['role'] ?? null), InvalidArgumentException::class, 'Provider message role must be a string.');
                throw_unless(is_string($message['content'] ?? null), InvalidArgumentException::class, 'Provider message content must be a string.');

                $messages[] = ['role' => $message['role'], 'content' => $message['content']];
            }

            $this->capture->messages = $messages;

            return new AiResponse(
                content: $this->response,
                tokensUsed: 1,
                model: 'test-model',
                duration: 0.01,
                metadata: ['prompt_tokens' => 1, 'completion_tokens' => 0],
            );
        }
    };
}

function aiOrchestratorTokenContext(): AiActionContextInterface
{
    return new class implements AiActionContextInterface
    {
        public function getContent(): string
        {
            return 'Page body';
        }

        public function getKeywords(): string
        {
            return 'widgets';
        }

        public function getPageId(): int
        {
            return 1;
        }

        public function getPageType(): string
        {
            return 'page';
        }

        public function getLanguageId(): int
        {
            return 1;
        }
    };
}
