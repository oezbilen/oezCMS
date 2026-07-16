<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\Command;
use OezCMS\Console\CommandRegistry;
use OezCMS\Console\ConsoleException;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Console\Output;
use PHPUnit\Framework\TestCase;

final class CommandRegistryTest extends TestCase
{
    public function testGetReturnsRegisteredCommand(): void
    {
        $registry = new CommandRegistry();
        $command = $this->createCommand('cache:clear');

        $registry->register($command);

        self::assertSame($command, $registry->get('cache:clear'));
    }

    public function testGetThrowsForUnknownCommand(): void
    {
        $registry = new CommandRegistry();

        $this->expectException(ConsoleException::class);

        $registry->get('does:not:exist');
    }

    public function testHasReportsRegistration(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand('cache:clear'));

        self::assertTrue($registry->has('cache:clear'));
        self::assertFalse($registry->has('does:not:exist'));
    }

    public function testAllReturnsEveryRegisteredCommand(): void
    {
        $registry = new CommandRegistry();
        $first = $this->createCommand('cache:clear');
        $second = $this->createCommand('user:create');

        $registry->register($first);
        $registry->register($second);

        self::assertSame([$first, $second], $registry->all());
    }

    private function createCommand(string $name): Command
    {
        return new class ($name) implements Command {
            public function __construct(private readonly string $name)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function description(): string
            {
                return 'test command';
            }

            public function run(Input $input, Output $output): ExitCode
            {
                return ExitCode::Success;
            }
        };
    }

    public function testRegisterThrowsForDuplicateName(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand('cache:clear'));

        $this->expectException(ConsoleException::class);

        $registry->register($this->createCommand('cache:clear'));
    }
}
