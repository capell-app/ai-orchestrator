<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\Ai\MeasuredUsageData;
use Capell\AIOrchestrator\Data\Ai\ProviderAttemptEvidenceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAllowanceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ProviderExecutionResultData;
use Capell\AIOrchestrator\Enums\Ai\ManagedProviderClaimStatus;
use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Models\ManagedProviderCall;
use Capell\AIOrchestrator\Support\Ai\ManagedProviderCallRepository;
use Carbon\CarbonImmutable;

function durableAuthorization(string $callKey = 'durable-call', string $request = 'request-a', string $outputIdentity = 'output', string $policyIdentity = 'policy:v1'): ProviderCallAuthorizationData
{
    return new ProviderCallAuthorizationData('reservation', 'operation', 'stage', 1, $callKey, hash('sha256', $request), $outputIdentity, $policyIdentity, hash('sha256', $policyIdentity), new ProviderCallAllowanceData(2, 100, 100), new DateTimeImmutable('2030-01-01T00:00:00Z'));
}

function durableResult(ProviderCallAuthorizationData $authorization): ProviderExecutionResultData
{
    $usage = new MeasuredUsageData($authorization->providerCallIdempotencyKey, 1, 4, 3, 'provider', 'model', 'run', 'prism', true);
    $attempt = new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, 1, ProviderAttemptOutcome::Reported, RetryClassification::NotApplicable, $usage, null);

    return ProviderExecutionResultData::validated($authorization->providerCallIdempotencyKey, 'output', 'content', $usage, [$attempt]);
}

function recordDurableAttempt(ManagedProviderCallRepository $repository, ProviderCallAuthorizationData $authorization, string $leaseToken, ProviderAttemptEvidenceData $resolved, CarbonImmutable $now): void
{
    $repository->appendAttempt($authorization, $leaseToken, new ProviderAttemptEvidenceData($authorization->providerCallIdempotencyKey, $resolved->attempt, ProviderAttemptOutcome::HandoffStarted, RetryClassification::NotApplicable, null, null), $now, 120);
    $repository->resolveAttempt($authorization, $leaseToken, $resolved, $now, 120);
}

it('atomically gives one worker the lease and reports concurrent claims in progress', function (): void {
    $repository = app(ManagedProviderCallRepository::class);
    $authorization = durableAuthorization();
    $now = CarbonImmutable::parse('2029-01-01T00:00:00Z');

    $first = $repository->claim($authorization, $now, 120, 3600);
    $concurrent = app(ManagedProviderCallRepository::class)->claim($authorization, $now->addSecond(), 120, 3600);

    expect($first->leaseToken)->not->toBeNull()
        ->and($concurrent->status)->toBe(ManagedProviderClaimStatus::InProgress)
        ->and(ManagedProviderCall::query()->count())->toBe(1);
});

it('rejects output identity and validator policy drift before durable takeover or replay', function (): void {
    $repository = app(ManagedProviderCallRepository::class);
    $authorization = durableAuthorization('validation-boundary-call');
    $now = CarbonImmutable::parse('2029-01-01T00:00:00Z');
    $claim = $repository->claim($authorization, $now, 120, 3600);
    $result = durableResult($authorization);
    recordDurableAttempt($repository, $authorization, (string) $claim->leaseToken, $result->attempts[0], $now);
    $repository->complete($authorization, (string) $claim->leaseToken, $result, $now->addSecond(), 3600);

    $outputDrift = $repository->claim(durableAuthorization('validation-boundary-call', outputIdentity: 'output:v2'), $now->addSeconds(2), 120, 3600);
    $policyDrift = $repository->claim(durableAuthorization('validation-boundary-call', policyIdentity: 'policy:v2'), $now->addSeconds(2), 120, 3600);

    expect($outputDrift->status)->toBe(ManagedProviderClaimStatus::IdentityMismatch)
        ->and($outputDrift->replayedResult)->toBeNull()
        ->and($policyDrift->status)->toBe(ManagedProviderClaimStatus::IdentityMismatch)
        ->and($policyDrift->replayedResult)->toBeNull();
});

