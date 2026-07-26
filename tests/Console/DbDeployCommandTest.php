<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\DbDeployCommand;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Core\Container;
use OezCMS\Core\ContainerException;
use PHPUnit\Framework\TestCase;

final class DbDeployCommandTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/oezcms-deploy-test-' . uniqid();
        if (!mkdir($this->databasePath, 0777) && !is_dir($this->databasePath)) {
            self::fail('Unable to create temporary test directory.');
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->databasePath);

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        foreach ($entries !== false ? $entries : [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }

    private function runCommand(Container $container, string $databasePath, BufferedOutput $output): ExitCode
    {
        $command = new DbDeployCommand($container, $databasePath);

        return $command->run(Input::fromArgv(['console', 'db:deploy']), $output);
    }

    public function testReportsZeroWhenDatabaseDirectoryIsMissing(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->runCommand(new Container(), $this->databasePath . '/missing', $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame("Deployed 0 object(s).\n", $output->contents());
    }

    public function testDoesNotResolveDatabaseWhenNothingToDeploy(): void
    {
        $exitCode = $this->runCommand(new Container(), $this->databasePath, new BufferedOutput());

        self::assertSame(ExitCode::Success, $exitCode);
    }

    public function testExposesCommandName(): void
    {
        $command = new DbDeployCommand(new Container(), $this->databasePath);

        self::assertSame('db:deploy', $command->name());
        self::assertNotSame('', $command->description());
    }

    public function testResolvesTheMigrationConnection(): void
    {
        $this->writeObjectFile('routines', 'fn_test.sql', 'SELECT 1');

        // With nothing registered, the container names the service it was asked
        // for. That name is the observable difference between deploying through
        // the runtime connection and deploying through the migration one.
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/MigrationDatabase/');

        $this->runCommand(new Container(), $this->databasePath, new BufferedOutput());
    }

    private function writeObjectFile(string $directory, string $filename, string $sql): void
    {
        $path = $this->databasePath . '/' . $directory;

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            self::fail('Unable to create temporary object directory.');
        }

        if (false === file_put_contents($path . '/' . $filename, $sql)) {
            self::fail('Unable to write temporary object file.');
        }
    }
}
