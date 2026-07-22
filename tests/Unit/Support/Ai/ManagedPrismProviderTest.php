<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Contracts\ProviderOutputValidator;
use Capell\AIOrchestrator\Data\Ai\ManagedProviderCallClaimData;
use Capell\AIOrchestrator\Data\Ai\ProviderAttemptEvidenceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAllowanceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ValidatedProviderOutputData;
use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use Capell\AIOrchestrator\Enums\Ai\ManagedProviderClaimStatus;
use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Models\ManagedProviderCall;
use Capell\AIOrchestrator\Support\Ai\ManagedProviderCallRepository;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Tests\Fixtures\AcceptingProviderOutputValidator;
use Capell\AIOrchestrator\Tests\Fixtures\ManagedProviderOverlapState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as PrismTextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

/** @param array<array-key, mixed> $params */
function managedPrismAuthorization(PrismProvider $provider, array $params, int $inputAllowance = 20, int $outputAllowance = 20, int $requestAllowance = 1, string $outputIdentity = 'output', ?ProviderOutputValidator $validator = null): ProviderCallAuthorizationData
{
    $validator ??= managedOutputValidator();

    return new ProviderCallAuthorizationData('opaque-reservation', 'operation-1', 'stage-1', 4, 'EXACT/call-key', $provider->managedRequestDigest($params), $outputIdentity, $validator->policyIdentity(), $validator->policyDigest(), new ProviderCallAllowanceData($requestAllowance, $inputAllowance, $outputAllowance), new DateTimeImmutable('2030-01-01T00:00:00+00:00'));
}

function managedPrismResponseWithoutUsage(string $text): PrismTextResponse
{
    $reflection = new ReflectionClass(PrismTextResponse::class);
    /** @var PrismTextResponse $response */
    $response = $reflection->newInstanceWithoutConstructor();

    foreach (['steps' => collect(), 'text' => $text, 'finishReason' => FinishReason::Stop, 'toolCalls' => [], 'toolResults' => [], 'meta' => new Meta('run-without-usage', 'model-x'), 'messages' => collect(), 'additionalContent' => [], 'raw' => null] as $property => $value) {
        (new ReflectionProperty(PrismTextResponse::class, $property))->setValue($response, $value);
    }

    return $response;
}

function managedOutputValidator(?string $requiredContent = null): AcceptingProviderOutputValidator
{
    return new AcceptingProviderOutputValidator($requiredContent);
}

it('consumes authorization and returns exact per-run prism usage evidence', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([new PrismTextResponse(collect(), 'generated', FinishReason::Stop, [], [], new Usage(11, 7), new Meta('prism-run-123', 'model-x'), collect())]);
    $provider = new PrismProvider(['provider' => 'anthropic', 'model' => 'model-x', 'max_retries' => 1, 'retry_delay_ms' => 0]);

    $params = ['messages' => [['role' => 'user', 'content' => 'Generate']], 'max_tokens' => 20];
    $result = $provider->authorizedChat(managedPrismAuthorization($provider, $params, outputIdentity: 'output-1'), 'operation-1', 'stage-1', 4, 'output-1', managedOutputValidator(), $params);

    $fake->assertRequest(function (array $requests): void {
        expect(data_get($requests[0]->clientOptions(), 'headers.Idempotency-Key'))->toBe(hash('sha256', 'EXACT/call-key'));
    });
    expect($result->providerCallIdentity)->toBe('EXACT/call-key')
        ->and($result->validity)->toBe(ExecutionValidity::Validated)
        ->and($result->retryClassification)->toBe(RetryClassification::NotApplicable)
        ->and($result->usage?->toCanonicalArray())->toMatchArray(['provider_call_identity' => 'EXACT/call-key', 'request_count' => 1, 'input_tokens' => 11, 'output_tokens' => 7, 'provider_identity' => 'anthropic', 'model_identity' => 'model-x', 'provider_run_identity' => 'prism-run-123', 'metering_source' => 'prism']);
});

