<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Capell\AIOrchestrator\Support\Ai\Cache\RateLimitCache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiRateLimiter
{
    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(
        protected RateLimitCache $cache,
        protected array $config,
    ) {}

    /**
     * Count one request against the global, per-user and per-feature buckets.
     *
     * Each bucket has its own limit. A missing user id gets no user bucket rather than
     * sharing the global one. Every bucket is incremented atomically; when any bucket
     * is over its limit the hits already taken are given back and the request is refused.
     */
    public function checkLimit(?string $identifier = null, ?string $feature = null): void
    {
        if (! ($this->config['enabled'] ?? false)) {
            return;
        }

        $perMinute = $this->intConfig('requests_per_minute', 60);
        $limits = ['global' => $this->intConfig('global_requests_per_minute', $perMinute)];

        if ($identifier !== null && $identifier !== '' && $identifier !== 'global') {
            $limits['user:' . $identifier] = $perMinute;
        }

        if ($feature !== null && $feature !== '') {
            $limits['feature:' . $feature] = $this->intConfig('feature_requests_per_minute', $perMinute);
        }

        $window = $this->intConfig('window_seconds', 60);
        $taken = [];

        foreach ($limits as $key => $limit) {
            $cacheKey = $this->cache->keyFor($key);
            $taken[] = $cacheKey;

            if ($this->cache->hit($cacheKey, $window) > $limit) {
                foreach ($taken as $takenKey) {
                    $this->cache->giveBack($takenKey);
                }

                throw new RuntimeException('AI rate limit exceeded for ' . $key);
            }
        }
    }

    public function getRemainingRequests(string $identifier = 'global'): int
    {
        $cacheKey = $this->cache->keyFor($identifier);
        $current = $this->cache->counter($cacheKey);
        $limit = $identifier === 'global'
            ? $this->intConfig('global_requests_per_minute', $this->intConfig('requests_per_minute', 60))
            : $this->intConfig('requests_per_minute', 60);

        return max(0, $limit - $current['count']);
    }

    public function resetLimit(string $identifier = 'global'): void
    {
        $this->cache->forget($this->cache->keyFor($identifier));
        Log::info('Rate limit reset', ['identifier' => $identifier]);
    }

    public function allow(?string $identifier = null): bool
    {
        try {
            $this->checkLimit($identifier);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
