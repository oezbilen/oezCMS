<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\ConsoleException;
use OezCMS\Console\DbDeployCommand;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Core\Container;
use OezCMS\Core\DatabaseException;
use OezCMS\Core\MariaDbConnectionFactory;
use OezCMS\Core\MigrationDatabase;

final class DbDeployIntegrationTest extends DatabaseIntegrationTestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/oezcms-deploy-integration-' . uniqid();

        foreach (['migrations', 'routines', 'views', 'triggers'] as $directory) {
            if (!mkdir($this->databasePath . '/' . $directory, 0777, true)) {
                self::fail(sprintf('Unable to create temporary %s directory.', $directory));
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_deployed_procedure');
            $this->pdo->exec('DROP VIEW IF EXISTS test_migration_view');
            $this->pdo->exec('DROP TABLE IF EXISTS test_migration_table');
            $this->pdo->exec('DROP TABLE IF EXISTS test_migration_table_two');
            $this->pdo->exec('DROP TABLE IF EXISTS oezcms_migration');
        }

        $files = glob($this->databasePath . '/*/*.sql');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        foreach (['migrations', 'routines', 'views', 'triggers'] as $directory) {
            $path = $this->databasePath . '/' . $directory;
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        if (is_dir($this->databasePath)) {
            rmdir($this->databasePath);
        }

        parent::tearDown();
    }

    private function writeObjectFile(string $directory, string $filename, string $sql): void
    {
        if (false === file_put_contents($this->databasePath . '/' . $directory . '/' . $filename, $sql)) {
            self::fail(sprintf('Unable to write %s/%s.', $directory, $filename));
        }
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

    private function runCommand(BufferedOutput $output, int $lockTimeoutSeconds = 30): ExitCode
    {
        $container = new Container();
        $container->instance(MigrationDatabase::class, new MigrationDatabase($this->database));

        $command = new DbDeployCommand($container, $this->databasePath, $lockTimeoutSeconds);

        return $command->run(Input::fromArgv(['console', 'db:deploy']), $output);
    }

    private function writeMigrationFile(string $filename, string $sql): void
    {
        if (false === file_put_contents($this->databasePath . '/migrations/' . $filename, $sql)) {
            self::fail(sprintf('Unable to write migration file %s.', $filename));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingRow(string $migration): array
    {
        $row = $this->database->fetchOne(
            'SELECT checksum, status, started_at, completed_at, error_message'
            . ' FROM oezcms_migration WHERE migration = :migration',
            ['migration' => $migration],
        );

        self::assertNotNull($row, sprintf('Expected a tracking row for %s.', $migration));

        return $row;
    }

    private function assertTableDoesNotExist(string $table): void
    {
        $row = $this->database->fetchOne(
            'SELECT COUNT(*) AS occurrences FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table],
        );

        self::assertNotNull($row);
        self::assertSame(0, $row['occurrences'], sprintf('Expected table %s not to exist.', $table));
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
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );

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
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );

        $first = new BufferedOutput();
        self::assertSame(ExitCode::Success, $this->runCommand($first));
        self::assertStringContainsString('Applied migrations/001_create_table.sql', $first->contents());

        $output = new BufferedOutput();
        self::assertSame(ExitCode::Success, $this->runCommand($output));
        self::assertSame("Deployed 0 object(s).\n", $output->contents());
    }

    public function testDeployRecordsCompletedStatusWithChecksum(): void
    {
        $sql = "CREATE TABLE test_migration_table (\r\n    id INT NOT NULL PRIMARY KEY\r\n)";
        $this->writeMigrationFile('001_create_table.sql', $sql);

        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));

        $row = $this->trackingRow('001_create_table.sql');
        self::assertSame('completed', $row['status']);
        self::assertSame(hash('sha256', str_replace("\r\n", "\n", $sql)), $row['checksum']);
        self::assertNotNull($row['completed_at']);
    }

    public function testFailedMigrationRecordsErrorMessage(): void
    {
        $this->writeMigrationFile(
            '001_broken.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY',
        );

        try {
            $this->runCommand(new BufferedOutput());
            self::fail('Expected the broken migration to raise a ConsoleException.');
        } catch (ConsoleException $exception) {
            self::assertStringContainsString('001_broken.sql', $exception->getMessage());
        }

        $row = $this->trackingRow('001_broken.sql');
        self::assertSame('failed', $row['status']);
        self::assertIsString($row['error_message']);
        self::assertNotSame('', $row['error_message']);
    }

    public function testFixedMigrationIsRetriedAfterFailure(): void
    {
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY',
        );

        try {
            $this->runCommand(new BufferedOutput());
            self::fail('Expected the broken migration to raise a ConsoleException.');
        } catch (ConsoleException) {
            // Expected: the file gets fixed below and the deploy retried.
        }

        $fixed = 'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)';
        $this->writeMigrationFile('001_create_table.sql', $fixed);

        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));
        self::assertSame(1, $this->database->execute(
            'INSERT INTO test_migration_table (id) VALUES (:id)',
            ['id' => 1],
        ));

        $row = $this->trackingRow('001_create_table.sql');
        self::assertSame('completed', $row['status']);
        self::assertSame(hash('sha256', $fixed), $row['checksum']);
    }

    public function testAbortsWhenInterruptedMigrationDetected(): void
    {
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );
        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));

        $this->database->execute(
            "UPDATE oezcms_migration SET status = 'started', completed_at = NULL"
            . ' WHERE migration = :migration',
            ['migration' => '001_create_table.sql'],
        );
        $this->writeMigrationFile(
            '002_second.sql',
            'CREATE TABLE test_migration_table_two (id INT NOT NULL PRIMARY KEY)',
        );

        try {
            $this->runCommand(new BufferedOutput());
            self::fail('Expected the interrupted migration to abort the deploy.');
        } catch (ConsoleException $exception) {
            self::assertStringContainsString('001_create_table.sql', $exception->getMessage());
            self::assertStringContainsString('started', $exception->getMessage());
        }

        $this->assertTableDoesNotExist('test_migration_table_two');
    }

    public function testAbortsWhenCompletedMigrationWasModified(): void
    {
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );
        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));

        $this->writeMigrationFile(
            '001_create_table.sql',
            "CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)\n-- tampered",
        );
        $this->writeMigrationFile(
            '002_second.sql',
            'CREATE TABLE test_migration_table_two (id INT NOT NULL PRIMARY KEY)',
        );

        try {
            $this->runCommand(new BufferedOutput());
            self::fail('Expected the modified migration to abort the deploy.');
        } catch (ConsoleException $exception) {
            self::assertStringContainsString('001_create_table.sql', $exception->getMessage());
            self::assertStringContainsString('modified', $exception->getMessage());
        }

        $this->assertTableDoesNotExist('test_migration_table_two');
    }

    public function testAdvisoryLockGuardsDeploy(): void
    {
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );
        self::assertSame(ExitCode::Success, $this->runCommand(new BufferedOutput()));

        $blocker = (new MariaDbConnectionFactory())->create($this->createConfig());

        // The completed run must have released its lock, otherwise this acquisition fails.
        $statement = $blocker->query("SELECT GET_LOCK(CONCAT('oezcms_db_deploy_', DATABASE()), 0)");
        self::assertNotFalse($statement);
        self::assertSame(1, $statement->fetchColumn());

        try {
            $this->runCommand(new BufferedOutput(), 0);
            self::fail('Expected the deploy to fail while the advisory lock is held.');
        } catch (ConsoleException $exception) {
            self::assertStringContainsString('Another deployment', $exception->getMessage());
        } finally {
            $blocker->query("SELECT RELEASE_LOCK(CONCAT('oezcms_db_deploy_', DATABASE()))");
        }
    }

    public function testAppliesDirectoriesInDependencyOrder(): void
    {
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );
        $this->writeProcedureFile();
        $this->writeObjectFile(
            'views',
            'test_migration_view.sql',
            'CREATE OR REPLACE VIEW test_migration_view AS SELECT id FROM test_migration_table',
        );
        $this->writeObjectFile(
            'triggers',
            'test_migration_trigger.sql',
            'CREATE OR REPLACE TRIGGER test_migration_trigger BEFORE INSERT ON test_migration_table'
            . ' FOR EACH ROW SET @test_migration_trigger := 1',
        );

        $output = new BufferedOutput();
        self::assertSame(ExitCode::Success, $this->runCommand($output));
        self::assertSame(
            "Applied migrations/001_create_table.sql\n"
            . "Applied routines/test_deployed_procedure.sql\n"
            . "Applied views/test_migration_view.sql\n"
            . "Applied triggers/test_migration_trigger.sql\n"
            . "Deployed 4 object(s).\n",
            $output->contents(),
        );
    }

    public function testStopsAtFirstFailingMigration(): void
    {
        $this->writeMigrationFile(
            '001_create_table.sql',
            'CREATE TABLE test_migration_table (id INT NOT NULL PRIMARY KEY)',
        );
        $this->writeMigrationFile('002_broken.sql', 'NOT VALID SQL');
        $this->writeMigrationFile(
            '003_seed.sql',
            'INSERT INTO test_migration_table (id) VALUES (1)',
        );

        try {
            $this->runCommand(new BufferedOutput());
            self::fail('Expected the broken migration to abort the deploy.');
        } catch (ConsoleException $exception) {
            self::assertStringContainsString('002_broken.sql', $exception->getMessage());
        }

        self::assertSame('completed', $this->trackingRow('001_create_table.sql')['status']);
        self::assertSame('failed', $this->trackingRow('002_broken.sql')['status']);
        self::assertNull($this->database->fetchOne(
            'SELECT migration FROM oezcms_migration WHERE migration = :migration',
            ['migration' => '003_seed.sql'],
        ));
    }

    public function testPropagatesObjectFailures(): void
    {
        $this->writeObjectFile('routines', 'broken.sql', 'NOT VALID SQL');

        $this->expectException(DatabaseException::class);

        $this->runCommand(new BufferedOutput());
    }
}
