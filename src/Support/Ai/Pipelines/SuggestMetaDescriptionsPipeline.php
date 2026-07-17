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
use Capell\AIOrchestrator\Support\Ai\Concerns\NormalizesAiValues;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Support\Ai\PromptRepository;
use Illuminate\Pipeline\Pipeline;
use InvalidArgumentException;
use RuntimeException;

class SuggestMetaDescriptionsPipeline
{
    use NormalizesAiValues;

    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly PrismProvider $provider,
        private readonly AiResponseParser $parser,
        private readonly AiRateLimiter $rateLimiter,
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

        $resultData = is_array($payload) ? ($payload['result_data'] ?? null) : null;
        throw_unless($resultData instanceof AiGenerationResultData, RuntimeException::class, 'Meta description pipeline produced no result data');

        return $resultData;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function validateInput(array $payload, callable $next): array
    {
        $context = $payload['context'] ?? null;
        throw_if(! $context instanceof AiActionContextInterface, InvalidArgumentException::class, 'Missing context');

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function checkRateLimit(array $payload, callable $next): array
    {
        $options = $this->aiArray($payload['options'] ?? null);
        $identifier = $this->aiString($options['user_id'] ?? 'global') ?: 'global';
        $this->rateLimiter->checkLimit($identifier, 'meta_suggestions');

        return $next($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function executeAiCall(array $payload, callable $next): array
    {
        /** @var AiActionContextInterface $context */
        $context = $payload['context'];
        $options = $this->aiArray($payload['options'] ?? null);
        $prompt = $this->aiArray($this->prompts->get('meta_description'));
        $content = $context->getContent();
        $keywords = $context->getKeywords();

        $userMessage = strtr($this->aiString($prompt['user_template'] ?? ''), [
            '{{content}}' => $content,
            '{{keywords}}' => $keywords,
        ]);

        $params = [
            'model' => $this->aiString($prompt['model'] ?? config('capell-ai-orchestrator.prism.model')),
            'messages' => [
                ['role' => 'system', 'content' => $this->aiString($prompt['system'] ?? '')],
                ['role' => 'user', 'content' => $userMessage . "\nPlease provide 3 meta description options as a simple bullet list."],
            ],
            'max_tokens' => config('capell-ai-orchestrator.prism.max_tokens', 128),
            'temperature' => 0.7,
            'site_id' => $this->positiveIntOrNull($options['site_id'] ?? null),
        ];

        $response = $this->provider->chat($params);
        $payload['ai_response'] = $response;
        $payload['ai_messages'] = $params['messages'];
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
        $payload['result'] = array_values(array_unique(array_map(fn (array $row): string => $this->aiString($row['value'] ?? ''), $parsed)));

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
        $result = array_map(fn (mixed $value): string => $this->aiString($value), $this->aiArray($payload['result'] ?? []));
        $resultData = AiGenerationResultData::make(
            actionKey: $input->actionKey,
            output: $result,
            inputText: $context->getContent(),
            outputText: implode("\n", $result),
            response: $response,
            messages: $this->aiMessages($payload['ai_messages'] ?? null),
            params: $this->aiParams($payload['ai_params'] ?? null),
            pageableId: $context->getPageId(),
            pageableType: $context->getPageType(),
            languageId: $context->getLanguageId(),
            siteId: $this->positiveIntOrNull($input->options['site_id'] ?? null),
        );

        $resultData->history = RecordAiGenerationAction::run($resultData);
        $payload['result_data'] = $resultData;

        return $next($payload);
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
