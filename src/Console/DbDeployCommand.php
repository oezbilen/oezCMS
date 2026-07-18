<?php

declare(strict_types=1);

namespace OezCMS\Console;

use OezCMS\Core\Container;
use OezCMS\Core\Database;

final class DbDeployCommand implements Command
{
    /**
     * Dependency order: views may use stored functions, triggers may
     * call procedures.
     */
    private const array OBJECT_DIRECTORIES = ['routines', 'views', 'triggers'];

    private const string MIGRATIONS_DIRECTORY = 'migrations';

    private const string TRACKING_TABLE_DDL = 'CREATE TABLE IF NOT EXISTS oezcms_migration ('
        . 'migration VARCHAR(255) NOT NULL PRIMARY KEY, '
        . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ')';

    public function __construct(
        private readonly Container $container,
        private readonly string $databasePath,
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

    private function readSqlFile(string $file): string
    {
        $sql = file_get_contents($file);

        if (false === $sql) {
            throw new ConsoleException(sprintf('Unable to read %s.', $file));
        }

        return $sql;
    }

    private function applyMigrations(Output $output): int
    {
        $files = $this->sqlFiles(self::MIGRATIONS_DIRECTORY);

        if ([] === $files) {
            return 0;
        }

        $database = $this->container->get(Database::class);
        $database->executeRaw(self::TRACKING_TABLE_DDL);

        $applied = [];
        foreach ($database->fetchAll('SELECT migration FROM oezcms_migration') as $row) {
            $migration = $row['migration'] ?? null;

            if (is_string($migration)) {
                $applied[$migration] = true;
            }
        }

        $count = 0;

        foreach ($files as $file) {
            $migration = basename($file);

            if (isset($applied[$migration])) {
                continue;
            }

            $database->executeRaw($this->readSqlFile($file));
            $database->execute(
                'INSERT INTO oezcms_migration (migration) VALUES (:migration)',
                ['migration' => $migration],
            );
            $output->writeLine(sprintf('Applied %s/%s', self::MIGRATIONS_DIRECTORY, $migration));
            ++$count;
        }

        return $count;
    }

    public function run(Input $input, Output $output): ExitCode
    {
        $deployed = $this->applyMigrations($output);

        foreach (self::OBJECT_DIRECTORIES as $directory) {
            foreach ($this->sqlFiles($directory) as $file) {
                $this->container->get(Database::class)->executeRaw($this->readSqlFile($file));
                $output->writeLine(sprintf('Applied %s/%s', $directory, basename($file)));
                ++$deployed;
            }
        }

        $output->writeLine(sprintf('Deployed %d object(s).', $deployed));

        return ExitCode::Success;
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
