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
        if (!is_file($this->envFile) || !is_readable($this->envFile)) {
            throw new EnvironmentException(
                sprintf('Environment file "%s" does not exist or is not readable.', $this->envFile),
            );
        }

        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (false === $lines) {
            throw new EnvironmentException(
                sprintf('Unable to read environment file "%s".', $this->envFile),
            );
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // ignore comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            // key=value parsing
            if (!str_contains($line, '=')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);

            $value = trim($parts[1] ?? '');
            $value = $this->stripInlineComment($value);
            $value = $this->stripQuotes($value);

            if ('' === $key) {
                continue;
            }

            if (isset($_ENV[$key]) || isset($_SERVER[$key]) || false !== getenv($key)) {
                $existing = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

                if (is_string($existing)) {
                    $this->variables[$key] = $existing;
                }

                continue;
            }

            $this->variables[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
        }
    }

    private function stripQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (('"' === $first && '"' === $last) || ("'" === $first && "'" === $last)) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function stripInlineComment(string $value): string
    {
        if ('' !== $value && ('"' === $value[0] || "'" === $value[0])) {
            return $value;
        }

        $position = strpos($value, ' #');

        if (false !== $position) {
            return rtrim(substr($value, 0, $position));
        }

        return $value;
    }

    public function get(string $key): ?string
    {
        return $this->variables[$key] ?? null;
    }

    public function getString(string $key, string $default = ''): string
    {
        return $this->get($key) ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if (null === $value) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => $default,
        };
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if (null === $value || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }
}
