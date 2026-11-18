<?php

declare(strict_types=1);

use Capell\AIOrchestrator\Support\Ai\AiRateLimiter;
use Capell\AIOrchestrator\Support\Ai\Cache\RateLimitCache;
use Illuminate\Support\Facades\Cache;

function makeSeoSuiteRateLimiter(int $requestsPerMinute = 2, int $windowSeconds = 30): AiRateLimiter
{
    Cache::driver('array')->clear();

    return new AiRateLimiter(new RateLimitCache('array'), [
        'enabled' => true,
        'requests_per_minute' => $requestsPerMinute,
        'window_seconds' => $windowSeconds,
    ]);
}

it('tracks remaining global ai requests and enforces configured limits', function (): void {
    $limiter = makeSeoSuiteRateLimiter();

    expect($limiter->getRemainingRequests('global'))->toBe(2);

    $limiter->checkLimit('global', 'titles');

    expect($limiter->getRemainingRequests('global'))->toBe(1)
        ->and($limiter->allow('global'))->toBeTrue()
        ->and($limiter->getRemainingRequests('global'))->toBe(0)
        ->and($limiter->allow('global'))->toBeFalse();
});

it('can reset ai rate limit counters', function (): void {
    $limiter = makeSeoSuiteRateLimiter(requestsPerMinute: 1);

    $limiter->checkLimit('global', 'titles');

    expect($limiter->getRemainingRequests('global'))->toBe(0);

    $limiter->resetLimit('global');

    expect($limiter->getRemainingRequests('global'))->toBe(1);
});

it('bypasses ai rate limits when disabled', function (): void {
    $limiter = new AiRateLimiter(new RateLimitCache('array'), [
        'enabled' => false,
        'requests_per_minute' => 1,
    ]);

    $limiter->checkLimit('global', 'titles');
    $limiter->checkLimit('global', 'titles');

    expect($limiter->getRemainingRequests('global'))->toBe(1)
        ->and($limiter->allow('global'))->toBeTrue();
});

it('gives each bucket its own limit so one user cannot exhaust the global bucket', function (): void {
    Cache::driver('array')->clear();
    $limiter = new AiRateLimiter(new RateLimitCache('array'), [
        'enabled' => true,
        'requests_per_minute' => 2,
        'global_requests_per_minute' => 5,
        'feature_requests_per_minute' => 5,
        'window_seconds' => 30,
    ]);

    $limiter->checkLimit('user-1', 'titles');
    $limiter->checkLimit('user-1', 'titles');

    expect(fn () => $limiter->checkLimit('user-1', 'titles'))->toThrow(RuntimeException::class, 'user:user-1');

    $limiter->checkLimit('user-2', 'titles');

    expect($limiter->getRemainingRequests('global'))->toBe(2);
});

it('does not collapse a missing user id into the global bucket', function (): void {
    Cache::driver('array')->clear();
    $limiter = new AiRateLimiter(new RateLimitCache('array'), [
        'enabled' => true,
        'requests_per_minute' => 1,
        'global_requests_per_minute' => 10,
        'feature_requests_per_minute' => 10,
        'window_seconds' => 30,
    ]);

    $limiter->checkLimit(null, 'titles');
    $limiter->checkLimit(null, 'titles');
    $limiter->checkLimit(null, 'titles');

    expect($limiter->getRemainingRequests('global'))->toBe(7);
});

it('gives back hits taken on other buckets when a request is refused', function (): void {
    Cache::driver('array')->clear();
    $limiter = new AiRateLimiter(new RateLimitCache('array'), [
        'enabled' => true,
        'requests_per_minute' => 1,
        'global_requests_per_minute' => 10,
        'feature_requests_per_minute' => 10,
        'window_seconds' => 30,
    ]);

    $limiter->checkLimit('user-1', 'titles');

    foreach (range(1, 3) as $ignored) {
        expect(fn () => $limiter->checkLimit('user-1', 'titles'))->toThrow(RuntimeException::class);
    }

    expect($limiter->getRemainingRequests('global'))->toBe(9);
});

it('counts every concurrent hit because the increment is atomic', function (): void {
    Cache::driver('array')->clear();
    $cache = new RateLimitCache('array');

    $totals = array_map(fn (): int => $cache->hit('ai_rate_limit_probe', 30), range(1, 5));

    expect($totals)->toBe([1, 2, 3, 4, 5]);
});
