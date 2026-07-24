<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Actions\Ai\BuildManagedProviderExecutionReceiptAction;
use Capell\AIOrchestrator\Data\Ai\MeasuredUsageData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAllowanceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\ProviderExecutionResultData;

it('binds the exact request policy output authorization execution and usage evidence', function (): void {
    $authorization = new ProviderCallAuthorizationData(
        'reservation',
        'operation',
        'stage',
        2,
        'call',
        hash('sha256', 'request'),
        'output',
        'policy',
        hash('sha256', 'policy'),
        new ProviderCallAllowanceData(1, 10, 10),
        new DateTimeImmutable('2030-01-01T00:00:00+00:00'),
    );
    $usage = new MeasuredUsageData('call', 1, 2, 3, 'provider', 'model', 'run', 'prism', true);
    $execution = ProviderExecutionResultData::validated('call', 'output', '{"valid":true}', $usage);
    $receipt = BuildManagedProviderExecutionReceiptAction::run($authorization, $execution);

    expect($receipt->authorizationDigest)->toBe($authorization->digest())
        ->and($receipt->executionDigest)->toBe($execution->digest())
        ->and($receipt->usageDigest)->toBe($usage->digest())
        ->and($receipt::fromCanonicalArray($receipt->toCanonicalArray())->digest())->toBe($receipt->digest());
});
