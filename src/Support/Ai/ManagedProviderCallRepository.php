<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Data\Ai\ManagedProviderCallClaimData;
use Capell\AIOrchestrator\Data\Ai\ProviderAttemptEvidenceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ProviderExecutionResultData;
use Capell\AIOrchestrator\Enums\Ai\ManagedProviderCallState;
use Capell\AIOrchestrator\Enums\Ai\ManagedProviderClaimStatus;
use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Models\ManagedProviderCall;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ManagedProviderCallRepository
{
    public function __construct(private ConnectionInterface $database) {}

    /**
     * Atomically acquires one lease per exact caller key and canonical authorization.
     * Active leases refuse concurrent workers, expired leases permit takeover, and
     * terminal results replay until the terminal-relative recovery horizon expires.
     */
    public function claim(ProviderCallAuthorizationData $authorization, CarbonImmutable $now, int $leaseSeconds, int $recoverySeconds): ManagedProviderCallClaimData
    {
        $claim = $this->database->transaction(function () use ($authorization, $now, $leaseSeconds, $recoverySeconds): ManagedProviderCallClaimData {
            $callKeyDigest = hash('sha256', $authorization->providerCallIdempotencyKey);
            ManagedProviderCall::query()->createOrFirst(
                ['call_key_digest' => $callKeyDigest],
                ['authorization_digest' => $authorization->digest(), 'request_digest' => $authorization->requestDigest, 'state' => ManagedProviderCallState::Running, 'lease_token' => null, 'attempts' => [], 'recovery_expires_at' => $now->addSeconds($recoverySeconds)],
            );
            $call = ManagedProviderCall::query()->where('call_key_digest', $callKeyDigest)->lockForUpdate()->firstOrFail();

            if (! hash_equals($call->authorization_digest, $authorization->digest()) || ! hash_equals($call->request_digest, $authorization->requestDigest)) {
                return new ManagedProviderCallClaimData(ManagedProviderClaimStatus::IdentityMismatch);
            }
            if ($call->state === ManagedProviderCallState::Terminal) {
                if ($call->recovery_expires_at->isBefore($now)) {
                    return new ManagedProviderCallClaimData(ManagedProviderClaimStatus::RecoveryExpired);
                }
                if (! is_array($call->terminal_result)) {
                    throw new RuntimeException('Terminal provider call is missing its immutable result.');
                }

                return new ManagedProviderCallClaimData(ManagedProviderClaimStatus::Replayed, replayedResult: ProviderExecutionResultData::fromCanonicalArray($call->terminal_result));
            }
            if ($call->lease_token !== null && $call->lease_expires_at?->isAfter($now)) {
                return new ManagedProviderCallClaimData(ManagedProviderClaimStatus::InProgress);
            }

            $leaseToken = (string) Str::uuid();
            $call->forceFill(['lease_token' => $leaseToken, 'lease_expires_at' => $now->addSeconds($leaseSeconds)])->save();

            return new ManagedProviderCallClaimData(ManagedProviderClaimStatus::Acquired, $leaseToken, attempts: $this->hydrateAttempts($call->attempts));
        }, 3);
        if (! $claim instanceof ManagedProviderCallClaimData) {
            throw new RuntimeException('Managed provider-call claim transaction returned an invalid result.');
        }

        return $claim;
    }

    public function appendAttempt(ProviderCallAuthorizationData $authorization, string $leaseToken, ProviderAttemptEvidenceData $attempt, CarbonImmutable $now, int $leaseSeconds): void
    {
        $this->database->transaction(function () use ($authorization, $leaseToken, $attempt, $now, $leaseSeconds): void {
            $call = $this->ownedRunningCall($authorization, $leaseToken);
            if ($attempt->providerCallIdentity !== $authorization->providerCallIdempotencyKey || $attempt->outcome !== ProviderAttemptOutcome::HandoffStarted) {
                throw new RuntimeException('Managed provider attempt identity does not match its authorization.');
            }
            $attempts = $call->attempts;
            if ($attempt->attempt !== count($attempts) + 1) {
                throw new RuntimeException('Managed provider attempt sequence is not contiguous.');
            }
            $attempts[] = $attempt->toCanonicalArray();
            $call->forceFill(['attempts' => $attempts, 'lease_expires_at' => $now->addSeconds($leaseSeconds)])->save();
        }, 3);
    }

    public function resolveAttempt(ProviderCallAuthorizationData $authorization, string $leaseToken, ProviderAttemptEvidenceData $attempt, CarbonImmutable $now, int $leaseSeconds): void
    {
        $this->database->transaction(function () use ($authorization, $leaseToken, $attempt, $now, $leaseSeconds): void {
            $call = $this->ownedRunningCall($authorization, $leaseToken);
            $attempts = $call->attempts;
            $index = $attempt->attempt - 1;
            $existing = $attempts[$index] ?? null;
            if (! is_array($existing) || ($existing['outcome'] ?? null) !== ProviderAttemptOutcome::HandoffStarted->value || $attempt->providerCallIdentity !== $authorization->providerCallIdempotencyKey) {
                throw new RuntimeException('Managed provider attempt resolution does not match a durable dispatch.');
            }
            $attempts[$index] = $attempt->toCanonicalArray();
            $call->forceFill(['attempts' => $attempts, 'lease_expires_at' => $now->addSeconds($leaseSeconds)])->save();
        }, 3);
    }

    public function complete(ProviderCallAuthorizationData $authorization, string $leaseToken, ProviderExecutionResultData $result, CarbonImmutable $now, int $recoverySeconds): void
    {
        $this->database->transaction(function () use ($authorization, $leaseToken, $result, $now, $recoverySeconds): void {
            $call = $this->ownedRunningCall($authorization, $leaseToken);
            if ($result->providerCallIdentity !== $authorization->providerCallIdempotencyKey || $this->canonicalAttempts($result->attempts) !== $call->attempts) {
                throw new RuntimeException('Managed provider terminal result does not match its durable attempt ledger.');
            }
            $call->forceFill(['state' => ManagedProviderCallState::Terminal, 'lease_token' => null, 'lease_expires_at' => null, 'terminal_result' => $result->toCanonicalArray(), 'terminal_at' => $now, 'recovery_expires_at' => $now->addSeconds($recoverySeconds)])->save();
        }, 3);
    }

    private function ownedRunningCall(ProviderCallAuthorizationData $authorization, string $leaseToken): ManagedProviderCall
    {
        $call = ManagedProviderCall::query()->where('call_key_digest', hash('sha256', $authorization->providerCallIdempotencyKey))->lockForUpdate()->firstOrFail();
        if ($call->state !== ManagedProviderCallState::Running || $call->lease_token !== $leaseToken || ! hash_equals($call->authorization_digest, $authorization->digest())) {
            throw new RuntimeException('Managed provider-call lease is no longer owned by this worker.');
        }

        return $call;
    }

    /** @param array<int, array<string, mixed>> $attempts
     * @return list<ProviderAttemptEvidenceData>
     */
    private function hydrateAttempts(array $attempts): array
    {
        return array_map(static fn (array $attempt): ProviderAttemptEvidenceData => ProviderAttemptEvidenceData::fromCanonicalArray($attempt), array_values($attempts));
    }

    /** @param list<ProviderAttemptEvidenceData> $attempts
     * @return list<array<string, mixed>>
     */
    private function canonicalAttempts(array $attempts): array
    {
        return array_map(static fn (ProviderAttemptEvidenceData $attempt): array => $attempt->toCanonicalArray(), $attempts);
    }
}
