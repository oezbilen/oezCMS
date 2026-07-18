<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\DbDeployCommand;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Core\Container;
use OezCMS\Core\Database;
use OezCMS\Core\DatabaseException;
use PDO;
use PHPUnit\Framework\TestCase;

final class DbDeployCommandTest extends TestCase
{
    private string $databasePath;
    private Database $database;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/oezcms-deploy-test-' . uniqid();
        if (!mkdir($this->databasePath, 0777) && !is_dir($this->databasePath)) {
            self::fail('Unable to create temporary test directory.');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database = new Database($pdo);

        $this->container = new Container();
        $this->container->instance(Database::class, $this->database);
    }

    protected function tearDown(): void
    {
        $files = glob($this->databasePath . '/*/*.sql');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        $directories = glob($this->databasePath . '/*', GLOB_ONLYDIR);
        foreach (false === $directories ? [] : $directories as $directory) {
            rmdir($directory);
        }

        if (is_dir($this->databasePath)) {
            rmdir($this->databasePath);
        }

        parent::tearDown();
    }

    private function writeSqlFile(string $relativePath, string $sql): void
    {
        $path = $this->databasePath . '/' . $relativePath;
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
            self::fail('Unable to create sql directory.');
        }

        if (false === file_put_contents($path, $sql)) {
            self::fail('Unable to write sql file.');
        }
    }

    private function runCommand(Container $container, string $databasePath, BufferedOutput $output): ExitCode
    {
        $command = new DbDeployCommand($container, $databasePath);

        return $command->run(Input::fromArgv(['console', 'db:deploy']), $output);
    }

    public function testAppliesSqlFilesInDependencyOrder(): void
    {
        $this->writeSqlFile('triggers/deploy_touch.sql', 'CREATE TRIGGER deploy_touch AFTER INSERT ON deploy_users BEGIN SELECT 1; END');
        $this->writeSqlFile('views/deploy_user_names.sql', 'CREATE VIEW deploy_user_names AS SELECT name FROM deploy_users');
        $this->writeSqlFile('routines/deploy_users.sql', 'CREATE TABLE deploy_users (id INTEGER PRIMARY KEY, name TEXT)');

        $output = new BufferedOutput();
        $exitCode = $this->runCommand($this->container, $this->databasePath, $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame(
            "Applied routines/deploy_users.sql\n"
            . "Applied views/deploy_user_names.sql\n"
            . "Applied triggers/deploy_touch.sql\n"
            . "Deployed 3 object(s).\n",
            $output->contents(),
        );

        $this->database->execute('INSERT INTO deploy_users (id, name) VALUES (:id, :name)', ['id' => 1, 'name' => 'Alice']);
        self::assertNotNull($this->database->fetchOne('SELECT name FROM deploy_user_names'));
    }

    public function testAppliesFilesAlphabeticallyWithinDirectory(): void
    {
        $this->writeSqlFile('routines/2_beta.sql', 'CREATE VIEW deploy_beta AS SELECT id FROM deploy_alpha');
        $this->writeSqlFile('routines/1_alpha.sql', 'CREATE TABLE deploy_alpha (id INTEGER PRIMARY KEY)');

        $output = new BufferedOutput();
        $exitCode = $this->runCommand($this->container, $this->databasePath, $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame(
            "Applied routines/1_alpha.sql\nApplied routines/2_beta.sql\nDeployed 2 object(s).\n",
            $output->contents(),
        );
    }

    public function testReportsZeroWhenDatabaseDirectoryIsMissing(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->runCommand($this->container, $this->databasePath . '/missing', $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame("Deployed 0 object(s).\n", $output->contents());
    }

    public function testDoesNotResolveDatabaseWhenNothingToDeploy(): void
    {
        $exitCode = $this->runCommand(new Container(), $this->databasePath, new BufferedOutput());

        self::assertSame(ExitCode::Success, $exitCode);
    }

    public function testPropagatesDatabaseFailures(): void
    {
        $this->writeSqlFile('routines/broken.sql', 'NOT VALID SQL');

        $this->expectException(DatabaseException::class);

        $this->runCommand($this->container, $this->databasePath, new BufferedOutput());
    }

    public function testExposesCommandName(): void
    {
        $command = new DbDeployCommand($this->container, $this->databasePath);

        self::assertSame('db:deploy', $command->name());
        self::assertNotSame('', $command->description());
    }

    public function testAppliesMigrationsBeforeObjectDirectories(): void
    {
        $this->writeSqlFile('views/mig_names.sql', 'CREATE VIEW mig_names AS SELECT name FROM mig_users');
        $this->writeSqlFile('migrations/001_create_users.sql', 'CREATE TABLE mig_users (id INTEGER PRIMARY KEY, name TEXT)');

        $output = new BufferedOutput();
        $exitCode = $this->runCommand($this->container, $this->databasePath, $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame(
            "Applied migrations/001_create_users.sql\nApplied views/mig_names.sql\nDeployed 2 object(s).\n",
            $output->contents(),
        );
    }

    public function testRecordsAppliedMigrations(): void
    {
        $this->writeSqlFile('migrations/001_create_users.sql', 'CREATE TABLE mig_users (id INTEGER PRIMARY KEY)');

        $this->runCommand($this->container, $this->databasePath, new BufferedOutput());

        self::assertNotNull($this->database->fetchOne(
            'SELECT migration FROM oezcms_migration WHERE migration = :migration',
            ['migration' => '001_create_users.sql'],
        ));
    }

    public function testSkipsAlreadyAppliedMigrations(): void
    {
        $this->writeSqlFile('migrations/001_create_users.sql', 'CREATE TABLE mig_users (id INTEGER PRIMARY KEY)');

        $first = new BufferedOutput();
        $this->runCommand($this->container, $this->databasePath, $first);
        self::assertSame("Applied migrations/001_create_users.sql\nDeployed 1 object(s).\n", $first->contents());

        $output = new BufferedOutput();
        $exitCode = $this->runCommand($this->container, $this->databasePath, $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertSame("Deployed 0 object(s).\n", $output->contents());
    }

    public function testAppliesOnlyPendingMigrations(): void
    {
        $this->writeSqlFile('migrations/001_create_users.sql', 'CREATE TABLE mig_users (id INTEGER PRIMARY KEY)');
        $this->runCommand($this->container, $this->databasePath, new BufferedOutput());

        $this->writeSqlFile('migrations/002_seed_users.sql', 'INSERT INTO mig_users (id) VALUES (1)');

        $output = new BufferedOutput();
        $this->runCommand($this->container, $this->databasePath, $output);

        self::assertSame("Applied migrations/002_seed_users.sql\nDeployed 1 object(s).\n", $output->contents());
        self::assertNotNull($this->database->fetchOne('SELECT id FROM mig_users WHERE id = :id', ['id' => 1]));
    }

    public function testStopsAtFirstFailingMigration(): void
    {
        $this->writeSqlFile('migrations/001_create_users.sql', 'CREATE TABLE mig_users (id INTEGER PRIMARY KEY)');
        $this->writeSqlFile('migrations/002_broken.sql', 'NOT VALID SQL');
        $this->writeSqlFile('migrations/003_seed_users.sql', 'INSERT INTO mig_users (id) VALUES (1)');

        try {
            $this->runCommand($this->container, $this->databasePath, new BufferedOutput());
            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException) {
            // The deploy stops here; the assertions below pin the partial state.
        }

        self::assertNotNull($this->database->fetchOne(
            'SELECT migration FROM oezcms_migration WHERE migration = :migration',
            ['migration' => '001_create_users.sql'],
        ));
        self::assertNull($this->database->fetchOne(
            'SELECT migration FROM oezcms_migration WHERE migration = :migration',
            ['migration' => '003_seed_users.sql'],
        ));
    }
}
