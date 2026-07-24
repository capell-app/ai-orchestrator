<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Actions\Ai;

use Capell\AIOrchestrator\Data\Ai\ManagedProviderExecutionReceiptData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ProviderExecutionResultData;
use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ManagedProviderExecutionReceiptData run(ProviderCallAuthorizationData $authorization, ProviderExecutionResultData $execution)
 */
final class BuildManagedProviderExecutionReceiptAction
{
    use AsFake;
    use AsObject;

    public function handle(
        ProviderCallAuthorizationData $authorization,
        ProviderExecutionResultData $execution,
    ): ManagedProviderExecutionReceiptData {
        if ($execution->validity !== ExecutionValidity::Validated
            || $execution->usage === null
            || $execution->validatedOutputIdentity === null
            || $execution->validatedOutputDigest === null
            || $execution->providerCallIdentity !== $authorization->providerCallIdempotencyKey
            || $execution->validatedOutputIdentity !== $authorization->validatedOutputIdentity) {
            throw new InvalidArgumentException('A managed execution receipt requires the exact validated authorized result.');
        }

        return new ManagedProviderExecutionReceiptData(
            providerCallIdentity: $execution->providerCallIdentity,
            requestDigest: $authorization->requestDigest,
            validatorPolicyIdentity: $authorization->validatorPolicyIdentity,
            validatorPolicyDigest: $authorization->validatorPolicyDigest,
            validatedOutputIdentity: $execution->validatedOutputIdentity,
            validatedOutputDigest: $execution->validatedOutputDigest,
            authorizationDigest: $authorization->digest(),
            executionDigest: $execution->digest(),
            usageDigest: $execution->usage->digest(),
            replayed: $execution->replayed,
        );
    }
}
