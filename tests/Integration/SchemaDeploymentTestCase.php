<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\DbDeployCommand;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Core\Container;
use OezCMS\Core\MigrationDatabase;

abstract class SchemaDeploymentTestCase extends DatabaseIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->wipeDatabase();
        $this->deploySchema();
    }

    private function wipeDatabase(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($this->schemaNames('SELECT EVENT_NAME AS name FROM information_schema.events WHERE EVENT_SCHEMA = DATABASE()') as $event) {
                $this->pdo->exec(sprintf('DROP EVENT IF EXISTS %s', $event));
            }

            foreach ($this->schemaNames('SELECT TRIGGER_NAME AS name FROM information_schema.triggers WHERE TRIGGER_SCHEMA = DATABASE()') as $trigger) {
                $this->pdo->exec(sprintf('DROP TRIGGER IF EXISTS %s', $trigger));
            }

            foreach ($this->schemaNames("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'VIEW'") as $view) {
                $this->pdo->exec(sprintf('DROP VIEW IF EXISTS %s', $view));
            }

            foreach ($this->schemaNames("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $table) {
                $this->pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $table));
            }

            foreach ($this->schemaNames("SELECT ROUTINE_NAME AS name FROM information_schema.routines WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'FUNCTION'") as $function) {
                $this->pdo->exec(sprintf('DROP FUNCTION IF EXISTS %s', $function));
            }

            foreach ($this->schemaNames("SELECT ROUTINE_NAME AS name FROM information_schema.routines WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'") as $procedure) {
                $this->pdo->exec(sprintf('DROP PROCEDURE IF EXISTS %s', $procedure));
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * @return list<string>
     */
    private function schemaNames(string $sql): array
    {
        $names = [];

        foreach ($this->database->fetchAll($sql) as $row) {
            $name = $row['name'] ?? null;

            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function deploySchema(): void
    {
        $container = new Container();
        $container->instance(MigrationDatabase::class, new MigrationDatabase($this->database));

        $command = new DbDeployCommand($container, dirname(__DIR__, 2) . '/database');
        $exitCode = $command->run(Input::fromArgv(['console', 'db:deploy']), new BufferedOutput());

        if (ExitCode::Success !== $exitCode) {
            self::fail('Schema deployment failed.');
        }
    }
}
