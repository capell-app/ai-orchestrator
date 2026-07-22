<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class UsableVersionAttachmentData extends CanonicalContract
{
    public function __construct(public string $validatedOutputIdentity, public string $validatedOutputDigest, public string $immutableVersionIdentity, public string $publishingOperationIdentity)
    {
        self::nonEmpty($validatedOutputIdentity, 'validated_output_identity');
        self::nonEmpty($immutableVersionIdentity, 'immutable_version_identity');
        self::nonEmpty($publishingOperationIdentity, 'publishing_operation_identity');
        if (preg_match('/^[a-f0-9]{64}$/', $validatedOutputDigest) !== 1) {
            throw new InvalidArgumentException('validated_output_digest must be a SHA-256 digest.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(self::stringValue($payload, 'validated_output_identity'), self::stringValue($payload, 'validated_output_digest'), self::stringValue($payload, 'immutable_version_identity'), self::stringValue($payload, 'publishing_operation_identity'));
    }

    public static function fromValidatedExecution(ProviderExecutionResultData $execution, string $immutableVersionIdentity, string $publishingOperationIdentity): self
    {
        if ($execution->validity !== ExecutionValidity::Validated || $execution->validatedOutputIdentity === null || $execution->validatedOutputDigest === null) {
            throw new InvalidArgumentException('Usable-version attachment requires validated execution evidence.');
        }

        return new self($execution->validatedOutputIdentity, $execution->validatedOutputDigest, $immutableVersionIdentity, $publishingOperationIdentity);
    }

    public function matches(ProviderExecutionResultData $execution): bool
    {
        return $execution->validity === ExecutionValidity::Validated && $execution->validatedOutputIdentity === $this->validatedOutputIdentity && hash_equals((string) $execution->validatedOutputDigest, $this->validatedOutputDigest);
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['immutable_version_identity' => $this->immutableVersionIdentity, 'publishing_operation_identity' => $this->publishingOperationIdentity, 'schema_version' => $this->schemaVersion(), 'validated_output_digest' => $this->validatedOutputDigest, 'validated_output_identity' => $this->validatedOutputIdentity];
    }
}
