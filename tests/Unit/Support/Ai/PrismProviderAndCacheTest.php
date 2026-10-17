<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Exceptions\OpenAICircuitBreakerOpenException;
use Capell\AIOrchestrator\Support\Ai\AIGenerationCache;
use Capell\AIOrchestrator\Support\Ai\AiResponse;
use Capell\AIOrchestrator\Support\Ai\Cache\RateLimitCache;
use Capell\AIOrchestrator\Support\Ai\PrismProvider;
use Capell\Tests\Support\FailingPrismTransport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\PrismManager;
use Prism\Prism\Testing\PrismFake;
use Prism\Prism\Text\Request as PrismTextRequest;
use Prism\Prism\Text\Response as PrismTextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function makePrismTextResponseWithoutUsage(string $text): PrismTextResponse
{
    $reflection = new ReflectionClass(PrismTextResponse::class);

    /** @var PrismTextResponse $response */
    $response = $reflection->newInstanceWithoutConstructor();

    foreach ([
        'steps' => collect(),
        'text' => $text,
        'finishReason' => FinishReason::Stop,
        'toolCalls' => [],
        'toolResults' => [],
        'meta' => new Meta(id: 'fake-response', model: 'gpt-test'),
        'messages' => collect(),
        'additionalContent' => [],
        'raw' => null,
    ] as $property => $value) {
        new ReflectionProperty(PrismTextResponse::class, $property)->setValue($response, $value);
    }

    return $response;
}

it('stores ai generation values behind the configured cache driver and ttl', function (): void {
    Cache::flush();

    $cache = new AIGenerationCache('array', 120);
    $calls = 0;

    $remembered = $cache->remember('brief:1', function () use (&$calls): string {
        $calls++;

        return 'generated brief';
    });
    $secondRemembered = $cache->remember('brief:1', function () use (&$calls): string {
        $calls++;

        return 'new brief';
    });

    $cache->put('brief:2', 'stored brief', 60);

    expect($remembered)->toBe('generated brief')
        ->and($secondRemembered)->toBe('generated brief')
        ->and($calls)->toBe(1)
        ->and($cache->get('brief:2'))->toBe('stored brief')
        ->and($cache->get('missing', 'fallback'))->toBe('fallback')
        ->and($cache->keyFor('page', 42))->toBe('page:42')
        ->and($cache->ttl())->toBe(120);
});

it('stores rate limit state behind the configured cache driver', function (): void {
    Cache::flush();

    $cache = new RateLimitCache('array');
    $key = $cache->keyFor('user-7');

    $cache->put($key, ['attempts' => 2], 30);

    expect($key)->toBe('ai_rate_limit_user-7')
        ->and($cache->get($key))->toBe(['attempts' => 2])
        ->and($cache->ttl())->toBe(60);

    $cache->forget($key);

    expect($cache->get($key, 'empty'))->toBe('empty');
});

it('maps provider aliases and exposes service metadata', function (): void {
    $provider = new PrismProvider(['max_retries' => 1, 'retry_delay_ms' => 0]);

    expect($provider->handles())->toBe('prism_provider')
        ->and($provider->isAvailable())->toBeTrue();
});

it('sends normalized chat messages through prism and maps the response telemetry', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);

    $fake = Prism::fake([
        new PrismTextResponse(
            steps: collect(),
            text: 'Generated title',
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(promptTokens: 11, completionTokens: 7),
            meta: new Meta(id: 'fake-response', model: 'gpt-test'),
            messages: collect(),
        ),
    ]);

    $provider = new PrismProvider([
        'provider' => 'anthropic',
        'model' => 'claude-test',
        'max_retries' => 1,
        'retry_delay_ms' => 0,
        'timeout_seconds' => 19,
        'connect_timeout_seconds' => 4,
    ]);

    $response = $provider->chat([
        'messages' => [
            ['role' => 'system', 'content' => 'Use the brand voice.'],
            ['role' => 'user', 'content' => 'Draft a page title.'],
            ['role' => 'user', 'content' => 'Keep it short.'],
        ],
        'max_tokens' => 128,
        'temperature' => 0.2,
    ]);

    $fake->assertCallCount(1);
    $fake->assertRequest(function (array $requests): void {
        $request = $requests[0] ?? null;

        expect($request)->toBeInstanceOf(PrismTextRequest::class);

        if (! $request instanceof PrismTextRequest) {
            return;
        }

        expect($request->model())->toBe('claude-test')
            ->and(collect($request->systemPrompts())->pluck('content')->all())->toBe(['Use the brand voice.'])
            ->and($request->prompt())->toBe("Draft a page title.\n\nKeep it short.")
            ->and($request->maxTokens())->toBe(128)
            ->and($request->temperature())->toBe(0.2)
            ->and($request->clientOptions()['timeout'] ?? null)->toBe(19)
            ->and($request->clientOptions()['connect_timeout'] ?? null)->toBe(4)
            ->and(data_get($request->clientOptions(), 'headers.Idempotency-Key'))
            ->toMatch('/^[a-f0-9]{64}$/');
    });

    expect($response->content)->toBe('Generated title')
        ->and($response->tokensUsed)->toBe(18)
        ->and($response->model)->toBe('claude-test')
        ->and($response->metadata)->toMatchArray([
            'prompt_tokens' => 11,
            'completion_tokens' => 7,
            'cache_hit' => false,
        ])
        ->and($provider->isAvailable())->toBeTrue();
});

