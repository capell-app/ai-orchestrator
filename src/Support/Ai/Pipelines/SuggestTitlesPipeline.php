<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Pipelines;

use Capell\AIOrchestrator\Actions\Ai\RecordAiGenerationAction;
use Capell\AIOrchestrator\Contracts\AiActionContextInterface;
use Capell\AIOrchestrator\Data\Ai\AiGenerationInputData;
use Capell\AIOrchestrator\Data\Ai\AiGenerationResultData;
use Capell\AIOrchestrator\Support\Ai\AiRateLimiter;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\AiResponseParser;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Support\Ai\PromptRepository;
use Illuminate\Pipeline\Pipeline;
use InvalidArgumentException;

class SuggestTitlesPipeline
{
    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly PrismProvider $provider,
        private readonly AiResponseParser $parser,
        private readonly AiRateLimiter $rateLimiter,
        private readonly RecordAiGenerationAction $recordAiGenerationAction,
    ) {}

    public function execute(AiGenerationInputData $input): AiGenerationResultData
    {
        $initialPayload = [
            'input' => $input,
            'context' => $input->context,
            'options' => $input->options,
        ];

        $payload = resolve(Pipeline::class)
            ->send($initialPayload)
            ->through([
                fn (array $payload, callable $next): array => $this->validateInput($payload, $next),
                fn (array $payload, callable $next): array => $this->checkRateLimit($payload, $next),
                fn (array $payload, callable $next): array => $this->executeAiCall($payload, $next),
                fn (array $payload, callable $next): array => $this->parseResponse($payload, $next),
                fn (array $payload, callable $next): array => $this->recordGeneration($payload, $next),
            ])
            ->thenReturn();

        /** @var AiGenerationResultData $resultData */
        $resultData = $payload['result_data'];

        return $resultData;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function validateInput(array $payload, callable $next): array
    {
        $context = $payload['context'] ?? null;
        throw_unless($context instanceof AiActionContextInterface, InvalidArgumentException::class, 'Missing AiActionContextInterface context');

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function checkRateLimit(array $payload, callable $next): array
    {
        $identifier = (string) ($payload['options']['user_id'] ?? 'global');
        $this->rateLimiter->checkLimit($identifier, 'title_suggestions');

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function executeAiCall(array $payload, callable $next): array
    {
        $context = $payload['context'];
        $options = $payload['options'] ?? [];
        $prompt = $this->prompts->get('title_generation');
        $content = $context->getContent();
        $keywords = $context->getKeywords();

        $userMessage = strtr((string) ($prompt['user_template'] ?? ''), [
            '{{content}}' => $content,
            '{{keywords}}' => $keywords,
            '{{current_title}}' => $options['current_title'] ?? '',
        ]);

        $messages = [
            ['role' => 'system', 'content' => (string) ($prompt['system'] ?? '')],
            ['role' => 'user', 'content' => $userMessage . "\nPlease provide 5 distinct title options as a simple bullet list."],
        ];

        $params = [
            'model' => (string) ($prompt['model'] ?? config('capell-ai-orchestrator.prism.model')),
            'messages' => $messages,
            'max_tokens' => config('capell-ai-orchestrator.prism.max_tokens', 128),
            'temperature' => 0.7,
        ];

        $response = $this->provider->chat($params);
        $payload['ai_response'] = $response;
        $payload['ai_messages'] = $messages;
        $payload['ai_params'] = $params;

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function parseResponse(array $payload, callable $next): array
    {
        /** @var AiResponse $response */
        $response = $payload['ai_response'];
        $parsed = $this->parser->parse($response->content);
        $payload['result'] = array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['value'] ?? ''), $parsed)));

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function recordGeneration(array $payload, callable $next): array
    {
        /** @var AiGenerationInputData $input */
        $input = $payload['input'];
        /** @var AiResponse $response */
        $response = $payload['ai_response'];
        /** @var AiActionContextInterface $context */
        $context = $payload['context'];
        $result = (array) ($payload['result'] ?? []);
        $resultData = AiGenerationResultData::make(
            actionKey: $input->actionKey,
            output: $result,
            inputText: $context->getContent(),
            outputText: implode("\n", $result),
            response: $response,
            messages: $payload['ai_messages'] ?? null,
            params: $payload['ai_params'] ?? null,
            pageableId: $context->getPageId(),
            pageableType: $context->getPageType(),
            languageId: $context->getLanguageId(),
        );

        $resultData->history = $this->recordAiGenerationAction->handle($resultData);
        $payload['result_data'] = $resultData;

        return $next($payload);
    }
}
