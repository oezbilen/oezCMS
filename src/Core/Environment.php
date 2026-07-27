<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Environment
{
    /** @var array<string, string> */
    private array $variables = [];

    private const string KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

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

        // Empty lines are skipped below rather than by file(), which would
        // renumber the remaining ones and misreport every error position.
        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES);

        if (false === $lines) {
            throw new EnvironmentException(
                sprintf('Unable to read environment file "%s".', $this->envFile),
            );
        }

        $seen = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $line = trim($line);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = $this->parseAssignment($line, $number);

            if (isset($seen[$key])) {
                throw $this->syntaxError($number, sprintf('duplicate key "%s"', $key));
            }

            $seen[$key] = true;

            $this->define($key, $value);
        }
    }

    /**
     * @return array{string, string}
     */
    private function parseAssignment(string $line, int $number): array
    {
        $position = strpos($line, '=');

        if (false === $position) {
            throw $this->syntaxError($number, 'expected KEY=VALUE');
        }

        $key = rtrim(substr($line, 0, $position));

        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw $this->syntaxError($number, sprintf('invalid key "%s"', $key));
        }

        return [$key, $this->parseValue(ltrim(substr($line, $position + 1)), $number)];
    }

    private function parseValue(string $value, int $number): string
    {
        if ('' === $value) {
            return '';
        }

        return match ($value[0]) {
            '"' => $this->parseDoubleQuoted($value, $number),
            "'" => $this->parseSingleQuoted($value, $number),
            default => $this->stripInlineComment($value),
        };
    }

    /**
     * Single quotes are literal: no escapes, so a value may not contain one.
     */
    private function parseSingleQuoted(string $value, int $number): string
    {
        $closing = strpos($value, "'", 1);

        if (false === $closing) {
            throw $this->syntaxError($number, 'unterminated quoted value');
        }

        $this->assertOnlyCommentFollows(substr($value, $closing + 1), $number);

        return substr($value, 1, $closing - 1);
    }

    /**
     * Double quotes support \" \\ \n and \t. The escape handling is the point:
     * scanning for the next quote would stop at an escaped one and silently
     * truncate the value.
     */
    private function parseDoubleQuoted(string $value, int $number): string
    {
        $result = '';
        $length = strlen($value);

        for ($index = 1; $index < $length; ++$index) {
            $character = $value[$index];

            if ('"' === $character) {
                $this->assertOnlyCommentFollows(substr($value, $index + 1), $number);

                return $result;
            }

            if ('\\' !== $character) {
                $result .= $character;

                continue;
            }

            ++$index;

            if ($index >= $length) {
                throw $this->syntaxError($number, 'unterminated escape sequence');
            }

            $result .= match ($value[$index]) {
                '"' => '"',
                '\\' => '\\',
                'n' => "\n",
                't' => "\t",
                default => throw $this->syntaxError(
                    $number,
                    sprintf('unknown escape sequence "\\%s"', $value[$index]),
                ),
            };
        }

        throw $this->syntaxError($number, 'unterminated quoted value');
    }

    private function assertOnlyCommentFollows(string $rest, int $number): void
    {
        $rest = trim($rest);

        if ('' === $rest || str_starts_with($rest, '#')) {
            return;
        }

        throw $this->syntaxError($number, 'unexpected characters after quoted value');
    }

    private function stripInlineComment(string $value): string
    {
        $position = strpos($value, ' #');

        return false === $position ? $value : rtrim(substr($value, 0, $position));
    }

    private function syntaxError(int $number, string $reason): EnvironmentException
    {
        return new EnvironmentException(sprintf(
            'Invalid environment syntax in "%s" at line %d: %s.',
            $this->envFile,
            $number,
            $reason,
        ));
    }

    private function define(string $key, string $value): void
    {
        if (isset($_ENV[$key]) || isset($_SERVER[$key]) || false !== getenv($key)) {
            $existing = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

            if (is_string($existing)) {
                $this->variables[$key] = $existing;
            }

            return;
        }

        $this->variables[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv(sprintf('%s=%s', $key, $value));
    }

    public function get(string $key): ?string
    {
        if (isset($this->variables[$key])) {
            return $this->variables[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : null;
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

        if (null === $value) {
            return $default;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return false === $integer ? $default : $integer;
    }
}
