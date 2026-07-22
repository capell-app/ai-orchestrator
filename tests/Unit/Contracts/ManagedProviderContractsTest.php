<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\Ai\MeasuredUsageData;
use Capell\AIOrchestrator\Data\Ai\ProviderAttemptEvidenceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAllowanceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ProviderExecutionResultData;
use Capell\AIOrchestrator\Data\Ai\TerminalSettlementResultData;
use Capell\AIOrchestrator\Data\Ai\UsableVersionAttachmentData;
use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Enums\Ai\SettlementReplayStatus;
use Capell\AIOrchestrator\Exceptions\AiContractRefusedException;

function managedAuthorization(DateTimeImmutable $deadline = new DateTimeImmutable('2030-01-01T00:00:00+00:00')): ProviderCallAuthorizationData
{
    return new ProviderCallAuthorizationData('reservation:opaque', 'operation:123', 'stage:456', 7, 'caller-key/EXACT:Ä', hash('sha256', 'request'), 'output:1', 'json-schema:v1', hash('sha256', 'json-schema:v1'), new ProviderCallAllowanceData(1, 100, 50), $deadline);
}

it('round trips authorization canonically and preserves the exact provider call identity', function (): void {
    $authorization = managedAuthorization();
    $roundTrip = ProviderCallAuthorizationData::fromCanonicalArray($authorization->toCanonicalArray());

    expect($roundTrip->canonicalJson())->toBe($authorization->canonicalJson())
        ->and($roundTrip->digest())->toHaveLength(64)
        ->and($roundTrip->providerCallIdempotencyKey)->toBe('caller-key/EXACT:Ä')
        ->and($roundTrip->providerHeaderIdentity()->value)->toBe(hash('sha256', 'caller-key/EXACT:Ä'));
});

it('rejects unknown schemas, mismatched identities, stale fences, deadlines, and empty allowance', function (): void {
    $payload = managedAuthorization()->toCanonicalArray();
    $payload['schema_version'] = 2;

    expect(fn (): ProviderCallAuthorizationData => ProviderCallAuthorizationData::fromCanonicalArray($payload))->toThrow(InvalidArgumentException::class)
        ->and(fn () => managedAuthorization()->assertUsable('other', 'stage:456', 7, new DateTimeImmutable('2029-01-01')))->toThrow(AiContractRefusedException::class, 'identity')
        ->and(fn () => managedAuthorization()->assertUsable('operation:123', 'stage:456', 8, new DateTimeImmutable('2029-01-01')))->toThrow(AiContractRefusedException::class, 'stale')
        ->and(fn () => managedAuthorization(new DateTimeImmutable('2025-01-01'))->assertUsable('operation:123', 'stage:456', 7, new DateTimeImmutable('2025-01-02')))->toThrow(AiContractRefusedException::class, 'deadline')
        ->and(fn () => (new ProviderCallAuthorizationData('r', 'o', 's', 1, 'k', hash('sha256', 'request'), 'output', 'policy', hash('sha256', 'policy'), new ProviderCallAllowanceData(0, 1, 1), new DateTimeImmutable('2030-01-01')))->assertUsable('o', 's', 1, new DateTimeImmutable('2029-01-01')))->toThrow(AiContractRefusedException::class, 'provider request');
});

it('binds validated output and usable version evidence by identity and digest', function (): void {
    $usage = new MeasuredUsageData('call', 1, 10, 5, 'provider-x', 'model-y', 'provider-run', 'adapter-x', true);
    $execution = ProviderExecutionResultData::validated('call', 'output:1', '{"valid":true}', $usage);
    $attachment = new UsableVersionAttachmentData('output:1', hash('sha256', '{"valid":true}'), 'version:immutable', 'publish:operation');

    expect($attachment->matches($execution))->toBeTrue()
        ->and($execution->validatedOutputDigest)->toBe('8daf09a6fc31937457dd77e9c25ce4b21349d605b561a8c5d557841bf964c9a0')
        ->and(ProviderExecutionResultData::fromCanonicalArray($execution->toCanonicalArray())->digest())->toBe($execution->digest())
        ->and(UsableVersionAttachmentData::fromCanonicalArray($attachment->toCanonicalArray())->digest())->toBe($attachment->digest());
});

