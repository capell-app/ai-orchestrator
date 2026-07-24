<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class ManagedProviderExecutionReceiptData extends CanonicalContract
{
    public function __construct(
        public string $providerCallIdentity,
        public string $requestDigest,
        public string $validatorPolicyIdentity,
        public string $validatorPolicyDigest,
        public string $validatedOutputIdentity,
        public string $validatedOutputDigest,
        public string $authorizationDigest,
        public string $executionDigest,
        public string $usageDigest,
        public bool $replayed,
    ) {
        self::nonEmpty($providerCallIdentity, 'provider_call_identity');
        self::nonEmpty($validatorPolicyIdentity, 'validator_policy_identity');
        self::nonEmpty($validatedOutputIdentity, 'validated_output_identity');

        foreach ([
            'request_digest' => $requestDigest,
            'validator_policy_digest' => $validatorPolicyDigest,
            'validated_output_digest' => $validatedOutputDigest,
            'authorization_digest' => $authorizationDigest,
            'execution_digest' => $executionDigest,
            'usage_digest' => $usageDigest,
        ] as $field => $digest) {
            if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
                throw new InvalidArgumentException("{$field} must be a SHA-256 digest.");
            }
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(
            self::stringValue($payload, 'provider_call_identity'),
            self::stringValue($payload, 'request_digest'),
            self::stringValue($payload, 'validator_policy_identity'),
            self::stringValue($payload, 'validator_policy_digest'),
            self::stringValue($payload, 'validated_output_identity'),
            self::stringValue($payload, 'validated_output_digest'),
            self::stringValue($payload, 'authorization_digest'),
            self::stringValue($payload, 'execution_digest'),
            self::stringValue($payload, 'usage_digest'),
            self::booleanValue($payload, 'replayed'),
        );
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return [
            'authorization_digest' => $this->authorizationDigest,
            'execution_digest' => $this->executionDigest,
            'provider_call_identity' => $this->providerCallIdentity,
            'replayed' => $this->replayed,
            'request_digest' => $this->requestDigest,
            'schema_version' => $this->schemaVersion(),
            'usage_digest' => $this->usageDigest,
            'validated_output_digest' => $this->validatedOutputDigest,
            'validated_output_identity' => $this->validatedOutputIdentity,
            'validator_policy_digest' => $this->validatorPolicyDigest,
            'validator_policy_identity' => $this->validatorPolicyIdentity,
        ];
    }
}
