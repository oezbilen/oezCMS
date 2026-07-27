<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ConsoleBootstrapTest extends TestCase
{
    public function testVersionCommandWritesToStdout(): void
    {
        $process = $this->runConsole(['version']);

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('oezCMS', $process->getOutput());
        self::assertSame('', $process->getErrorOutput());
    }

    public function testMissingCommandWritesUsageToStderr(): void
    {
        $process = $this->runConsole([]);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('Usage:', $process->getErrorOutput());
        self::assertSame('', $process->getOutput());
    }

    public function testUnknownCommandWritesErrorToStderr(): void
    {
        $process = $this->runConsole(['nope']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('Unknown command: nope', $process->getErrorOutput());
        self::assertStringContainsString('Usage:', $process->getErrorOutput());
        self::assertSame('', $process->getOutput());
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $environment Merged into the inherited environment
     */
    private function runConsole(array $arguments, array $environment = []): Process
    {
        $process = new Process(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/console', ...$arguments],
            env: $environment,
        );
        $process->run();

        return $process;
    }

    public function testUsageListsDbDeployCommand(): void
    {
        $process = $this->runConsole([]);

        self::assertStringContainsString('db:deploy', $process->getErrorOutput());
    }

    public function testBootstrapFailureIsReportedWithoutAStackTrace(): void
    {
        // A rejected APP_ENV fails inside Kernel::boot(), before the Application
        // exists that would otherwise translate the exception.
        $process = $this->runConsole(['version'], ['APP_ENV' => 'nonsense']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Error: Invalid configuration: APP_ENV', $process->getErrorOutput());
        self::assertStringNotContainsString('Stack trace', $process->getErrorOutput());
        self::assertSame('', $process->getOutput());
    }

    public function testBootstrapFailureWritesDiagnosticsInDebugMode(): void
    {
        $process = $this->runConsole(['version'], ['APP_ENV' => 'nonsense', 'APP_DEBUG' => 'true']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('EnvironmentException', $process->getErrorOutput());
    }
}
