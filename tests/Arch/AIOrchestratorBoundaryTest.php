<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\Ai\ProviderCallAllowanceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Data\Ai\TerminalSettlementResultData;
use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\SettlementReplayStatus;
use Capell\AIOrchestrator\Tests\Support\FinancialAuthoritySourceScanner;
use Symfony\Component\Finder\Finder;

arch('ai-orchestrator package does not import removed layout-builder namespace')
    ->expect('Capell\AIOrchestrator')
    ->not->toUse('Capell\LayoutBuilder');

it('ai-orchestrator source contains no direct layout-builder references', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $violations = [];

    $files = (new Finder)
        ->files()
        ->in($packagePath . '/src')
        ->name('*.php')
        ->contains('Capell\\LayoutBuilder');

    foreach ($files as $file) {
        $violations[] = str_replace($packagePath . '/', '', $file->getPathname());
    }

    expect($violations)->toBeEmpty();
});

it('producer public source exposes no financial authority APIs', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $violations = [];

    $files = (new Finder)
        ->files()
        ->in($packagePath . '/src')
        ->name('*.php');

    foreach ($files as $file) {
        $relativePath = str_replace($packagePath . '/src/', '', $file->getPathname());
        if (FinancialAuthoritySourceScanner::violations($relativePath, $file->getContents()) !== []) {
            $violations[] = 'src/' . $relativePath;
        }
    }

    $config = require $packagePath . '/config/capell-ai-orchestrator.php';

    expect($violations)->toBeEmpty()
        ->and($config)->not->toHaveKeys(['ai_costs', 'spend_limits'])
        ->and($config['prism'])->not->toHaveKey('enforce_price_map');
});

it('financial authority ratchet detects normalized symbol and mutation variants', function (string $source): void {
    expect(FinancialAuthoritySourceScanner::violations('Fixtures/FinancialAuthority.php', $source))->not->toBeEmpty();
})->with([
    'cost action' => 'final class EstimateCostAction {}',
    'pricing service' => 'final class PricingService {}',
    'wallet member' => '$walletBalance = 10;',
    'reserve mutation' => 'public function reserveFunds(): void {}',
    'release mutation' => 'public function release(): void {}',
]);

it('allows only the complete provider transport DTO APIs and canonical keys', function (): void {
    $authorization = new ReflectionClass(ProviderCallAuthorizationData::class);
    $settlement = new ReflectionClass(TerminalSettlementResultData::class);
    $declaredMethods = static fn (ReflectionClass $class): array => array_values(array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter($class->getMethods(ReflectionMethod::IS_PUBLIC), static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class->getName()),
    ));

    $authorizationData = new ProviderCallAuthorizationData('reservation', 'operation', 'stage', 1, 'call', hash('sha256', 'request'), 'output', 'policy', hash('sha256', 'policy'), new ProviderCallAllowanceData(1, 1, 1), new DateTimeImmutable('2030-01-01'));
    $settlementData = new TerminalSettlementResultData(1, 0, 'transition', SettlementReplayStatus::Applied, ContractRefusal::None, null);

    expect(array_keys($authorization->getDefaultProperties()))->toBe([])
        ->and(array_map(static fn (ReflectionProperty $property): string => $property->getName(), $authorization->getProperties(ReflectionProperty::IS_PUBLIC)))->toBe(['reservationReference', 'operationIdentity', 'stageIdentity', 'fencingToken', 'providerCallIdempotencyKey', 'requestDigest', 'validatedOutputIdentity', 'validatorPolicyIdentity', 'validatorPolicyDigest', 'allowance', 'deadline'])
        ->and($declaredMethods($authorization))->toEqualCanonicalizing(['__construct', 'fromCanonicalArray', 'providerHeaderIdentity', 'assertUsable', 'toCanonicalArray'])
        ->and(array_map(static fn (ReflectionProperty $property): string => $property->getName(), $settlement->getProperties(ReflectionProperty::IS_PUBLIC)))->toBe(['settledAmount', 'releasedAmount', 'transitionIdentity', 'replayStatus', 'refusal', 'errorCode'])
        ->and($declaredMethods($settlement))->toEqualCanonicalizing(['__construct', 'fromCanonicalArray', 'toCanonicalArray'])
        ->and(array_keys($authorizationData->toCanonicalArray()))->toEqualCanonicalizing(['allowance', 'deadline', 'fencing_token', 'operation_identity', 'provider_call_idempotency_key', 'request_digest', 'reservation_reference', 'schema_version', 'stage_identity', 'validated_output_identity', 'validator_policy_digest', 'validator_policy_identity'])
        ->and(array_keys($settlementData->toCanonicalArray()))->toEqualCanonicalizing(['error_code', 'refusal', 'released_amount', 'replay_status', 'schema_version', 'settled_amount', 'transition_identity']);
});

it('rejects malicious reuse of transport identifiers on allowed paths', function (string $path, string $source): void {
    expect(FinancialAuthoritySourceScanner::violations($path, $source))->not->toBeEmpty();
})->with([
    ['Data/Ai/ProviderCallAuthorizationData.php', 'public function reservationReference(): void {}'],
    ['Data/Ai/TerminalSettlementResultData.php', 'public function releasedAmount(): void {}'],
    ['Data/Ai/ProviderCallAuthorizationData.php', 'reservationReference reservationReference reservation_reference'],
]);

arch()
    ->expect('Capell\AIOrchestrator')
    ->classes()
    ->toUseStrictEquality();
