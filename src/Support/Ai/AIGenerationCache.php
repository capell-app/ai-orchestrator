<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Ai;

use Closure;
use Illuminate\Support\Facades\Cache;

class AIGenerationCache
{
    public function __construct(private readonly string $driver, private readonly int $ttl) {}

    /**
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return Cache::driver($this->driver)->remember($key, $this->ttl, $callback);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::driver($this->driver)->get($key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        Cache::driver($this->driver)->put($key, $value, $ttl ?? $this->ttl);
    }

    public function keyFor(string $type, int|string $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    public function keyForRequest(array $request): string
    {
        $encodedRequest = json_encode(
            $this->normalizeForHash($request),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->keyFor('ai-generation', hash('sha256', $encodedRequest));
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalizeForHash($item), $value);
    }
}
