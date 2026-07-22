<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ProviderAttemptOutcome;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class ProviderAttemptEvidenceData extends CanonicalContract
{
    public function __construct(
        public string $providerCallIdentity,
        public int $attempt,
        public ProviderAttemptOutcome $outcome,
        public RetryClassification $retryClassification,
        public ?MeasuredUsageData $usage,
        public ?string $errorCode,
    ) {
        self::nonEmpty($providerCallIdentity, 'provider_call_identity');
        if ($attempt < 1) {
            throw new InvalidArgumentException('attempt must be positive.');
        }
        if (($outcome === ProviderAttemptOutcome::Reported) !== ($usage !== null)) {
            throw new InvalidArgumentException('Only a reported attempt may carry measured usage.');
        }
        if ($outcome === ProviderAttemptOutcome::Reported && ($errorCode !== null || $retryClassification !== RetryClassification::NotApplicable)) {
            throw new InvalidArgumentException('Reported attempt cannot carry an error or retry classification.');
        }
        if ($outcome === ProviderAttemptOutcome::HandoffStarted && ($errorCode !== null || $retryClassification !== RetryClassification::NotApplicable)) {
            throw new InvalidArgumentException('Started handoff cannot carry outcome classification before a provider response.');
        }
        if (! in_array($outcome, [ProviderAttemptOutcome::HandoffStarted, ProviderAttemptOutcome::Reported], true) && ($errorCode === null || $retryClassification === RetryClassification::NotApplicable)) {
            throw new InvalidArgumentException('Unreported attempt requires error and retry classification.');
        }
        if ($usage !== null && $usage->providerCallIdentity !== $providerCallIdentity) {
            throw new InvalidArgumentException('Attempt and usage call identities must match.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);
        $usage = ($payload['usage'] ?? null) === null ? null : self::arrayValue($payload, 'usage');

        return new self(self::stringValue($payload, 'provider_call_identity'), self::integerValue($payload, 'attempt'), ProviderAttemptOutcome::from(self::stringValue($payload, 'outcome')), RetryClassification::from(self::stringValue($payload, 'retry_classification')), $usage === null ? null : MeasuredUsageData::fromCanonicalArray($usage), self::nullableStringValue($payload, 'error_code'));
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['attempt' => $this->attempt, 'error_code' => $this->errorCode, 'outcome' => $this->outcome->value, 'provider_call_identity' => $this->providerCallIdentity, 'retry_classification' => $this->retryClassification->value, 'schema_version' => $this->schemaVersion(), 'usage' => $this->usage?->toCanonicalArray()];
    }
}
