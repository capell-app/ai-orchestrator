<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data\Ai;

use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\SettlementReplayStatus;
use Capell\AIOrchestrator\Support\Contracts\CanonicalContract;
use InvalidArgumentException;

final readonly class TerminalSettlementResultData extends CanonicalContract
{
    public function __construct(public int $settledAmount, public int $releasedAmount, public string $transitionIdentity, public SettlementReplayStatus $replayStatus, public ContractRefusal $refusal, public ?string $errorCode)
    {
        self::nonNegative($settledAmount, 'settled_amount');
        self::nonNegative($releasedAmount, 'released_amount');
        self::nonEmpty($transitionIdentity, 'transition_identity');
        if ($replayStatus !== SettlementReplayStatus::Refused && $refusal !== ContractRefusal::None) {
            throw new InvalidArgumentException('Successful settlement cannot carry a refusal.');
        }
        if ($replayStatus === SettlementReplayStatus::Refused && ($settledAmount !== 0 || $releasedAmount !== 0 || $refusal === ContractRefusal::None || $errorCode === null)) {
            throw new InvalidArgumentException('Refused settlement requires zero amounts, refusal, and error classification.');
        }
        if ($replayStatus !== SettlementReplayStatus::Refused && $errorCode !== null) {
            throw new InvalidArgumentException('Successful settlement cannot carry an error.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromCanonicalArray(array $payload): self
    {
        self::assertVersion($payload);

        return new self(self::integerValue($payload, 'settled_amount'), self::integerValue($payload, 'released_amount'), self::stringValue($payload, 'transition_identity'), SettlementReplayStatus::from(self::stringValue($payload, 'replay_status')), ContractRefusal::from(self::stringValue($payload, 'refusal')), self::nullableStringValue($payload, 'error_code'));
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return ['error_code' => $this->errorCode, 'refusal' => $this->refusal->value, 'released_amount' => $this->releasedAmount, 'replay_status' => $this->replayStatus->value, 'schema_version' => $this->schemaVersion(), 'settled_amount' => $this->settledAmount, 'transition_identity' => $this->transitionIdentity];
    }
}
