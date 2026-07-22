<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Exceptions;

use RuntimeException;
use Throwable;

final class ManagedProviderDispatchInterruptedException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct($previous->getMessage(), (int) $previous->getCode(), $previous);
    }
}