it('validates usage allowance and provider-neutral identities', function (): void {
    $usage = new MeasuredUsageData('caller-key/EXACT:Ä', 1, 100, 50, 'any-provider', 'any-model', 'provider-run-9', 'any-adapter', true);

    expect(fn () => $usage->assertWithin(managedAuthorization()))->not->toThrow(Throwable::class)
        ->and($usage->toCanonicalArray())->toMatchArray(['provider_identity' => 'any-provider', 'model_identity' => 'any-model', 'provider_run_identity' => 'provider-run-9', 'metering_source' => 'any-adapter'])
        ->and(fn (): MeasuredUsageData => new MeasuredUsageData('call', 1, 0, 0, 'p', 'm', 'run', 'adapter', false))->toThrow(AiContractRefusedException::class, 'required')
        ->and(fn () => (new MeasuredUsageData('caller-key/EXACT:Ä', 1, 101, 0, 'p', 'm', 'run', 'adapter', true))->assertWithin(managedAuthorization()))->toThrow(AiContractRefusedException::class, 'exceeds');
});

it('round trips terminal settlement outcomes without financial dependencies', function (): void {
    $settlement = new TerminalSettlementResultData(73, 27, 'transition:terminal', SettlementReplayStatus::Replayed, ContractRefusal::None, null);

    expect(TerminalSettlementResultData::fromCanonicalArray($settlement->toCanonicalArray())->canonicalJson())->toBe($settlement->canonicalJson())
        ->and($settlement->toCanonicalArray())->toMatchArray(['settled_amount' => 73, 'released_amount' => 27, 'replay_status' => 'replayed']);
});

it('rejects contradictory execution, attachment, and settlement states', function (): void {
    $usage = new MeasuredUsageData('call', 1, 1, 1, 'p', 'm', 'run', 'adapter', true);
    $failed = ProviderExecutionResultData::failed('call', 'provider.failed', RetryClassification::Terminal, []);

    expect(fn () => new ProviderExecutionResultData('call', ExecutionValidity::Validated, 'output', hash('sha256', ''), RetryClassification::NotApplicable, ContractRefusal::None, null, $usage, ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ProviderExecutionResultData('call', ExecutionValidity::Refused, 'output', hash('sha256', 'x'), RetryClassification::Terminal, ContractRefusal::AllowanceExceeded, 'error', null, 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => UsableVersionAttachmentData::fromValidatedExecution($failed, 'version', 'publish'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TerminalSettlementResultData(1, 0, 'transition', SettlementReplayStatus::Refused, ContractRefusal::SettlementMismatch, 'error'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TerminalSettlementResultData(0, 0, 'transition', SettlementReplayStatus::Refused, ContractRefusal::None, null))->toThrow(InvalidArgumentException::class);
});

it('reads legacy v1 execution payloads without attempt evidence', function (): void {
    $usage = new MeasuredUsageData('call', 1, 1, 1, 'p', 'm', 'run', 'adapter', true);
    $payload = ProviderExecutionResultData::validated('call', 'output', 'content', $usage)->toCanonicalArray();
    unset($payload['attempts']);

    expect(ProviderExecutionResultData::fromCanonicalArray($payload)->attempts)->toBe([]);
});

it('rejects contradictory durable attempt evidence', function (): void {
    $usage = new MeasuredUsageData('call', 1, 1, 1, 'p', 'm', 'run', 'adapter', true);

    expect(fn () => new ProviderAttemptEvidenceData('call', 1, ProviderAttemptOutcome::Reported, RetryClassification::Retryable, $usage, 'error'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ProviderAttemptEvidenceData('call', 1, ProviderAttemptOutcome::InvalidUsage, RetryClassification::NotApplicable, null, null))->toThrow(InvalidArgumentException::class);
});

it('binds validated aggregate usage to the final provider-reported attempt', function (): void {
    $reported = new MeasuredUsageData('call', 1, 2, 1, 'p', 'm', 'run', 'adapter', true);
    $different = new MeasuredUsageData('call', 1, 3, 1, 'p', 'm', 'run', 'adapter', true);
    $attempt = new ProviderAttemptEvidenceData('call', 1, ProviderAttemptOutcome::Reported, RetryClassification::NotApplicable, $reported, null);

    expect(fn () => ProviderExecutionResultData::validated('call', 'output', 'content', $different, [$attempt]))->toThrow(InvalidArgumentException::class, 'aggregate usage');
});
