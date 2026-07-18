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

        foreach (['routines', 'migrations'] as $directory) {
            if (!mkdir($this->databasePath . '/' . $directory, 0777, true)) {
                self::fail(sprintf('Unable to create temporary %s directory.', $directory));
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_deployed_procedure');
            $this->pdo->exec('DROP TABLE IF EXISTS test_migration_table');
            $this->pdo->exec('DROP TABLE IF EXISTS oezcms_migration');
        }

        $files = glob($this->databasePath . '/*/*.sql');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        foreach ([$this->databasePath . '/routines', $this->databasePath . '/migrations'] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
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

    private function writeMigrationFile(): void
    {
        $sql = 'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)';

        if (false === file_put_contents($this->databasePath . '/migrations/001_create_table.sql', $sql)) {
            self::fail('Unable to write migration file.');
        }
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

    public function testAppliesMigrationOnMariaDb(): void
    {
        $this->writeMigrationFile();

        $output = new BufferedOutput();
        $exitCode = $this->runCommand($output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertStringContainsString('Applied migrations/001_create_table.sql', $output->contents());
        self::assertSame(1, $this->database->execute(
            'INSERT INTO test_migration_table (id) VALUES (:id)',
            ['id' => 1],
        ));
    }

    public function testRerunningMigrationsIsIdempotent(): void
    {
        $this->writeMigrationFile();

        $first = new BufferedOutput();
        self::assertSame(ExitCode::Success, $this->runCommand($first));
        self::assertStringContainsString('Applied migrations/001_create_table.sql', $first->contents());

        $output = new BufferedOutput();
        self::assertSame(ExitCode::Success, $this->runCommand($output));
        self::assertSame("Deployed 0 object(s).\n", $output->contents());
    }
}
