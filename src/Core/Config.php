<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private readonly array $items = [],
    ) {
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->resolve($key);

        return is_string($value) ? $value : $default;
    }

    public function has(string $key): bool
    {
        // Array destructuring: ignore the value and keep only the existence flag.
        [$exists] = $this->find($key);

        return $exists;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->resolve($key);

        return is_int($value) ? $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->resolve($key);

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<mixed> $default
     * @return array<mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->resolve($key);

        return is_array($value) ? $value : $default;
    }

    private function resolve(string $key): mixed
    {
        [, $value] = $this->find($key);

        return $value;
    }

    /**
     * Walks the dot-separated path and reports both whether the full
     * path exists and the value found, so an explicit null value can be
     * distinguished from a missing key.
     *
     * @return array{bool, mixed}
     */
    private function find(string $key): array
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }
}
