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
        $value = $this->items[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
