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
     * @param list<string> $arguments
     */
    private function runConsole(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/console', ...$arguments]);
        $process->run();

        return $process;
    }

    public function testUsageListsDbDeployCommand(): void
    {
        $process = $this->runConsole([]);

        self::assertStringContainsString('db:deploy', $process->getErrorOutput());
    }
}
