<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai\Cache;

use Illuminate\Support\Facades\Cache;

class RateLimitCache
{
    public function __construct(private readonly string $driver) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::driver($this->driver)->get($key, $default);
    }

    /**
     * Read a rate-limit counter, always normalised to an integer count.
     *
     * @return array{count: int}
     */
    public function counter(string $key): array
    {
        $value = $this->get($key, 0);
        $count = is_array($value) ? ($value['count'] ?? 0) : $value;

        return ['count' => is_numeric($count) ? (int) $count : 0];
    }

    /**
     * Atomically add one hit to a counter and return the new total.
     *
     * Cache::add only creates the key when it is missing, so the window starts on
     * the first hit and concurrent callers never overwrite each other's count.
     */
    public function hit(string $key, int $seconds): int
    {
        $store = Cache::driver($this->driver);
        $store->add($key, 0, $seconds);

        $value = $store->increment($key);

        return is_int($value) ? $value : 1;
    }

    /**
     * Give back a hit that was counted for an attempt that was then refused.
     */
    public function giveBack(string $key): void
    {
        Cache::driver($this->driver)->decrement($key);
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        Cache::driver($this->driver)->put($key, $value, $seconds);
    }

    public function forget(string $key): void
    {
        Cache::driver($this->driver)->forget($key);
    }

    public function keyFor(string $id): string
    {
        return 'ai_rate_limit_' . $id;
    }

    public function ttl(): int
    {
        // Default TTL for rate limiting (e.g., 60 seconds)
        return 60;
    }
}
