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
