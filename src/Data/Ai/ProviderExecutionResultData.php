<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class ProviderExecutionResultData extends CanonicalContract
{
    public function __construct(
        public string $providerCallIdentity,
        public ExecutionValidity $validity,
        public ?string $validatedOutputIdentity,
        public ?string $validatedOutputDigest,
        public RetryClassification $retryClassification,
        public ContractRefusal $refusal,
        public ?string $errorCode,
        public ?MeasuredUsageData $usage,
        public ?string $output = null,
        public bool $replayed = false,
        /** @var list<ProviderAttemptEvidenceData> */
        public array $attempts = [],
    ) {
        self::nonEmpty($providerCallIdentity, 'provider_call_identity');
        if ($validity === ExecutionValidity::Validated && ($validatedOutputIdentity === null || $output === null || $output === '' || preg_match('/^[a-f0-9]{64}$/', (string) $validatedOutputDigest) !== 1 || $usage === null || $refusal !== ContractRefusal::None || $errorCode !== null)) {
            throw new InvalidArgumentException('Validated execution requires non-empty output evidence and usage without refusal or error.');
        }
        if ($validity === ExecutionValidity::Validated && ($retryClassification !== RetryClassification::NotApplicable || $usage->providerCallIdentity !== $providerCallIdentity)) {
            throw new InvalidArgumentException('Validated execution retry and usage identities must match its call.');
        }
        if ($validity !== ExecutionValidity::Validated && ($validatedOutputIdentity !== null || $validatedOutputDigest !== null || $output !== null)) {
            throw new InvalidArgumentException('Only validated execution may carry output evidence.');
        }
        if ($validity !== ExecutionValidity::Validated && $usage !== null) {
            throw new InvalidArgumentException('Only validated execution may carry aggregate measured usage.');
        }
        if ($validity === ExecutionValidity::Refused && ($refusal === ContractRefusal::None || $errorCode === null || $retryClassification === RetryClassification::NotApplicable)) {
            throw new InvalidArgumentException('Refused execution requires refusal and error classification.');
        }
        if ($validity === ExecutionValidity::Invalid && ($refusal === ContractRefusal::None || $errorCode === null || $retryClassification !== RetryClassification::Terminal)) {
            throw new InvalidArgumentException('Invalid execution requires terminal refusal and error classification.');
        }
        if ($validity === ExecutionValidity::Failed && ($errorCode === null || $retryClassification === RetryClassification::NotApplicable || $refusal === ContractRefusal::None)) {
            throw new InvalidArgumentException('Failed execution requires error and retry classification.');
        }
        foreach ($attempts as $index => $attempt) {
            if (! $attempt instanceof ProviderAttemptEvidenceData || $attempt->providerCallIdentity !== $providerCallIdentity) {
                throw new InvalidArgumentException('Every attempt must match the execution call identity.');
            }
            if ($attempt->attempt !== $index + 1) {
                throw new InvalidArgumentException('Execution attempts must be ordered and contiguous.');
            }
        }
        if ($validity === ExecutionValidity::Validated && $attempts !== []) {
            $finalAttempt = $attempts[array_key_last($attempts)];
            if ($finalAttempt->outcome !== ProviderAttemptOutcome::Reported || $finalAttempt->usage === null || $usage->canonicalJson() !== $finalAttempt->usage->canonicalJson()) {
                throw new InvalidArgumentException('Validated aggregate usage must equal the final reported attempt.');
            }
        }
    }

    /** @param list<ProviderAttemptEvidenceData> $attempts */
    public static function validated(string $callIdentity, string $outputIdentity, string $output, MeasuredUsageData $usage, array $attempts = []): self
    {
        return new self($callIdentity, ExecutionValidity::Validated, self::nonEmpty($outputIdentity, 'validated_output_identity'), hash('sha256', $output), RetryClassification::NotApplicable, ContractRefusal::None, null, $usage, $output, false, $attempts);
    }

    /** @param list<ProviderAttemptEvidenceData> $attempts */
    public static function fromValidatedOutput(string $callIdentity, ValidatedProviderOutputData $output, MeasuredUsageData $usage, array $attempts = []): self
    {
        return new self($callIdentity, ExecutionValidity::Validated, $output->identity, $output->digest, RetryClassification::NotApplicable, ContractRefusal::None, null, $usage, $output->content, false, $attempts);
    }

    /** @param list<ProviderAttemptEvidenceData> $attempts */
    public static function refused(string $callIdentity, ContractRefusal $refusal, string $errorCode, RetryClassification $retry = RetryClassification::Terminal, array $attempts = []): self
    {
        return new self($callIdentity, ExecutionValidity::Refused, null, null, $retry, $refusal, self::nonEmpty($errorCode, 'error_code'), null, null, false, $attempts);
    }

    /** @param list<ProviderAttemptEvidenceData> $attempts */
    public static function failed(string $callIdentity, string $errorCode, RetryClassification $retry, array $attempts, ContractRefusal $refusal = ContractRefusal::ProviderError): self
    {
        return new self($callIdentity, ExecutionValidity::Failed, null, null, $retry, $refusal, self::nonEmpty($errorCode, 'error_code'), null, null, false, $attempts);
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);
        $usage = ($payload['usage'] ?? null) === null ? null : self::arrayValue($payload, 'usage');
        $attemptPayloads = $payload['attempts'] ?? [];
        if (! is_array($attemptPayloads) || ! array_is_list($attemptPayloads)) {
            throw new InvalidArgumentException('attempts must be a list.');
        }
        $attempts = [];
        foreach ($attemptPayloads as $attemptPayload) {
            if (! is_array($attemptPayload) || array_is_list($attemptPayload)) {
                throw new InvalidArgumentException('Each attempt must be an object.');
            }
            $attempts[] = ProviderAttemptEvidenceData::fromCanonicalArray(self::objectValue($attemptPayload, 'attempt'));
        }

        return new self(self::stringValue($payload, 'provider_call_identity'), ExecutionValidity::from(self::stringValue($payload, 'validity')), self::nullableStringValue($payload, 'validated_output_identity'), self::nullableStringValue($payload, 'validated_output_digest'), RetryClassification::from(self::stringValue($payload, 'retry_classification')), ContractRefusal::from(self::stringValue($payload, 'refusal')), self::nullableStringValue($payload, 'error_code'), is_array($usage) ? MeasuredUsageData::fromCanonicalArray($usage) : null, self::nullableStringValue($payload, 'output'), self::booleanValue($payload, 'replayed'), $attempts);
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['attempts' => array_map(static fn (ProviderAttemptEvidenceData $attempt): array => $attempt->toCanonicalArray(), $this->attempts), 'error_code' => $this->errorCode, 'output' => $this->output, 'provider_call_identity' => $this->providerCallIdentity, 'refusal' => $this->refusal->value, 'replayed' => $this->replayed, 'retry_classification' => $this->retryClassification->value, 'schema_version' => $this->schemaVersion(), 'usage' => $this->usage?->toCanonicalArray(), 'validated_output_digest' => $this->validatedOutputDigest, 'validated_output_identity' => $this->validatedOutputIdentity, 'validity' => $this->validity->value];
    }
}