it('refuses missing prism usage instead of normalizing it to zero', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([managedPrismResponseWithoutUsage('generated')]);
    $provider = new PrismProvider(['max_retries' => 1, 'retry_delay_ms' => 0]);

    $params = ['messages' => [['role' => 'user', 'content' => 'Generate']], 'max_tokens' => 20];
    $result = $provider->authorizedChat(managedPrismAuthorization($provider, $params, outputIdentity: 'output-1'), 'operation-1', 'stage-1', 4, 'output-1', managedOutputValidator(), $params);

    expect($result->validity)->toBe(ExecutionValidity::Invalid)
        ->and($result->refusal)->toBe(ContractRefusal::MissingUsage);
});

it('refuses requested and measured usage beyond the authorization allowance', function (): void {
    $provider = new PrismProvider(['max_tokens' => 30]);
    $result = $provider->authorizedChat(managedPrismAuthorization($provider, [], outputAllowance: 20), 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), []);
    expect($result->validity)->toBe(ExecutionValidity::Refused)
        ->and($result->refusal)->toBe(ContractRefusal::AllowanceExceeded);
});

it('replays the exact managed call locally without reusing the legacy generation cache', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([new PrismTextResponse(collect(), 'generated', FinishReason::Stop, [], [], new Usage(4, 3), new Meta('replay-run', 'model-x'), collect())]);
    $provider = new PrismProvider(['provider' => 'anthropic', 'model' => 'model-x', 'max_retries' => 1]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Replay safely']], 'max_tokens' => 20];
    $authorization = managedPrismAuthorization($provider, $params, outputIdentity: 'output-1');

    $first = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output-1', managedOutputValidator(), $params);
    Cache::flush();
    $second = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output-1', managedOutputValidator(), $params);

    $fake->assertCallCount(1);
    expect($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeTrue()
        ->and($second->usage?->providerRunIdentity)->toBe('replay-run');
});

it('refuses a request that differs from the authorization digest before dispatch', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake();
    $provider = new PrismProvider(['max_retries' => 1]);
    $authorizedParams = ['messages' => [['role' => 'user', 'content' => 'Authorized']], 'max_tokens' => 20];

    $result = $provider->authorizedChat(managedPrismAuthorization($provider, $authorizedParams), 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), ['messages' => [['role' => 'user', 'content' => 'Different']], 'max_tokens' => 20]);
    expect($result->validity)->toBe(ExecutionValidity::Refused)
        ->and($result->refusal)->toBe(ContractRefusal::IdentityMismatch);
    $fake->assertCallCount(0);
});

it('uses only authorization allowances for managed execution', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([new PrismTextResponse(collect(), 'managed', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('guard-run', 'model-x'), collect())]);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1], null, app(ManagedProviderCallRepository::class));
    $params = ['messages' => [['role' => 'user', 'content' => 'Managed only']], 'max_tokens' => 20];

    $success = $provider->authorizedChat(managedPrismAuthorization($provider, $params), 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);
    $refusal = $provider->authorizedChat(managedPrismAuthorization($provider, $params), 'wrong-operation', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    expect($success->validity)->toBe(ExecutionValidity::Validated)
        ->and($refusal->validity)->toBe(ExecutionValidity::Refused);
});

it('continues durable attempt numbering and evidence after lease takeover', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([new PrismTextResponse(collect(), 'recovered', FinishReason::Stop, [], [], new Usage(2, 2), new Meta('takeover-success', 'model-x'), collect())]);
    $repository = app(ManagedProviderCallRepository::class);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 2, 'retry_delay_ms' => 0], null, $repository);
    $params = ['messages' => [['role' => 'user', 'content' => 'Recover']], 'max_tokens' => 20];
    $authorization = managedPrismAuthorization($provider, $params, requestAllowance: 2);
    $past = CarbonImmutable::now()->subMinutes(5);
    $claim = $repository->claim($authorization, $past, 30, 3600);
    $repository->appendAttempt($authorization, (string) $claim->leaseToken, new ProviderAttemptEvidenceData('EXACT/call-key', 1, ProviderAttemptOutcome::HandoffStarted, RetryClassification::NotApplicable, null, null), $past, 30);
    $repository->resolveAttempt($authorization, (string) $claim->leaseToken, new ProviderAttemptEvidenceData('EXACT/call-key', 1, ProviderAttemptOutcome::FailedBeforeUsage, RetryClassification::Retryable, null, 'connection.failed'), $past, 30);

    $result = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    expect($result->validity)->toBe(ExecutionValidity::Validated)
        ->and($result->attempts)->toHaveCount(2)
        ->and($result->attempts[0]->attempt)->toBe(1)
        ->and($result->attempts[1]->attempt)->toBe(2)
        ->and($result->attempts[1]->usage?->providerRunIdentity)->toBe('takeover-success');
});

