<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Exceptions;

use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use RuntimeException;

final class AiContractRefusedException extends RuntimeException
{
    public function __construct(public readonly ContractRefusal $refusal, string $message)
    {
        parent::__construct($message);
    }
}
