<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Data\Ai\ProviderCallAllowanceData;
use Capell\AIOrchestrator\Data\Ai\ProviderCallAuthorizationData;
use Capell\AIOrchestrator\Enums\Ai\ContractRefusal;
use Capell\AIOrchestrator\Enums\Ai\ExecutionValidity;
use Capell\AIOrchestrator\Enums\Ai\RetryClassification;
use Capell\AIOrchestrator\Exceptions\AiProviderOutputTruncatedException;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\AIOrchestrator\Tests\Fixtures\AcceptingProviderOutputValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

/** @return array<string, mixed> */
function schemaModeSchema(): array
{
    return ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']];
}

function schemaModeResponse(string $json, FinishReason $finish = FinishReason::Stop): StructuredResponse
{
    $decoded = json_decode($json, true);

    return new StructuredResponse(collect(), $json, is_array($decoded) ? $decoded : null, $finish, new Usage(11, 7), new Meta('schema-run', 'model-x'), raw: null);
}

function schemaModeProvider(): PrismProvider
{
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);

    return new PrismProvider(['provider' => 'openai', 'model' => 'model-x', 'max_retries' => 3, 'retry_delay_ms' => 0]);
}

it('sends a structured request carrying the schema when a response schema is given', function (): void {
    $provider = schemaModeProvider();
    $fake = Prism::fake([schemaModeResponse('{"name":"Bluefin"}')]);

    $response = $provider->chat(['messages' => [['role' => 'user', 'content' => 'Describe']], 'max_tokens' => 50, 'response_schema' => schemaModeSchema()]);

    $fake->assertRequest(function (array $requests): void {
        $request = $requests[0];

        expect($request instanceof StructuredRequest)->toBeTrue()
            ->and($request instanceof StructuredRequest ? $request->schema()->toArray() : null)->toBe(schemaModeSchema());
    });
    expect($response->content)->toBe('{"name":"Bluefin"}');
});

it('does not reuse a cached or authorised request across different schemas', function (): void {
    $provider = schemaModeProvider();
    $messages = [['role' => 'user', 'content' => 'Describe']];

    $plain = $provider->managedRequestDigest(['messages' => $messages, 'max_tokens' => 50]);
    $schema = $provider->managedRequestDigest(['messages' => $messages, 'max_tokens' => 50, 'response_schema' => schemaModeSchema()]);
    $other = $provider->managedRequestDigest(['messages' => $messages, 'max_tokens' => 50, 'response_schema' => ['type' => 'object']]);

    expect($schema)->not->toBe($plain)
        ->and($other)->not->toBe($schema);
});

it('stops at a cut-off structured response instead of retrying it', function (): void {
    $provider = schemaModeProvider();
    $fake = Prism::fake([schemaModeResponse('{"name":"Blue', FinishReason::Length), schemaModeResponse('{"name":"Bluefin"}')]);

    expect(fn () => $provider->chat(['messages' => [['role' => 'user', 'content' => 'Describe']], 'max_tokens' => 5, 'response_schema' => schemaModeSchema()]))
        ->toThrow(AiProviderOutputTruncatedException::class);

    $fake->assertCallCount(1);
});

it('refuses a cut-off managed structured response as terminal output truncation after one attempt', function (): void {
    $provider = schemaModeProvider();
    $fake = Prism::fake([schemaModeResponse('{"name":"Blue', FinishReason::Length), schemaModeResponse('{"name":"Bluefin"}')]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Describe']], 'max_tokens' => 20, 'response_schema' => schemaModeSchema()];
    $validator = new AcceptingProviderOutputValidator;
    $authorization = new ProviderCallAuthorizationData('reservation', 'operation-1', 'stage-1', 1, 'call-key', $provider->managedRequestDigest($params), 'output', $validator->policyIdentity(), $validator->policyDigest(), new ProviderCallAllowanceData(3, 50, 20), CarbonImmutable::parse('2030-01-01T00:00:00+00:00'));

    $result = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 1, 'output', $validator, $params);

    $fake->assertCallCount(1);
    expect($result->validity)->toBe(ExecutionValidity::Invalid)
        ->and($result->refusal)->toBe(ContractRefusal::OutputTruncated)
        ->and($result->retryClassification)->toBe(RetryClassification::Terminal);
});

it('treats a finished structured response that fails the validator as terminal without a second request', function (): void {
    $provider = schemaModeProvider();
    $fake = Prism::fake([schemaModeResponse('{"name":"Bluefin"}'), schemaModeResponse('{"name":"Bluefin"}')]);
    $params = ['messages' => [['role' => 'user', 'content' => 'Describe']], 'max_tokens' => 20, 'response_schema' => schemaModeSchema()];
    $validator = new AcceptingProviderOutputValidator('a different required string');
    $authorization = new ProviderCallAuthorizationData('reservation', 'operation-1', 'stage-1', 1, 'call-key-2', $provider->managedRequestDigest($params), 'output', $validator->policyIdentity(), $validator->policyDigest(), new ProviderCallAllowanceData(3, 50, 20), CarbonImmutable::parse('2030-01-01T00:00:00+00:00'));

    $result = $provider->authorizedChat($authorization, 'operation-1', 'stage-1', 1, 'output', $validator, $params);

    $fake->assertCallCount(1);
    expect($result->validity)->toBe(ExecutionValidity::Invalid)
        ->and($result->refusal)->toBe(ContractRefusal::InvalidOutput);
});
