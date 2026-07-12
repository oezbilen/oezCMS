<?php

declare(strict_types=1);

namespace OezCMS\Console;

final class Input
{
    /**
     * @param list<string> $arguments
     */
    private function __construct(
        private readonly ?string $command,
        private readonly array $arguments,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        return new self($argv[1] ?? null, array_slice($argv, 2));
    }

    public function command(): ?string
    {
        return $this->command;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