it('replays without another provider call after response loss following durable commit', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([new PrismTextResponse(collect(), 'committed', FinishReason::Stop, [], [], new Usage(2, 1), new Meta('response-loss-run', 'model-x'), collect())]);
    $repository = app(ManagedProviderCallRepository::class);
    $params = ['messages' => [['role' => 'user', 'content' => 'Commit first']], 'max_tokens' => 20];
    $losingProvider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1, 'after_managed_commit' => static function (): never {
        throw new RuntimeException('response lost');
    }], null, $repository);
    $authorization = managedPrismAuthorization($losingProvider, $params);

    expect(fn () => $losingProvider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params))->toThrow(RuntimeException::class, 'response lost');

    $recoveryProvider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1], null, $repository);
    $replayed = $recoveryProvider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    $fake->assertCallCount(1);
    expect($replayed->replayed)->toBeTrue()
        ->and($replayed->output)->toBe('committed');
});

it('does not repeat an in-doubt physical call after a worker dies at dispatch', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake();
    $repository = app(ManagedProviderCallRepository::class);
    $params = ['messages' => [['role' => 'user', 'content' => 'Dispatch once']], 'max_tokens' => 20];
    $dyingProvider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1, 'after_managed_dispatch' => static function (): never {
        throw new RuntimeException('worker died');
    }], null, $repository);
    $authorization = managedPrismAuthorization($dyingProvider, $params);

    expect(fn () => $dyingProvider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params))->toThrow(RuntimeException::class, 'worker died');
    ManagedProviderCall::query()->firstOrFail()->forceFill(['lease_expires_at' => now()->subSecond()])->save();

    $takeoverProvider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1], null, $repository);
    $result = $takeoverProvider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    $fake->assertCallCount(0);
    expect($result->validity)->toBe(ExecutionValidity::Failed)
        ->and($result->refusal)->toBe(ContractRefusal::ProviderOutcomeUnknown)
        ->and($result->retryClassification)->toBe(RetryClassification::Terminal)
        ->and($result->attempts[0]->outcome)->toBe(ProviderAttemptOutcome::OutcomeUnknown)
        ->and($result->attempts[0]->retryClassification)->toBe(RetryClassification::Terminal);
});

it('uses one injected clock for authorization leases and terminal recovery', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([new PrismTextResponse(collect(), 'clocked', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('clock-run', 'model-x'), collect())]);
    $now = CarbonImmutable::parse('2029-01-01T12:00:00Z');
    $repository = app(ManagedProviderCallRepository::class);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1, 'managed_recovery_seconds' => 7200], null, $repository, static fn (): CarbonImmutable => $now);
    $params = ['messages' => [['role' => 'user', 'content' => 'Use clock']], 'max_tokens' => 20];

    $result = $provider->authorizedChat(managedPrismAuthorization($provider, $params), 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    expect($result->validity)->toBe(ExecutionValidity::Validated)
        ->and(ManagedProviderCall::query()->firstOrFail()->recovery_expires_at?->equalTo($now->addHours(2)))->toBeTrue();
});

