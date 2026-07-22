<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Exceptions\AiContractRefusedException;
use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class ProviderCallAuthorizationData extends CanonicalContract
{
    public function __construct(
        public string $reservationReference,
        public string $operationIdentity,
        public string $stageIdentity,
        public int $fencingToken,
        public string $providerCallIdempotencyKey,
        public string $requestDigest,
        public string $validatedOutputIdentity,
        public string $validatorPolicyIdentity,
        public string $validatorPolicyDigest,
        public ProviderCallAllowanceData $allowance,
        public DateTimeImmutable $deadline,
    ) {
        self::nonEmpty($reservationReference, 'reservation_reference');
        self::nonEmpty($operationIdentity, 'operation_identity');
        self::nonEmpty($stageIdentity, 'stage_identity');
        self::nonEmpty($providerCallIdempotencyKey, 'provider_call_idempotency_key');
        self::nonEmpty($validatedOutputIdentity, 'validated_output_identity');
        self::nonEmpty($validatorPolicyIdentity, 'validator_policy_identity');
        if (preg_match('/^[a-f0-9]{64}$/', $requestDigest) !== 1) {
            throw new InvalidArgumentException('request_digest must be a SHA-256 digest.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $validatorPolicyDigest) !== 1) {
            throw new InvalidArgumentException('validator_policy_digest must be a SHA-256 digest.');
        }
        if ($fencingToken < 1) {
            throw new InvalidArgumentException('fencing_token must be positive.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(self::stringValue($payload, 'reservation_reference'), self::stringValue($payload, 'operation_identity'), self::stringValue($payload, 'stage_identity'), self::integerValue($payload, 'fencing_token'), self::stringValue($payload, 'provider_call_idempotency_key'), self::stringValue($payload, 'request_digest'), self::stringValue($payload, 'validated_output_identity'), self::stringValue($payload, 'validator_policy_identity'), self::stringValue($payload, 'validator_policy_digest'), ProviderCallAllowanceData::fromCanonicalArray(self::arrayValue($payload, 'allowance')), new DateTimeImmutable(self::stringValue($payload, 'deadline')));
    }

    public function providerHeaderIdentity(): ProviderIdempotencyHeaderData
    {
        return new ProviderIdempotencyHeaderData(hash('sha256', $this->providerCallIdempotencyKey));
    }

    public function assertUsable(string $operationIdentity, string $stageIdentity, int $fencingToken, DateTimeImmutable $now): void
    {
        if ($this->operationIdentity !== $operationIdentity || $this->stageIdentity !== $stageIdentity) {
            throw new AiContractRefusedException(ContractRefusal::IdentityMismatch, 'The authorization operation or stage identity does not match.');
        }
        if ($this->fencingToken !== $fencingToken) {
            throw new AiContractRefusedException(ContractRefusal::StaleFence, 'The authorization fencing token is stale.');
        }
        if ($now > $this->deadline) {
            throw new AiContractRefusedException(ContractRefusal::DeadlineExceeded, 'The authorization deadline has passed.');
        }
        if ($this->allowance->requestCount < 1) {
            throw new AiContractRefusedException(ContractRefusal::AllowanceExceeded, 'The authorization does not allow a provider request.');
        }
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['allowance' => $this->allowance->toCanonicalArray(), 'deadline' => $this->deadline->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s.u\\Z'), 'fencing_token' => $this->fencingToken, 'operation_identity' => $this->operationIdentity, 'provider_call_idempotency_key' => $this->providerCallIdempotencyKey, 'request_digest' => $this->requestDigest, 'reservation_reference' => $this->reservationReference, 'schema_version' => $this->schemaVersion(), 'stage_identity' => $this->stageIdentity, 'validated_output_identity' => $this->validatedOutputIdentity, 'validator_policy_digest' => $this->validatorPolicyDigest, 'validator_policy_identity' => $this->validatorPolicyIdentity];
    }
}