it('resolves configured prism provider names to prism enums', function (string $name, Provider $expected): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    $transport = new PrismFake;
    $manager = Mockery::mock(PrismManager::class);
    $manager->shouldReceive('resolve')->once()->with($expected->value, Mockery::type('array'))->andReturn($transport);
    app()->instance(PrismManager::class, $manager);
    $provider = new PrismProvider(['provider' => $name, 'max_retries' => 1]);

    expect($provider->chat(['messages' => [['role' => 'user', 'content' => 'Provider routing probe']]])->tokensUsed)->toBe(0);
    $transport->assertCallCount(1);
})->with([
    'anthropic' => ['anthropic', Provider::Anthropic],
    'gemini' => ['gemini', Provider::Gemini],
    'google' => ['google', Provider::Gemini],
    'ollama' => ['ollama', Provider::Ollama],
    'openai fallback' => ['unknown', Provider::OpenAI],
]);

it('opens and resets the prism circuit breaker after repeated failures', function (): void {
    Cache::flush();

    $provider = new PrismProvider(['provider' => 'openai', 'max_retries' => 1, 'retry_delay_ms' => 0]);
    installFailingPrismTransport(new ConnectionException('Controlled outage'));

    expect($provider->isAvailable())->toBeTrue();

    for ($failure = 1; $failure <= 5; $failure++) {
        expect(fn (): AiResponse => $provider->chat(['messages' => [['role' => 'user', 'content' => 'Breaker failure probe']]]))->toThrow(ConnectionException::class);
    }

    expect($provider->isAvailable())->toBeFalse()
        ->and(fn (): mixed => $provider->chat(['messages' => []]))->toThrow(OpenAICircuitBreakerOpenException::class);

    $provider->resetCircuitBreaker();

    expect($provider->isAvailable())->toBeTrue();
});

it('scopes prism circuit breakers by provider and model', function (): void {
    Cache::flush();

    $openAiProvider = new PrismProvider(['provider' => 'openai', 'max_retries' => 1, 'retry_delay_ms' => 0]);
    $otherOpenAiModel = new PrismProvider(['provider' => 'openai', 'model' => 'gpt-4o-mini']);
    $anthropicProvider = new PrismProvider(['provider' => 'anthropic']);
    installFailingPrismTransport(new ConnectionException('Controlled outage'));

    for ($failure = 1; $failure <= 5; $failure++) {
        expect(fn (): AiResponse => $openAiProvider->chat(['messages' => [['role' => 'user', 'content' => 'Scoped breaker failure probe']]]))->toThrow(ConnectionException::class);
    }

    expect($openAiProvider->circuitBreakerKey())->toBe('ai_circuit_breaker_state:openai:gpt-4o')
        ->and($otherOpenAiModel->circuitBreakerKey())->toBe('ai_circuit_breaker_state:openai:gpt-4o-mini')
        ->and($anthropicProvider->circuitBreakerKey())->toBe('ai_circuit_breaker_state:anthropic:gpt-4o')
        ->and($openAiProvider->isAvailable())->toBeFalse()
        ->and($otherOpenAiModel->isAvailable())->toBeTrue()
        ->and($anthropicProvider->isAvailable())->toBeTrue();
});