it('does not redispatch when provider evidence was recorded but terminal persistence failed', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([new PrismTextResponse(collect(), 'reported', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('reported-run', 'model-x'), collect())]);
    $repository = app(ManagedProviderCallRepository::class);
    $params = ['messages' => [['role' => 'user', 'content' => 'Persist terminal']], 'max_tokens' => 20];
    $failingProvider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1, 'before_managed_complete' => static function (): never {
        throw new RuntimeException('database unavailable');
    }], null, $repository);
    $authorization = managedPrismAuthorization($failingProvider, $params);

    expect(fn () => $failingProvider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params))->toThrow(RuntimeException::class, 'database unavailable');
    ManagedProviderCall::query()->firstOrFail()->forceFill(['lease_expires_at' => now()->subSecond()])->save();

    $takeover = (new PrismProvider(['model' => 'model-x', 'max_retries' => 1], null, $repository))->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    $fake->assertCallCount(1);
    expect($takeover->refusal)->toBe(ContractRefusal::ProviderOutcomeUnknown)
        ->and($takeover->attempts[0]->outcome)->toBe(ProviderAttemptOutcome::Reported);
});

it('requires explicit semantic output validation before promotion', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([new PrismTextResponse(collect(), '{malformed', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('invalid-output-run', 'model-x'), collect())]);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Return JSON']], 'max_tokens' => 20];

    $validator = managedOutputValidator('{"valid":true}');
    $result = $provider->authorizedChat(managedPrismAuthorization($provider, $params, validator: $validator), 'operation-1', 'stage-1', 4, 'output', $validator, $params);

    expect($result->validity)->toBe(ExecutionValidity::Invalid)
        ->and($result->validatedOutputIdentity)->toBeNull()
        ->and($result->validatedOutputDigest)->toBeNull();
});

it('rejects validated output promoted under a different identity', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([new PrismTextResponse(collect(), 'valid content', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('identity-run', 'model-x'), collect())]);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Validate identity']], 'max_tokens' => 20];

    $validator = new AcceptingProviderOutputValidator('valid content', 'different-output');
    $result = $provider->authorizedChat(managedPrismAuthorization($provider, $params, outputIdentity: 'expected-output', validator: $validator), 'operation-1', 'stage-1', 4, 'expected-output', $validator, $params);

    expect($result->validity)->toBe(ExecutionValidity::Invalid)
        ->and($result->validatedOutputIdentity)->toBeNull();
});

it('terminalizes validator exceptions as invalid output while retaining reported usage for replay', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([new PrismTextResponse(collect(), 'provider output', FinishReason::Stop, [], [], new Usage(6, 4), new Meta('validator-throw-run', 'model-x'), collect())]);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Validate safely']], 'max_tokens' => 20];
    $validator = new readonly class implements ProviderOutputValidator
    {
        public function policyIdentity(): string
        {
            return 'throwing-validator:v1';
        }

        public function policyDigest(): string
        {
            return hash('sha256', $this->policyIdentity());
        }

        public function validate(string $outputIdentity, string $providerOutput): ?ValidatedProviderOutputData
        {
            throw new RuntimeException('validator implementation failed');
        }
    };
    $authorization = managedPrismAuthorization($provider, $params, validator: $validator);

    $result = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', $validator, $params);
    $replayed = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', $validator, $params);

    $fake->assertCallCount(1);
    expect($result->validity)->toBe(ExecutionValidity::Invalid)
        ->and($result->refusal)->toBe(ContractRefusal::InvalidOutput)
        ->and($result->attempts[0]->outcome)->toBe(ProviderAttemptOutcome::Reported)
        ->and($result->attempts[0]->usage?->inputTokens)->toBe(6)
        ->and($result->attempts[0]->usage?->outputTokens)->toBe(4)
        ->and($replayed->replayed)->toBeTrue()
        ->and($replayed->canonicalJson())->not->toBe($result->canonicalJson())
        ->and($replayed->validity)->toBe(ExecutionValidity::Invalid)
        ->and($replayed->attempts[0]->usage?->canonicalJson())->toBe($result->attempts[0]->usage?->canonicalJson());
});

