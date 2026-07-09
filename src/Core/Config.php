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
        return null !== $this->resolve($key);
    }

    private function resolve(string $key): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
