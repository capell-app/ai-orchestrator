<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Actions\Ai\AssertAiModelPriceConfiguredAction;
use Capell\AIOrchestrator\Exceptions\OpenAICircuitBreakerOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use LogicException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use RuntimeException;
use Throwable;

class PrismProvider
{
    private const string CIRCUIT_BREAKER_KEY_PREFIX = 'ai_circuit_breaker_state';

    private const int FAILURE_THRESHOLD = 5;

    private const int CIRCUIT_TIMEOUT = 300;

    protected int $maxRetries;

    protected int $retryDelay;

    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
        private readonly ?AIGenerationCache $generationCache = null,
        private readonly ?AiSpendGuard $spendGuard = null,
    ) {
        $this->maxRetries = max(1, $this->intConfig('max_retries', 3));
        $this->retryDelay = max(0, $this->intConfig('retry_delay_ms', 1000));
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    public function execute(array $input): mixed
    {
        return $this->chat($input);
    }

    /**
     * @param  array<array-key, mixed>  $params
     */
    public function chat(array $params): AiResponse
    {
        if (($this->config['require_queue_for_web'] ?? false) === true && ! app()->runningInConsole()) {
            throw new LogicException('AI provider calls must be executed by a queued worker.');
        }

        [$systemPrompt, $userMessage] = $this->promptsFrom($params['messages'] ?? []);
        [$systemPrompt, $userMessage] = $this->boundPrompts($systemPrompt, $userMessage);

        $model = $this->scalarString($params['model'] ?? $this->config['model'] ?? 'gpt-4o');
        $providerName = $this->scalarString($this->config['provider'] ?? 'openai');
        $maxTokens = isset($params['max_tokens']) ? $this->intFrom($params['max_tokens']) : $this->intConfig('max_tokens', 512);
        $temperature = isset($params['temperature']) ? $this->floatFrom($params['temperature']) : 0.7;
        $siteId = $this->positiveIntOrNull($params['site_id'] ?? null);

        if (($this->config['enforce_price_map'] ?? false) === true) {
            AssertAiModelPriceConfiguredAction::run($model);
        }
        $requestIdentity = [
            'provider' => $providerName,
            'model' => $model,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userMessage,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'site_id' => $siteId,
        ];
        $cacheKey = $this->generationCache?->keyForRequest($requestIdentity);

        if ($cacheKey !== null) {
            $cachedResponse = $this->generationCache->get($cacheKey);

            if ($cachedResponse instanceof AiResponse) {
                return new AiResponse(
                    content: $cachedResponse->content,
                    tokensUsed: $cachedResponse->tokensUsed,
                    model: $cachedResponse->model,
                    duration: 0.0,
                    metadata: [
                        ...$cachedResponse->metadata,
                        'cache_hit' => true,
                        'cost_micros' => 0,
                    ],
                );
            }
        }

        throw_if($this->isCircuitOpen($model), OpenAICircuitBreakerOpenException::class);

        $idempotencySource = $this->scalarString($params['idempotency_key'] ?? $cacheKey ?? json_encode($requestIdentity));
        $idempotencyKey = hash('sha256', $idempotencySource);
        $reservation = $this->spendGuard?->reserve(
            siteId: $siteId,
            model: $model,
            promptTokens: (int) ceil(mb_strlen($systemPrompt . $userMessage) / 4),
            completionTokens: max(0, $maxTokens),
            idempotencyKey: $idempotencyKey,
        );

        $attempt = 0;
        $lastException = null;
        $startTime = microtime(true);

        while ($attempt < $this->maxRetries) {
            try {
                $response = Prism::text()
                    ->using($this->resolveProvider($providerName), $model)
                    ->withSystemPrompt($systemPrompt)
                    ->withPrompt($userMessage)
                    ->withMaxTokens($maxTokens)
                    ->usingTemperature($temperature)
                    ->withClientOptions([
                        'timeout' => max(1, $this->intConfig('timeout_seconds', 30)),
                        'connect_timeout' => max(1, $this->intConfig('connect_timeout_seconds', 5)),
                        'headers' => ['Idempotency-Key' => $idempotencyKey],
                    ])
                    ->asText();

                $duration = microtime(true) - $startTime;
                $this->resetCircuitBreaker($model);
                $usage = $this->usageFromResponse($response);
                $promptTokens = $this->promptTokens($usage);
                $completionTokens = $this->completionTokens($usage);
                $totalTokens = $promptTokens + $completionTokens;

                Log::debug('AI API Call Metrics', [
                    'provider' => $providerName,
                    'model' => $model,
                    'total_tokens' => $totalTokens,
                    'duration_ms' => round($duration * 1000, 2),
                ]);

                $aiResponse = new AiResponse(
                    content: $response->text,
                    tokensUsed: $totalTokens,
                    model: $model,
                    duration: $duration,
                    metadata: [
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'cache_hit' => false,
                        'idempotency_key' => $idempotencyKey,
                        'site_id' => $siteId,
                        'spend_reservation_id' => $reservation?->id,
                        'estimated_cost_micros' => $reservation?->estimatedCostMicros,
                    ],
                );

                if ($cacheKey !== null) {
                    $this->generationCache->put($cacheKey, $aiResponse);
                }

                return $aiResponse;
            } catch (Throwable $e) {
                $attempt++;
                $lastException = $e;

                if (! $this->isRetryable($e)) {
                    Log::warning('AI API request rejected without retry', [
                        'provider' => $providerName,
                        'model' => $model,
                        'error' => $e->getMessage(),
                    ]);

                    $this->spendGuard?->release($reservation?->id);

                    throw $e;
                }

                $this->recordFailure($model);

                Log::warning('AI API attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->maxRetries) {
                    $this->spendGuard?->release($reservation?->id);

                    throw $lastException;
                }

                $delay = $this->retryDelay * (2 ** ($attempt - 1));
                $jitter = random_int(0, (int) ($delay * 0.1));
                Sleep::usleep(($delay + $jitter) * 1000);
            }
        }

        $this->spendGuard?->release($reservation?->id);

        throw $lastException ?? new RuntimeException('Unknown AI provider error');
    }

    public function isAvailable(): bool
    {
        return ! $this->isCircuitOpen();
    }

    public function handles(): string
    {
        return 'prism_provider';
    }

    public function resetCircuitBreaker(?string $model = null): void
    {
        Cache::forget($this->circuitBreakerKey($model));
    }

    public function circuitBreakerKey(?string $model = null): string
    {
        $providerName = $this->scalarString($this->config['provider'] ?? 'openai');
        $modelName = $model ?? $this->scalarString($this->config['model'] ?? 'gpt-4o');

        return self::CIRCUIT_BREAKER_KEY_PREFIX . ':' . strtolower($providerName) . ':' . strtolower($modelName);
    }

    protected function resolveProvider(string $name): Provider
    {
        return match (strtolower($name)) {
            'anthropic' => Provider::Anthropic,
            'gemini', 'google' => Provider::Gemini,
            'ollama' => Provider::Ollama,
            default => Provider::OpenAI,
        };
    }

    protected function isCircuitOpen(?string $model = null): bool
    {
        return $this->currentFailures($model) >= self::FAILURE_THRESHOLD;
    }

    protected function recordFailure(?string $model = null): void
    {
        Cache::put($this->circuitBreakerKey($model), ['failures' => $this->currentFailures($model) + 1], self::CIRCUIT_TIMEOUT);
    }

    protected function isRetryable(Throwable $exception): bool
    {
        $currentException = $exception;

        do {
            if ($currentException instanceof ConnectionException
                || $currentException instanceof PrismRateLimitedException
                || $currentException instanceof PrismProviderOverloadedException) {
                return true;
            }

            $statusCode = $currentException->getCode();

            if (in_array($statusCode, [408, 409, 425, 429], true) || $statusCode >= 500) {
                return true;
            }

            $currentException = $currentException->getPrevious();
        } while ($currentException instanceof Throwable);

        return false;
    }

    private function currentFailures(?string $model = null): int
    {
        $state = Cache::get($this->circuitBreakerKey($model), ['failures' => 0]);
        $failures = is_array($state) ? ($state['failures'] ?? 0) : 0;

        return is_numeric($failures) ? (int) $failures : 0;
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    private function intFrom(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function floatFrom(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return array{string, string}
     */
    private function promptsFrom(mixed $messages): array
    {
        $systemPrompts = [];
        $userMessages = [];

        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $content = $this->scalarString($message['content'] ?? '');

                if (($message['role'] ?? null) === 'system') {
                    $systemPrompts[] = $content;
                } elseif (($message['role'] ?? null) === 'user') {
                    $userMessages[] = $content;
                }
            }
        }

        return [implode("\n\n", $systemPrompts), implode("\n\n", $userMessages)];
    }

    /**
     * @return array{string, string}
     */
    private function boundPrompts(string $systemPrompt, string $userPrompt): array
    {
        $maximumCharacters = max(1, $this->intConfig('max_prompt_chars', 32_000));

        if (mb_strlen($systemPrompt . $userPrompt) <= $maximumCharacters) {
            return [$systemPrompt, $userPrompt];
        }

        if ($systemPrompt === '') {
            return ['', mb_substr($userPrompt, 0, $maximumCharacters)];
        }

        if ($userPrompt === '') {
            return [mb_substr($systemPrompt, 0, $maximumCharacters), ''];
        }

        $systemBudget = max(1, (int) floor($maximumCharacters * 0.35));
        $boundedSystemPrompt = mb_substr($systemPrompt, 0, $systemBudget);
        $userBudget = max(0, $maximumCharacters - mb_strlen($boundedSystemPrompt));

        return [$boundedSystemPrompt, mb_substr($userPrompt, 0, $userBudget)];
    }

    private function usageFromResponse(mixed $response): mixed
    {
        if (! is_object($response) || ! isset($response->usage)) {
            return null;
        }

        return $response->usage;
    }

    private function promptTokens(mixed $usage): int
    {
        return is_object($usage) && isset($usage->promptTokens)
            ? (int) $usage->promptTokens
            : 0;
    }

    private function completionTokens(mixed $usage): int
    {
        return is_object($usage) && isset($usage->completionTokens)
            ? (int) $usage->completionTokens
            : 0;
    }
}
