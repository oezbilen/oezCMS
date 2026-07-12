<?php

declare(strict_types=1);

namespace OezCMS\Console;

final class CommandRegistry
{
    /**
     * @var array<string, Command>
     */
    private array $commands = [];

    public function register(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    public function get(string $name): Command
    {
        return $this->commands[$name]
            ?? throw new ConsoleException(sprintf('Unknown command: %s', $name));
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return list<Command>
     */
    public function all(): array
    {
        return array_values($this->commands);
    }
}
