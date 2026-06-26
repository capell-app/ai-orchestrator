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

    public function checkLimit(string $identifier = 'global', ?string $feature = null): void
    {
        if (! ($this->config['enabled'] ?? false)) {
            return;
        }

        $perMinute = $this->intConfig('requests_per_minute', 60);
        $limits = [
            'global' => $perMinute,
            'user:' . $identifier => $perMinute,
            'feature:' . $feature => $perMinute,
        ];
        foreach ($limits as $key => $limit) {
            throw_unless($this->canExecute($key, $limit), RuntimeException::class, 'AI rate limit exceeded for ' . $key);
        }

        foreach (array_keys($limits) as $key) {
            $this->incrementCounter($key);
        }
    }

    public function getRemainingRequests(string $identifier = 'global'): int
    {
        $cacheKey = 'ai_rate_limit_' . $identifier;
        $current = $this->cache->counter($cacheKey);
        $limit = $this->intConfig('requests_per_minute', 60);

        return max(0, $limit - $current['count']);
    }

    public function resetLimit(string $identifier = 'global'): void
    {
        $this->cache->forget('ai_rate_limit_' . $identifier);
        Log::info('Rate limit reset', ['identifier' => $identifier]);
    }

    public function allow(string $identifier = 'global'): bool
    {
        try {
            $this->checkLimit($identifier);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function canExecute(string $key, int $limit): bool
    {
        $cacheKey = 'ai_rate_limit_' . $key;
        $current = $this->cache->counter($cacheKey);

        return $current['count'] < $limit;
    }

    protected function incrementCounter(string $key): void
    {
        $cacheKey = 'ai_rate_limit_' . $key;
        $current = $this->cache->counter($cacheKey);
        $current['count']++;
        $ttl = $this->intConfig('window_seconds', 60);
        $this->cache->put($cacheKey, $current, $ttl);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
