<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\DbDeployCommand;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Core\Container;
use OezCMS\Core\Database;

final class DbDeployIntegrationTest extends DatabaseIntegrationTestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/oezcms-deploy-integration-' . uniqid();
        if (!mkdir($this->databasePath . '/routines', 0777, true)) {
            self::fail('Unable to create temporary routines directory.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_deployed_procedure');
        }

        $files = glob($this->databasePath . '/routines/*.sql');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        if (is_dir($this->databasePath . '/routines')) {
            rmdir($this->databasePath . '/routines');
        }

        if (is_dir($this->databasePath)) {
            rmdir($this->databasePath);
        }

        parent::tearDown();
    }

    private function writeProcedureFile(): void
    {
        $sql = <<<'SQL'
            CREATE OR REPLACE PROCEDURE test_deployed_procedure()
            BEGIN
                SELECT 1 AS deployed_value;
            END
            SQL;

        if (false === file_put_contents($this->databasePath . '/routines/test_deployed_procedure.sql', $sql)) {
            self::fail('Unable to write procedure file.');
        }
    }

    private function runCommand(BufferedOutput $output): ExitCode
    {
        $container = new Container();
        $container->instance(Database::class, $this->database);

        $command = new DbDeployCommand($container, $this->databasePath);

        return $command->run(Input::fromArgv(['console', 'db:deploy']), $output);
    }

    public function testDeploysProcedureThatIsCallableRightAway(): void
    {
        $this->writeProcedureFile();

        $output = new BufferedOutput();
        $exitCode = $this->runCommand($output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertStringContainsString('Applied routines/test_deployed_procedure.sql', $output->contents());

        $resultSets = $this->database->callProcedure('test_deployed_procedure');
        self::assertSame([['deployed_value' => 1]], $resultSets[0]);
    }

    public function testDeployingTwiceSucceeds(): void
    {
        $this->writeProcedureFile();

        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));
        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));
    }
}