it('refuses validated output and validator policy drift under the same durable call key', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $fake = Prism::fake([new PrismTextResponse(collect(), 'accepted', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('stable-policy-run', 'model-x'), collect())]);
    $provider = new PrismProvider(['model' => 'model-x', 'max_retries' => 1]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Stable validation']], 'max_tokens' => 20];
    $policyV1 = new AcceptingProviderOutputValidator(policy: 'permissive:v1');
    $policyV2 = new AcceptingProviderOutputValidator(policy: 'permissive:v2');

    $first = $provider->authorizedChat(managedPrismAuthorization($provider, $params, outputIdentity: 'output:v1', validator: $policyV1), 'operation-1', 'stage-1', 4, 'output:v1', $policyV1, $params);
    $outputDrift = $provider->authorizedChat(managedPrismAuthorization($provider, $params, outputIdentity: 'output:v2', validator: $policyV1), 'operation-1', 'stage-1', 4, 'output:v2', $policyV1, $params);
    $policyDrift = $provider->authorizedChat(managedPrismAuthorization($provider, $params, outputIdentity: 'output:v1', validator: $policyV2), 'operation-1', 'stage-1', 4, 'output:v1', $policyV2, $params);

    $fake->assertCallCount(1);
    expect($first->validity)->toBe(ExecutionValidity::Validated)
        ->and($outputDrift->refusal)->toBe(ContractRefusal::IdentityMismatch)
        ->and($outputDrift->replayed)->toBeFalse()
        ->and($policyDrift->refusal)->toBe(ContractRefusal::IdentityMismatch)
        ->and($policyDrift->replayed)->toBeFalse();
});

it('treats every post-handoff transport response failure as outcome unknown', function (Throwable $failure): void {
    $classifier = new ReflectionMethod(PrismProvider::class, 'managedPostHandoffFailure');
    $retryClassifier = new ReflectionMethod(PrismProvider::class, 'managedPostHandoffRetryClassification');

    expect($classifier->invoke(new PrismProvider, $failure))->toBe(ContractRefusal::ProviderOutcomeUnknown)
        ->and($retryClassifier->invoke(new PrismProvider))->toBe(RetryClassification::Terminal);
})->with([
    'connection loss after receipt' => new ConnectionException('connection lost'),
    'rate limit response' => PrismRateLimitedException::make(),
    'request timeout response' => new PrismException('timeout', 408),
    'conflict response' => new PrismException('conflict', 409),
    'too early response' => new PrismException('too early', 425),
    'server response' => new PrismException('server error', 503),
]);

it('derives a lease beyond the maximum physical timeout and persistence margin', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([new PrismTextResponse(collect(), 'slow result', FinishReason::Stop, [], [], new Usage(1, 1), new Meta('slow-run', 'model-x'), collect())]);
    $repository = app(ManagedProviderCallRepository::class);
    $now = CarbonImmutable::parse('2029-01-01T00:00:00Z');
    $state = new ManagedProviderOverlapState;
    $provider = new PrismProvider([
        'model' => 'model-x',
        'max_retries' => 1,
        'timeout_seconds' => 60,
        'managed_lease_seconds' => 1,
        'managed_persistence_margin_seconds' => 15,
        'after_managed_dispatch' => function () use ($repository, $now, $state): void {
            $authorization = $state->authorization;
            if (! $authorization instanceof ProviderCallAuthorizationData) {
                throw new RuntimeException('Authorization was not prepared.');
            }
            $state->claim = $repository->claim($authorization, $now->addSeconds(74), 75, 3600);
        },
    ], null, $repository, static fn (): CarbonImmutable => $now);
    $params = ['messages' => [['role' => 'user', 'content' => 'Slow request']], 'max_tokens' => 20];
    $authorization = managedPrismAuthorization($provider, $params);
    $state->authorization = $authorization;

    $result = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 4, 'output', managedOutputValidator(), $params);

    $overlapClaim = $state->claim;
    if (! $overlapClaim instanceof ManagedProviderCallClaimData) {
        throw new RuntimeException('Overlap claim was not captured.');
    }
    expect($overlapClaim->status)->toBe(ManagedProviderClaimStatus::InProgress)
        ->and($result->validity)->toBe(ExecutionValidity::Validated);
});
