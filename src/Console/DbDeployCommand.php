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
        return 'Apply idempotent database objects (routines, views, triggers)';
    }

    public function run(Input $input, Output $output): ExitCode
    {
        $deployed = 0;

        foreach (self::OBJECT_DIRECTORIES as $directory) {
            foreach ($this->sqlFiles($directory) as $file) {
                $sql = file_get_contents($file);

                if (false === $sql) {
                    throw new ConsoleException(sprintf('Unable to read %s.', $file));
                }

                $this->container->get(Database::class)->executeRaw($sql);
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
