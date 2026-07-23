<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\DbDeployCommand;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Core\Container;
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
        if (is_dir($this->databasePath)) {
            rmdir($this->databasePath);
        }

        parent::tearDown();
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
}
