<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Support\Contracts;

use Capell\AIOrchestrator\Contracts\VersionedContract;
use InvalidArgumentException;
use JsonException;

abstract readonly class CanonicalContract implements VersionedContract
{
    final public function schemaVersion(): int
    {
        return 1;
    }

    final public function canonicalJson(): string
    {
        try {
            return json_encode($this->sortRecursively($this->toCanonicalArray()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The contract cannot be canonically serialized.', previous: $exception);
        }
    }

    final public function digest(): string
    {
        return hash('sha256', $this->canonicalJson());
    }

    /** @param array<string, mixed> $payload */
    final protected static function assertVersion(array $payload): void
    {
        if (($payload['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported contract schema version.');
        }
    }

    final protected static function nonEmpty(string $value, string $field): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("{$field} must not be empty.");
        }

        return $value;
    }

    final protected static function nonNegative(int $value, string $field): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative integer.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    final protected static function stringValue(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} must be a string.");
        }

        return self::nonEmpty($value, $field);
    }

    /** @param array<string, mixed> $payload */
    final protected static function nullableStringValue(array $payload, string $field): ?string
    {
        if (($payload[$field] ?? null) === null) {
            return null;
        }

        return self::stringValue($payload, $field);
    }

    /** @param array<string, mixed> $payload */
    final protected static function integerValue(array $payload, string $field): int
    {
        $value = $payload[$field] ?? null;
        if (! is_int($value)) {
            throw new InvalidArgumentException("{$field} must be an integer.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    final protected static function booleanValue(array $payload, string $field): bool
    {
        $value = $payload[$field] ?? null;
        if (! is_bool($value)) {
            throw new InvalidArgumentException("{$field} must be a boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    final protected static function arrayValue(array $payload, string $field): array
    {
        $value = $payload[$field] ?? null;

        return self::objectValue($value, $field);
    }

    /** @return array<string, mixed> */
    final protected static function objectValue(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException("{$field} must be an object.");
        }

        $object = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new InvalidArgumentException("{$field} must use string keys.");
            }
            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