it('takes over an expired lease while retaining earlier attempt evidence', function (): void {
    $repository = app(ManagedProviderCallRepository::class);
    $authorization = durableAuthorization('takeover-call');
    $now = CarbonImmutable::parse('2029-01-01T00:00:00Z');
    $first = $repository->claim($authorization, $now, 30, 3600);
    $usage = new MeasuredUsageData('takeover-call', 1, 1, 1, 'p', 'm', 'run-1', 'prism', true);
    recordDurableAttempt($repository, $authorization, (string) $first->leaseToken, new ProviderAttemptEvidenceData('takeover-call', 1, ProviderAttemptOutcome::Reported, RetryClassification::NotApplicable, $usage, null), $now);

    $takeover = $repository->claim($authorization, $now->addSeconds(121), 30, 3600);

    expect($takeover->leaseToken)->not->toBeNull()->not->toBe($first->leaseToken)
        ->and(ManagedProviderCall::query()->firstOrFail()->attempts)->toHaveCount(1);
});

it('durably replays after response loss and process cache restart', function (): void {
    $repository = app(ManagedProviderCallRepository::class);
    $authorization = durableAuthorization('response-loss-call');
    $now = CarbonImmutable::parse('2029-01-01T00:00:00Z');
    $claim = $repository->claim($authorization, $now, 120, 3600);
    $result = durableResult($authorization);
    recordDurableAttempt($repository, $authorization, (string) $claim->leaseToken, $result->attempts[0], $now);
    $repository->complete($authorization, (string) $claim->leaseToken, $result, $now->addSecond(), 3600);
    cache()->flush();

    $replay = app(ManagedProviderCallRepository::class)->claim($authorization, $now->addSeconds(2), 120, 3600);

    expect($replay->replayedResult?->providerCallIdentity)->toBe('response-loss-call')
        ->and($replay->replayedResult?->attempts)->toHaveCount(1);
});

it('refuses identity drift and terminal recovery after the approved horizon', function (): void {
    $repository = app(ManagedProviderCallRepository::class);
    $authorization = durableAuthorization('bounded-recovery-call');
    $now = CarbonImmutable::parse('2029-01-01T00:00:00Z');
    $claim = $repository->claim($authorization, $now, 120, 60);
    $result = durableResult($authorization);
    recordDurableAttempt($repository, $authorization, (string) $claim->leaseToken, $result->attempts[0], $now);
    $repository->complete($authorization, (string) $claim->leaseToken, $result, $now->addSecond(), 60);

    $mismatch = $repository->claim(durableAuthorization('bounded-recovery-call', 'different'), $now->addSeconds(2), 120, 60);
    $expired = $repository->claim($authorization, $now->addSeconds(62), 120, 60);

    expect($mismatch->leaseToken)->toBeNull()
        ->and($mismatch->replayedResult)->toBeNull()
        ->and($expired->status)->toBe(ManagedProviderClaimStatus::RecoveryExpired);
});

it('rejects attempt and terminal evidence that differs from the authorization ledger', function (): void {
    $repository = app(ManagedProviderCallRepository::class);
    $authorization = durableAuthorization('ledger-call');
    $now = CarbonImmutable::now();
    $claim = $repository->claim($authorization, $now, 120, 3600);
    $otherUsage = new MeasuredUsageData('other-call', 1, 1, 1, 'p', 'm', 'run', 'prism', true);
    $otherAttempt = new ProviderAttemptEvidenceData('other-call', 1, ProviderAttemptOutcome::Reported, RetryClassification::NotApplicable, $otherUsage, null);

    expect(fn () => $repository->appendAttempt($authorization, (string) $claim->leaseToken, $otherAttempt, $now, 120))->toThrow(RuntimeException::class, 'identity');

    $result = durableResult($authorization);
    expect(fn () => $repository->complete($authorization, (string) $claim->leaseToken, $result, $now, 3600))->toThrow(RuntimeException::class, 'attempt ledger');
});
