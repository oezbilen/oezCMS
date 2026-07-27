<?php

declare(strict_types=1);

namespace OezCMS\Console;

use OezCMS\Core\Container;
use OezCMS\Core\Database;
use OezCMS\Core\DatabaseException;
use OezCMS\Core\MigrationDatabase;

final class DbDeployCommand implements Command
{
    /**
     * Dependency order: views may use stored functions, triggers may
     * call procedures.
     */
    private const array OBJECT_DIRECTORIES = ['routines', 'views', 'triggers'];

    private const string MIGRATIONS_DIRECTORY = 'migrations';

    private const string STATUS_STARTED = 'started';
    private const string STATUS_COMPLETED = 'completed';
    private const string STATUS_FAILED = 'failed';

    /**
     * Advisory locks are server-wide, so the database name must be part
     * of the lock name to keep deployments of separate databases apart.
     */
    private const string LOCK_NAME_PREFIX = 'oezcms_db_deploy_';

    private const int DEFAULT_LOCK_TIMEOUT_SECONDS = 30;

    private const string TRACKING_TABLE_DDL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS oezcms_migration (
            migration VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
                COMMENT 'Migration file name, applied exactly once in filename order',
            checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
                COMMENT 'SHA-256 of the executed migration file, line endings normalized',
            status ENUM('started', 'completed', 'failed') NOT NULL
                COMMENT 'started = in progress or interrupted, completed = applied, failed = statement error',
            started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            completed_at DATETIME(3) NULL,
            error_message TEXT NULL
                COMMENT 'Server error message of the last failed attempt',
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
          COMMENT='Tracks schema migrations applied by db:deploy'
        SQL;

    public function __construct(
        private readonly Container $container,
        private readonly string $databasePath,
        private readonly int $lockTimeoutSeconds = self::DEFAULT_LOCK_TIMEOUT_SECONDS,
    ) {
    }

    public function name(): string
    {
        return 'db:deploy';
    }

    public function description(): string
    {
        return 'Apply database migrations and idempotent objects (routines, views, triggers)';
    }

    public function run(Input $input, Output $output): ExitCode
    {
        $migrationFiles = $this->sqlFiles(self::MIGRATIONS_DIRECTORY);

        $objectFiles = [];
        foreach (self::OBJECT_DIRECTORIES as $directory) {
            $objectFiles[$directory] = $this->sqlFiles($directory);
        }

        if ([] === $migrationFiles && [] === array_filter($objectFiles)) {
            $output->writeLine('Nothing to deploy.');

            return ExitCode::Success;
        }

        $database = $this->container->get(MigrationDatabase::class)->database();
        $this->acquireLock($database);

        try {
            $applied = $this->applyMigrations($database, $migrationFiles, $output);

            foreach ($objectFiles as $directory => $files) {
                foreach ($files as $file) {
                    $database->executeRaw($this->readSqlFile($file));
                    $output->writeLine(sprintf('Applied %s/%s', $directory, basename($file)));
                }
            }

            // Counted from the file lists rather than tallied while deploying:
            // every file has run by the time this is reached, since a failure
            // leaves through the exception instead.
            $output->writeLine(sprintf('Migrations applied: %d', $applied));

            foreach ($objectFiles as $directory => $files) {
                $output->writeLine(sprintf('%s refreshed: %d', ucfirst($directory), count($files)));
            }

            return ExitCode::Success;
        } finally {
            $this->releaseLock($database);
        }
    }

    private function readSqlFile(string $file): string
    {
        $sql = file_get_contents($file);

        if (false === $sql) {
            throw new ConsoleException(sprintf('Unable to read %s.', $file));
        }

        // Normalized before hashing AND executing, so the stored checksum
        // always describes exactly the statement that ran, regardless of
        // the platform the file was checked out on.
        $sql = str_replace("\r\n", "\n", $sql);

        $this->assertCarriesStatement($sql, $file);

        return $sql;
    }

    /**
     * Every file in this project opens with a comment header, so "nothing but
     * comments" is the shape an emptied-out file actually takes.
     *
     * Migrations are read while the pending list is collected, which is before
     * any tracking row is written. The driver would reject an empty query too,
     * but only after the migration had been recorded as started and then
     * failed, leaving someone to resolve a failure that never reached the
     * schema.
     */
    private function assertCarriesStatement(string $sql, string $file): void
    {
        // Remove ordinary block comments, but retain executable MySQL and
        // MariaDB comments: /*! ... */ and /*M! ... */.
        $statements = preg_replace('#/\*(?!!|M!).*?\*/#s', '', $sql);

        if (null !== $statements) {
            $statements = preg_replace('/^[\t ]*(?:--(?=[\s\x00]|$)|#).*$/m', '', $statements);
        }

        if (null === $statements || '' === trim($statements)) {
            throw new ConsoleException(sprintf('%s contains no SQL statement.', $file));
        }
    }

    /**
     * @param list<string> $files
     */
    private function applyMigrations(Database $database, array $files, Output $output): int
    {
        if ([] === $files) {
            return 0;
        }

        $database->executeRaw(self::TRACKING_TABLE_DDL);

        $tracked = [];
        foreach ($database->fetchAll('SELECT migration, status, checksum FROM oezcms_migration') as $row) {
            $migration = $row['migration'] ?? null;

            if (!is_string($migration)) {
                continue;
            }

            if (self::STATUS_STARTED === ($row['status'] ?? null)) {
                throw new ConsoleException(sprintf(
                    'Migration %s is in state "started": a previous deploy was interrupted.'
                    . ' Verify the database manually, then delete the tracking row to re-run'
                    . ' the migration or mark it completed.',
                    $migration,
                ));
            }

            $tracked[$migration] = [
                'status' => $row['status'] ?? null,
                'checksum' => $row['checksum'] ?? null,
            ];
        }

        $pending = [];
        foreach ($files as $file) {
            $migration = basename($file);
            $sql = $this->readSqlFile($file);
            $checksum = hash('sha256', $sql);
            $known = $tracked[$migration] ?? null;

            if (null !== $known && self::STATUS_COMPLETED === $known['status']) {
                if ($checksum !== $known['checksum']) {
                    throw new ConsoleException(sprintf(
                        'Migration %s was modified after being applied; applied migrations must not change.',
                        $migration,
                    ));
                }

                continue;
            }

            $pending[] = [
                'migration' => $migration,
                'sql' => $sql,
                'checksum' => $checksum,
                'retry' => null !== $known,
            ];
        }

        $count = 0;
        foreach ($pending as $entry) {
            $this->recordStart($database, $entry['migration'], $entry['checksum'], $entry['retry']);

            try {
                $database->executeRaw($entry['sql']);
            } catch (DatabaseException $exception) {
                $this->recordFailure($database, $entry['migration'], $exception);

                throw new ConsoleException(
                    sprintf('Migration %s failed: %s', $entry['migration'], $exception->getMessage()),
                    previous: $exception,
                );
            }

            $database->execute(
                'UPDATE oezcms_migration SET status = :status, completed_at = CURRENT_TIMESTAMP(3)'
                . ' WHERE migration = :migration',
                ['status' => self::STATUS_COMPLETED, 'migration' => $entry['migration']],
            );

            $output->writeLine(sprintf('Applied %s/%s', self::MIGRATIONS_DIRECTORY, $entry['migration']));
            ++$count;
        }

        return $count;
    }

    private function recordStart(Database $database, string $migration, string $checksum, bool $retry): void
    {
        if ($retry) {
            $database->execute(
                'UPDATE oezcms_migration SET status = :status, checksum = :checksum,'
                . ' started_at = CURRENT_TIMESTAMP(3), completed_at = NULL, error_message = NULL'
                . ' WHERE migration = :migration',
                ['status' => self::STATUS_STARTED, 'checksum' => $checksum, 'migration' => $migration],
            );

            return;
        }

        $database->execute(
            'INSERT INTO oezcms_migration (migration, checksum, status) VALUES (:migration, :checksum, :status)',
            ['migration' => $migration, 'checksum' => $checksum, 'status' => self::STATUS_STARTED],
        );
    }

    private function recordFailure(Database $database, string $migration, DatabaseException $exception): void
    {
        try {
            $database->execute(
                'UPDATE oezcms_migration SET status = :status, error_message = :error'
                . ' WHERE migration = :migration',
                ['status' => self::STATUS_FAILED, 'error' => $exception->getMessage(), 'migration' => $migration],
            );
        } catch (DatabaseException) {
            // The connection may be gone; the row then stays "started", which
            // is the honest state, and recording must not mask the original
            // failure.
        }
    }

    private function acquireLock(Database $database): void
    {
        $row = $database->fetchOne(
            'SELECT GET_LOCK(CONCAT(:prefix, DATABASE()), :timeout) AS acquired',
            ['prefix' => self::LOCK_NAME_PREFIX, 'timeout' => $this->lockTimeoutSeconds],
        );

        if (null === $row || 1 !== $row['acquired']) {
            throw new ConsoleException('Another deployment is already running.');
        }
    }

    private function releaseLock(Database $database): void
    {
        try {
            $database->fetchOne(
                'SELECT RELEASE_LOCK(CONCAT(:prefix, DATABASE())) AS released',
                ['prefix' => self::LOCK_NAME_PREFIX],
            );
        } catch (DatabaseException) {
            // A dead connection has released the lock already; cleanup must
            // not mask why the deploy failed.
        }
    }

    /**
     * @return list<string>
     */
    private function sqlFiles(string $directory): array
    {
        $files = glob($this->databasePath . '/' . $directory . '/*.sql');

        return false === $files ? [] : $files;
    }
}
