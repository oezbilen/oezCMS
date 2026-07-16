<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use LogicException;
use OezCMS\Console\Application;
use OezCMS\Console\BufferedOutput;
use OezCMS\Console\Command;
use OezCMS\Console\CommandRegistry;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Console\Output;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    public function testRunsResolvedCommandAndReturnsItsExitCode(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand(
            'greet',
            static function (Input $input, Output $output): ExitCode {
                $output->writeLine(sprintf('Hello %s', $input->arguments()[0] ?? 'world'));

                return ExitCode::Success;
            },
        ));
        $application = new Application($registry);
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $exitCode = $application->run(
            Input::fromArgv(['bin/console', 'greet', 'Alice']),
            $output,
            $errorOutput,
        );

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame("Hello Alice\n", $output->contents());
        self::assertSame('', $errorOutput->contents());
    }

    public function testReturnsUsageWhenNoCommandGiven(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand('greet'));
        $application = new Application($registry);
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $exitCode = $application->run(Input::fromArgv(['bin/console']), $output, $errorOutput);

        self::assertSame(ExitCode::Usage, $exitCode);
        self::assertStringContainsString('Usage:', $errorOutput->contents());
        self::assertStringContainsString('greet', $errorOutput->contents());
        self::assertSame('', $output->contents());
    }

    public function testReturnsUsageForUnknownCommand(): void
    {
        $application = new Application(new CommandRegistry());
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $exitCode = $application->run(Input::fromArgv(['bin/console', 'nope']), $output, $errorOutput);

        self::assertSame(ExitCode::Usage, $exitCode);
        self::assertStringContainsString('Unknown command: nope', $errorOutput->contents());
        self::assertStringContainsString('Usage:', $errorOutput->contents());
        self::assertSame('', $output->contents());
    }

    public function testTranslatesCommandExceptionIntoFailure(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand(
            'broken',
            static function (): ExitCode {
                throw new RuntimeException('boom');
            },
        ));
        $application = new Application($registry);
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $exitCode = $application->run(Input::fromArgv(['bin/console', 'broken']), $output, $errorOutput);

        self::assertSame(ExitCode::Failure, $exitCode);
        self::assertStringContainsString('boom', $errorOutput->contents());
        self::assertSame('', $output->contents());
    }

    /**
     * @param ?callable(Input, Output): ExitCode $runner
     */
    private function createCommand(string $name, ?callable $runner = null): Command
    {
        return new class ($name, $runner) implements Command {
            /**
             * @param ?callable(Input, Output): ExitCode $runner
             */
            public function __construct(
                private readonly string $name,
                private $runner = null,
            ) {
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
                return null === $this->runner ? ExitCode::Success : ($this->runner)($input, $output);
            }
        };
    }

    private function brokenRegistry(): CommandRegistry
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand('broken', static function (): ExitCode {
            throw new RuntimeException('boom');
        }));

        return $registry;
    }

    public function testDoesNotWriteDiagnosticsByDefault(): void
    {
        $application = new Application($this->brokenRegistry());
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $application->run(Input::fromArgv(['bin/console', 'broken']), $output, $errorOutput);

        self::assertStringContainsString('boom', $errorOutput->contents());
        self::assertStringNotContainsString(RuntimeException::class, $errorOutput->contents());
        self::assertStringNotContainsString('#0', $errorOutput->contents());
    }

    public function testWritesDiagnosticsInDebugMode(): void
    {
        $application = new Application($this->brokenRegistry(), true);
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $exitCode = $application->run(Input::fromArgv(['bin/console', 'broken']), $output, $errorOutput);

        self::assertSame(ExitCode::Failure, $exitCode);
        self::assertStringContainsString('boom', $errorOutput->contents());
        self::assertStringContainsString(RuntimeException::class, $errorOutput->contents());
        self::assertStringContainsString('#0', $errorOutput->contents());
    }

    public function testWritesPreviousExceptionChainInDebugMode(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->createCommand('broken', static function (): ExitCode {
            throw new RuntimeException('outer', 0, new LogicException('inner cause'));
        }));
        $application = new Application($registry, true);
        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();

        $application->run(Input::fromArgv(['bin/console', 'broken']), $output, $errorOutput);

        self::assertStringContainsString('inner cause', $errorOutput->contents());
        self::assertStringContainsString(LogicException::class, $errorOutput->contents());
    }
}
