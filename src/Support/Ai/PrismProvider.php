<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Contracts\ProviderOutputValidator;
use Capell\AIOrchestrator\Data\Ai\MeasuredUsageData;
use Capell\AIOrchestrator\Data\Ai\ProviderAttemptEvidenceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ProviderExecutionResultData;
use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use Capell\AIOrchestrator\Enums\Ai\ManagedProviderClaimStatus;
use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Exceptions\AiContractRefusedException;
use Capell\AIOrchestrator\Exceptions\ManagedProviderDispatchInterruptedException;
use Capell\AIOrchestrator\Exceptions\ManagedProviderPersistenceException;
use Capell\AIOrchestrator\Exceptions\ManagedProviderResultCommittedException;
use Capell\AIOrchestrator\Exceptions\OpenAICircuitBreakerOpenException;
use Carbon\CarbonImmutable;
use Closure;
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
        private readonly ?ManagedProviderCallRepository $managedCalls = null,
        private readonly ?Closure $clock = null,
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
        return $this->dispatchChat($params);
    }

    /**
     * Execute one managed provider call against a caller-issued authorization.
     *
     * @param  array<array-key, mixed>  $params
     */
    public function authorizedChat(
        ProviderCallAuthorizationData $authorization,
        string $operationIdentity,
        string $stageIdentity,
        int $fencingToken,
        string $validatedOutputIdentity,
        ProviderOutputValidator $outputValidator,
        array $params,
    ): ProviderExecutionResultData {
        try {
            $authorization->assertUsable($operationIdentity, $stageIdentity, $fencingToken, $this->now());

            if (! hash_equals($authorization->requestDigest, $this->managedRequestDigest($params))) {
                throw new AiContractRefusedException(ContractRefusal::IdentityMismatch, 'The provider request does not match the authorized request digest.');
            }
            if ($authorization->validatedOutputIdentity !== $validatedOutputIdentity
                || $authorization->validatorPolicyIdentity !== $outputValidator->policyIdentity()
                || ! hash_equals($authorization->validatorPolicyDigest, $outputValidator->policyDigest())) {
                throw new AiContractRefusedException(ContractRefusal::IdentityMismatch, 'The validated output or validator policy does not match the authorization.');
            }

            $maxTokens = isset($params['max_tokens']) ? $this->intFrom($params['max_tokens']) : $this->intConfig('max_tokens', 512);
            if ($maxTokens > $authorization->allowance->outputTokens) {
                throw new AiContractRefusedException(ContractRefusal::AllowanceExceeded, 'The requested output-token maximum exceeds the authorization.');
            }

            [$systemPrompt, $userPrompt] = $this->promptsFrom($params['messages'] ?? []);
            if ((int) ceil(mb_strlen($systemPrompt . $userPrompt) / 4) > $authorization->allowance->inputTokens) {
                throw new AiContractRefusedException(ContractRefusal::AllowanceExceeded, 'The conservative input-token estimate exceeds the authorization.');
            }
        } catch (AiContractRefusedException $exception) {
            return ProviderExecutionResultData::refused($authorization->providerCallIdempotencyKey, $exception->refusal, $this->errorCode($exception));
        }

        $repository = $this->managedCalls ?? app(ManagedProviderCallRepository::class);
        $claim = $repository->claim($authorization, $this->now(), $this->managedLeaseSeconds(), max(3600, $this->intConfig('managed_recovery_seconds', 2_592_000)));
        if ($claim->status === ManagedProviderClaimStatus::Replayed && $claim->replayedResult !== null) {
            $result = $claim->replayedResult;

            return new ProviderExecutionResultData($result->providerCallIdentity, $result->validity, $result->validatedOutputIdentity, $result->validatedOutputDigest, $result->retryClassification, $result->refusal, $result->errorCode, $result->usage, $result->output, true, $result->attempts);
        }
        if ($claim->status !== ManagedProviderClaimStatus::Acquired || $claim->leaseToken === null) {
            $refusal = match ($claim->status) {
                ManagedProviderClaimStatus::InProgress => ContractRefusal::InProgress,
                ManagedProviderClaimStatus::RecoveryExpired => ContractRefusal::RecoveryExpired,
                default => ContractRefusal::IdentityMismatch,
            };
            $retry = $claim->status === ManagedProviderClaimStatus::InProgress ? RetryClassification::Retryable : RetryClassification::Terminal;

            return ProviderExecutionResultData::refused($authorization->providerCallIdempotencyKey, $refusal, $refusal->value, $retry);
        }

        return $this->dispatchManagedChat($authorization, $operationIdentity, $stageIdentity, $fencingToken, $validatedOutputIdentity, $outputValidator, $params, $claim->leaseToken, $claim->attempts, $repository);
    }

    /** @param array<array-key, mixed> $params */
    public function managedRequestDigest(array $params): string
    {
        [$systemPrompt, $userPrompt] = $this->promptsFrom($params['messages'] ?? []);
        [$systemPrompt, $userPrompt] = $this->boundPrompts($systemPrompt, $userPrompt);
        $identity = [
            'max_tokens' => isset($params['max_tokens']) ? $this->intFrom($params['max_tokens']) : $this->intConfig('max_tokens', 512),
            'model' => $this->scalarString($params['model'] ?? $this->config['model'] ?? 'gpt-4o'),
            'provider' => $this->scalarString($this->config['provider'] ?? 'openai'),
            'site_id' => $this->positiveIntOrNull($params['site_id'] ?? null),
            'system_prompt' => $systemPrompt,
            'temperature' => isset($params['temperature']) ? $this->floatFrom($params['temperature']) : 0.7,
            'user_prompt' => $userPrompt,
        ];

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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

    /**
     * @param  array<array-key, mixed>  $params
     * @param  list<ProviderAttemptEvidenceData>  $attempts
     */
    private function dispatchManagedChat(
        ProviderCallAuthorizationData $authorization,
        string $operationIdentity,
        string $stageIdentity,
        int $fencingToken,
        string $validatedOutputIdentity,
        ProviderOutputValidator $outputValidator,
        array $params,
        string $leaseToken,
        array $attempts,
        ManagedProviderCallRepository $repository,
    ): ProviderExecutionResultData {
        [$systemPrompt, $userMessage] = $this->promptsFrom($params['messages'] ?? []);
        [$systemPrompt, $userMessage] = $this->boundPrompts($systemPrompt, $userMessage);
        $model = $this->scalarString($params['model'] ?? $this->config['model'] ?? 'gpt-4o');
        $providerName = $this->scalarString($this->config['provider'] ?? 'openai');
        $maxTokens = isset($params['max_tokens']) ? $this->intFrom($params['max_tokens']) : $this->intConfig('max_tokens', 512);
        $temperature = isset($params['temperature']) ? $this->floatFrom($params['temperature']) : 0.7;
        $maximumAttempts = min($this->maxRetries, $authorization->allowance->requestCount);
        $leaseSeconds = $this->managedLeaseSeconds();
        $recoverySeconds = max(3600, $this->intConfig('managed_recovery_seconds', 2_592_000));

        $lastAttempt = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        if ($lastAttempt !== null && ! ($lastAttempt->outcome === ProviderAttemptOutcome::FailedBeforeUsage && $lastAttempt->retryClassification === RetryClassification::Retryable)) {
            if ($lastAttempt->outcome === ProviderAttemptOutcome::HandoffStarted) {
                $lastAttempt = new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, $lastAttempt->attempt, ProviderAttemptOutcome::OutcomeUnknown, RetryClassification::Terminal, null, ContractRefusal::ProviderOutcomeUnknown->value);
                $attempts = $this->replaceLastAttempt($attempts, $lastAttempt);
                $this->resolveManagedAttempt($repository, $authorization, $leaseToken, $lastAttempt, $leaseSeconds);
            }

            return $this->persistManagedResult($repository, $authorization, $leaseToken, ProviderExecutionResultData::failed($authorization->providerCallIdempotencyKey, ContractRefusal::ProviderOutcomeUnknown->value, $this->managedPostHandoffRetryClassification(), $attempts, ContractRefusal::ProviderOutcomeUnknown), $recoverySeconds);
        }

        for ($attemptNumber = count($attempts) + 1; $attemptNumber <= $maximumAttempts; $attemptNumber++) {
            try {
                $authorization->assertUsable($operationIdentity, $stageIdentity, $fencingToken, $this->now());
            } catch (AiContractRefusedException $exception) {
                return $this->persistManagedResult($repository, $authorization, $leaseToken, ProviderExecutionResultData::refused($authorization->providerCallIdempotencyKey, $exception->refusal, $this->errorCode($exception), RetryClassification::Terminal, $attempts), $recoverySeconds);
            }

            $handoff = new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, $attemptNumber, ProviderAttemptOutcome::HandoffStarted, RetryClassification::NotApplicable, null, null);
            $attempts[] = $handoff;
            $repository->appendAttempt($authorization, $leaseToken, $handoff, $this->now(), $leaseSeconds);
            $afterDispatch = $this->config['after_managed_dispatch'] ?? null;
            if (is_callable($afterDispatch)) {
                try {
                    $afterDispatch($handoff);
                } catch (Throwable $exception) {
                    throw new ManagedProviderDispatchInterruptedException($exception);
                }
            }

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
                        'headers' => ['Idempotency-Key' => $authorization->providerHeaderIdentity()->value],
                    ])
                    ->asText();
                $usageValue = $this->usageFromResponse($response);
                if ($usageValue === null) {
                    $attemptEvidence = new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, $attemptNumber, ProviderAttemptOutcome::InvalidUsage, RetryClassification::Terminal, null, ContractRefusal::MissingUsage->value);
                    $attempts = $this->replaceLastAttempt($attempts, $attemptEvidence);
                    $this->resolveManagedAttempt($repository, $authorization, $leaseToken, $attemptEvidence, $leaseSeconds);

                    return $this->persistManagedResult($repository, $authorization, $leaseToken, new ProviderExecutionResultData($authorization->providerCallIdempotencyKey, ExecutionValidity::Invalid, null, null, RetryClassification::Terminal, ContractRefusal::MissingUsage, ContractRefusal::MissingUsage->value, null, null, false, $attempts), $recoverySeconds);
                }
                $usage = new MeasuredUsageData($authorization->providerCallIdempotencyKey, 1, $this->promptTokens($usageValue), $this->completionTokens($usageValue), $providerName, $model, $this->prismRunIdentity($response), 'prism', true);
                $attemptEvidence = new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, $attemptNumber, ProviderAttemptOutcome::Reported, RetryClassification::NotApplicable, $usage, null);
                $attempts = $this->replaceLastAttempt($attempts, $attemptEvidence);
                $this->resolveManagedAttempt($repository, $authorization, $leaseToken, $attemptEvidence, $leaseSeconds);

                try {
                    $usage->assertWithin($authorization);
                } catch (AiContractRefusedException $exception) {
                    return $this->persistManagedResult($repository, $authorization, $leaseToken, ProviderExecutionResultData::refused($authorization->providerCallIdempotencyKey, $exception->refusal, $this->errorCode($exception), RetryClassification::Terminal, $attempts), $recoverySeconds);
                }
                try {
                    $validatedOutput = $outputValidator->validate($validatedOutputIdentity, $response->text);
                } catch (Throwable) {
                    return $this->persistManagedResult($repository, $authorization, $leaseToken, new ProviderExecutionResultData($authorization->providerCallIdempotencyKey, ExecutionValidity::Invalid, null, null, RetryClassification::Terminal, ContractRefusal::InvalidOutput, ContractRefusal::InvalidOutput->value, null, null, false, $attempts), $recoverySeconds);
                }
                if ($validatedOutput === null || $validatedOutput->identity !== $validatedOutputIdentity) {
                    return $this->persistManagedResult($repository, $authorization, $leaseToken, new ProviderExecutionResultData($authorization->providerCallIdempotencyKey, ExecutionValidity::Invalid, null, null, RetryClassification::Terminal, ContractRefusal::InvalidOutput, ContractRefusal::InvalidOutput->value, null, null, false, $attempts), $recoverySeconds);
                }

                return $this->persistManagedResult($repository, $authorization, $leaseToken, ProviderExecutionResultData::fromValidatedOutput($authorization->providerCallIdempotencyKey, $validatedOutput, $usage, $attempts), $recoverySeconds);
            } catch (Throwable $exception) {
                if ($exception instanceof ManagedProviderResultCommittedException || $exception instanceof ManagedProviderDispatchInterruptedException || $exception instanceof ManagedProviderPersistenceException) {
                    throw $exception;
                }
                $attemptEvidence = new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, $attemptNumber, ProviderAttemptOutcome::OutcomeUnknown, RetryClassification::Terminal, null, $this->errorCode($exception));
                $attempts = $this->replaceLastAttempt($attempts, $attemptEvidence);
                $this->resolveManagedAttempt($repository, $authorization, $leaseToken, $attemptEvidence, $leaseSeconds);

                $refusal = $this->managedPostHandoffFailure($exception);

                return $this->persistManagedResult($repository, $authorization, $leaseToken, ProviderExecutionResultData::failed($authorization->providerCallIdempotencyKey, $refusal->value, $this->managedPostHandoffRetryClassification(), $attempts, $refusal), $recoverySeconds);
            }
        }

        return $this->persistManagedResult($repository, $authorization, $leaseToken, ProviderExecutionResultData::failed($authorization->providerCallIdempotencyKey, ContractRefusal::RetryExhausted->value, RetryClassification::Retryable, $attempts, ContractRefusal::RetryExhausted), $recoverySeconds);
    }

    private function persistManagedResult(ManagedProviderCallRepository $repository, ProviderCallAuthorizationData $authorization, string $leaseToken, ProviderExecutionResultData $result, int $recoverySeconds): ProviderExecutionResultData
    {
        try {
            $beforeComplete = $this->config['before_managed_complete'] ?? null;
            if (is_callable($beforeComplete)) {
                $beforeComplete($result);
            }
            $repository->complete($authorization, $leaseToken, $result, $this->now(), $recoverySeconds);
        } catch (Throwable $exception) {
            throw new ManagedProviderPersistenceException($exception);
        }
        $afterCommit = $this->config['after_managed_commit'] ?? null;
        if (is_callable($afterCommit)) {
            try {
                $afterCommit($result);
            } catch (Throwable $exception) {
                throw new ManagedProviderResultCommittedException($exception);
            }
        }

        return $result;
    }

    private function errorCode(Throwable $exception): string
    {
        return str_replace('\\', '.', $exception::class);
    }

    /**
     * @param  list<ProviderAttemptEvidenceData>  $attempts
     * @return list<ProviderAttemptEvidenceData>
     */
    private function replaceLastAttempt(array $attempts, ProviderAttemptEvidenceData $replacement): array
    {
        array_pop($attempts);
        $attempts[] = $replacement;

        return $attempts;
    }

    private function now(): CarbonImmutable
    {
        $now = $this->clock === null ? CarbonImmutable::now() : ($this->clock)();
        if (! $now instanceof CarbonImmutable) {
            throw new LogicException('Managed provider clock must return CarbonImmutable.');
        }

        return $now;
    }

    private function managedLeaseSeconds(): int
    {
        $physicalTimeout = max(1, $this->intConfig('timeout_seconds', 30));
        $persistenceMargin = max(5, $this->intConfig('managed_persistence_margin_seconds', 15));

        return max($physicalTimeout + $persistenceMargin, $this->intConfig('managed_lease_seconds', 120));
    }

    private function managedPostHandoffFailure(Throwable $exception): ContractRefusal
    {
        return ContractRefusal::ProviderOutcomeUnknown;
    }

    private function managedPostHandoffRetryClassification(): RetryClassification
    {
        return RetryClassification::Terminal;
    }

    private function resolveManagedAttempt(ManagedProviderCallRepository $repository, ProviderCallAuthorizationData $authorization, string $leaseToken, ProviderAttemptEvidenceData $attempt, int $leaseSeconds): void
    {
        try {
            $repository->resolveAttempt($authorization, $leaseToken, $attempt, $this->now(), $leaseSeconds);
        } catch (Throwable $exception) {
            throw new ManagedProviderPersistenceException($exception);
        }
    }

    /** @param array<array-key, mixed> $params */
    private function dispatchChat(array $params): AiResponse
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
                    ],
                );
            }
        }

        throw_if($this->isCircuitOpen($model), OpenAICircuitBreakerOpenException::class);

        $idempotencySource = $this->scalarString($params['idempotency_key'] ?? $cacheKey ?? json_encode($requestIdentity));
        $idempotencyKey = hash('sha256', $idempotencySource);
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

                    throw $e;
                }

                $this->recordFailure($model);

                Log::warning('AI API attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->maxRetries) {
                    throw $lastException;
                }

                $delay = $this->retryDelay * (2 ** ($attempt - 1));
                $jitter = random_int(0, (int) ($delay * 0.1));
                Sleep::usleep(($delay + $jitter) * 1000);
            }
        }

        throw $lastException ?? new RuntimeException('Unknown AI provider error');
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

    private function prismRunIdentity(mixed $response): string
    {
        if (is_object($response) && isset($response->meta) && is_object($response->meta) && isset($response->meta->id) && is_scalar($response->meta->id)) {
            return (string) $response->meta->id;
        }

        throw new AiContractRefusedException(ContractRefusal::InvalidUsage, 'Prism did not return a run identity.');
    }
}
