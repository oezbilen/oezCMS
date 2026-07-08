<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Environment
{
    /** @var array<string, string> */
    private array $variables = [];

    public function __construct(private readonly string $envFile)
    {
    }

    public function load(): void
    {
        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (false === $lines) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // key=value parsing
            if (!str_contains($line, '=')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1] ?? '');

            $this->variables[$key] = $value;
        }
    }

    public function get(string $key): ?string
    {
        return $this->variables[$key] ?? null;
    }
}