it('only retries transient provider failures', function (Throwable $exception, bool $expected): void {
    Cache::flush();
    $transport = installFailingPrismTransport($exception);
    $provider = new PrismProvider(['max_retries' => 2, 'retry_delay_ms' => 0]);

    expect(fn (): AiResponse => $provider->chat(['messages' => [['role' => 'user', 'content' => 'Retry classification probe']]]))->toThrow($exception::class);
    expect($transport->calls)->toBe($expected ? 2 : 1);
})->with([
    'connection failure' => [new ConnectionException('connection failed'), true],
    'rate limit' => [PrismRateLimitedException::make(), true],
    'provider overloaded' => [PrismProviderOverloadedException::make('openai'), true],
    'request timeout' => [new PrismException('timed out', 408), true],
    'server error' => [new PrismException('unavailable', 503), true],
    'bad request' => [new PrismException('invalid prompt', 400), false],
    'domain failure' => [new RuntimeException('invalid generated content'), false],
]);

it('bounds prompt size before dispatch', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);

    $fake = Prism::fake();
    $provider = new PrismProvider([
        'model' => 'gpt-4o-mini',
        'max_retries' => 1,
        'max_prompt_chars' => 32,
    ]);

    $provider->chat([
        'messages' => [
            ['role' => 'system', 'content' => str_repeat('S', 20)],
            ['role' => 'user', 'content' => str_repeat('U', 40)],
        ],
    ]);

    $fake->assertRequest(function (array $requests): void {
        $request = $requests[0] ?? null;

        expect($request)->toBeInstanceOf(PrismTextRequest::class);

        if (! $request instanceof PrismTextRequest) {
            return;
        }

        $systemPrompt = collect($request->systemPrompts())->pluck('content')->implode('');

        expect(mb_strlen($systemPrompt . $request->prompt()))->toBeLessThanOrEqual(32)
            ->and($request->prompt())->not->toBe(str_repeat('U', 40));
    });
});

it('reuses identical successful generations without rebilling', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);

    $fake = Prism::fake([
        new PrismTextResponse(
            steps: collect(),
            text: 'Cached response',
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(promptTokens: 3, completionTokens: 2),
            meta: new Meta(id: 'cached-response', model: 'gpt-4o-mini'),
            messages: collect(),
        ),
    ]);
    $provider = new PrismProvider(
        ['model' => 'gpt-4o-mini', 'max_retries' => 1],
        new AIGenerationCache('array', 120),
    );
    $params = ['messages' => [['role' => 'user', 'content' => 'Repeatable prompt']]];

    $first = $provider->chat($params);
    $second = $provider->chat($params);

    $fake->assertCallCount(1);

    expect($first->metadata['cache_hit'] ?? null)->toBeFalse()
        ->and($second->content)->toBe('Cached response')
        ->and($second->metadata)->toMatchArray(['cache_hit' => true])
        ->and($second->metadata)->not->toHaveKey('cost_micros');
});

it('normalizes missing prism usage telemetry to zero tokens', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);
    Prism::fake([makePrismTextResponseWithoutUsage('Zero usage probe')]);
    $response = new PrismProvider(['max_retries' => 1])->chat(['messages' => [['role' => 'user', 'content' => 'Usage probe']]]);

    expect($response->metadata['prompt_tokens'])->toBe(0)
        ->and($response->metadata['completion_tokens'])->toBe(0);
});

it('maps prism chat responses with missing usage telemetry to zero tokens', function (): void {
    Cache::flush();
    app()->instance('prism', new \Prism\Prism\Prism);

    $fake = Prism::fake([
        makePrismTextResponseWithoutUsage('Generated summary'),
    ]);

    $provider = new PrismProvider([
        'provider' => 'ollama',
        'model' => 'llama-test',
        'max_retries' => 1,
        'retry_delay_ms' => 0,
    ]);

    $response = $provider->chat([
        'messages' => [
            ['role' => 'user', 'content' => 'Draft a summary.'],
        ],
    ]);

    $fake->assertCallCount(1);

    expect($response->content)->toBe('Generated summary')
        ->and($response->tokensUsed)->toBe(0)
        ->and($response->model)->toBe('llama-test')
        ->and($response->metadata)->toMatchArray([
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
        ])
        ->and($provider->isAvailable())->toBeTrue();
});

function installFailingPrismTransport(Throwable $failure): FailingPrismTransport
{
    app()->instance('prism', new \Prism\Prism\Prism);
    $transport = new FailingPrismTransport($failure);
    $manager = Mockery::mock(PrismManager::class);
    $manager->shouldReceive('resolve')->andReturn($transport);
    app()->instance(PrismManager::class, $manager);

    return $transport;
}
