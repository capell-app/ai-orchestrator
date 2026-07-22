<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Exceptions\AiContractRefusedException;
use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class MeasuredUsageData extends CanonicalContract
{
    public function __construct(
        public string $providerCallIdentity,
        public int $requestCount,
        public int $inputTokens,
        public int $outputTokens,
        public string $providerIdentity,
        public string $modelIdentity,
        public string $providerRunIdentity,
        public string $meteringSource,
        public bool $providerReported,
    ) {
        self::nonEmpty($providerCallIdentity, 'provider_call_identity');
        self::nonEmpty($providerIdentity, 'provider_identity');
        self::nonEmpty($modelIdentity, 'model_identity');
        self::nonEmpty($providerRunIdentity, 'provider_run_identity');
        self::nonEmpty($meteringSource, 'metering_source');
        self::nonNegative($requestCount, 'request_count');
        self::nonNegative($inputTokens, 'input_tokens');
        self::nonNegative($outputTokens, 'output_tokens');
        if (! $providerReported) {
            throw new AiContractRefusedException(ContractRefusal::MissingUsage, 'Provider-reported usage evidence is required.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(self::stringValue($payload, 'provider_call_identity'), self::integer($payload, 'request_count'), self::integer($payload, 'input_tokens'), self::integer($payload, 'output_tokens'), self::stringValue($payload, 'provider_identity'), self::stringValue($payload, 'model_identity'), self::stringValue($payload, 'provider_run_identity'), self::stringValue($payload, 'metering_source'), self::booleanValue($payload, 'provider_reported'));
    }

    public function assertWithin(ProviderCallAuthorizationData $authorization): void
    {
        if ($this->providerCallIdentity !== $authorization->providerCallIdempotencyKey) {
            throw new AiContractRefusedException(ContractRefusal::IdentityMismatch, 'Usage does not match the authorized provider call.');
        }
        if ($this->requestCount > $authorization->allowance->requestCount || $this->inputTokens > $authorization->allowance->inputTokens || $this->outputTokens > $authorization->allowance->outputTokens) {
            throw new AiContractRefusedException(ContractRefusal::AllowanceExceeded, 'Measured usage exceeds the authorized allowance.');
        }
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['input_tokens' => $this->inputTokens, 'metering_source' => $this->meteringSource, 'model_identity' => $this->modelIdentity, 'output_tokens' => $this->outputTokens, 'provider_call_identity' => $this->providerCallIdentity, 'provider_identity' => $this->providerIdentity, 'provider_reported' => $this->providerReported, 'provider_run_identity' => $this->providerRunIdentity, 'request_count' => $this->requestCount, 'schema_version' => $this->schemaVersion()];
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        if (! isset($payload[$key]) || ! is_int($payload[$key])) {
            throw new InvalidArgumentException("{$key} must be an integer.");
        }

        return $payload[$key];
    }
}
