<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Exceptions\OpenAICircuitBreakerOpenException;
use Capell\Core\Contracts\ServiceContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use RuntimeException;
use Throwable;

class PrismProvider implements ServiceContract
{
    private const string CIRCUIT_BREAKER_KEY_PREFIX = 'ai_circuit_breaker_state';

    private const int FAILURE_THRESHOLD = 5;

    private const int CIRCUIT_TIMEOUT = 300;

    protected int $maxRetries;

    protected int $retryDelay;

    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(protected array $config = [])
    {
        $this->maxRetries = (int) ($this->config['max_retries'] ?? 3);
        $this->retryDelay = (int) ($this->config['retry_delay_ms'] ?? 1000);
    }

    public function execute(array $input): mixed
    {
        return $this->chat($input);
    }

    /**
     * @param  array<array-key, mixed>  $params
     */
    public function chat(array $params): AiResponse
    {
        throw_if($this->isCircuitOpen(), OpenAICircuitBreakerOpenException::class);

        $attempt = 0;
        $lastException = null;
        $startTime = microtime(true);

        while ($attempt < $this->maxRetries) {
            try {
                $messages = $params['messages'] ?? [];
                $systemPrompt = '';
                $userMessage = '';

                $userMessages = [];
                foreach ($messages as $message) {
                    if ($message['role'] === 'system') {
                        $systemPrompt = $message['content'];
                    } elseif ($message['role'] === 'user') {
                        $userMessages[] = $message['content'];
                    }
                }

                $userMessage = implode("\n\n", $userMessages);

                $model = $params['model'] ?? $this->config['model'] ?? 'gpt-4o';
                $providerName = $this->config['provider'] ?? 'openai';

                $maxTokens = isset($params['max_tokens']) ? (int) $params['max_tokens'] : (int) ($this->config['max_tokens'] ?? 512);
                $temperature = isset($params['temperature']) ? (float) $params['temperature'] : 0.7;

                $response = Prism::text()
                    ->using($this->resolveProvider($providerName), $model)
                    ->withSystemPrompt($systemPrompt)
                    ->withPrompt($userMessage)
                    ->withMaxTokens($maxTokens)
                    ->usingTemperature($temperature)
                    ->asText();

                $duration = microtime(true) - $startTime;
                $this->resetCircuitBreaker();
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

                return new AiResponse(
                    content: $response->text,
                    tokensUsed: $totalTokens,
                    model: $model,
                    duration: $duration,
                    metadata: [
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                    ],
                );
            } catch (Throwable $e) {
                $attempt++;
                $lastException = $e;
                $this->recordFailure();

                Log::warning('AI API attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'error' => $e->getMessage(),
                ]);

                throw_if($attempt >= $this->maxRetries, $lastException);

                $delay = $this->retryDelay * (2 ** ($attempt - 1));
                $jitter = random_int(0, (int) ($delay * 0.1));
                Sleep::usleep(($delay + $jitter) * 1000);
            }
        }

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

    public function resetCircuitBreaker(): void
    {
        Cache::forget($this->circuitBreakerKey());
    }

    public function circuitBreakerKey(): string
    {
        $providerName = $this->config['provider'] ?? 'openai';

        return self::CIRCUIT_BREAKER_KEY_PREFIX . ':' . strtolower((string) $providerName);
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

    protected function isCircuitOpen(): bool
    {
        $state = Cache::get($this->circuitBreakerKey(), ['failures' => 0]);

        return (int) ($state['failures'] ?? 0) >= self::FAILURE_THRESHOLD;
    }

    protected function recordFailure(): void
    {
        $state = Cache::get($this->circuitBreakerKey(), ['failures' => 0]);
        $state['failures'] = (int) ($state['failures'] ?? 0) + 1;
        Cache::put($this->circuitBreakerKey(), $state, self::CIRCUIT_TIMEOUT);
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
