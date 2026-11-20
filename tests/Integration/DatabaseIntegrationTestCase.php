<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Core\Config;
use OezCMS\Core\Database;
use OezCMS\Core\MariaDbConnectionFactory;
use OezCMS\Tests\Support\TestDatabaseGuard;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseIntegrationTestCase extends TestCase
{
    /**
     * Variables naming a database the application uses. The tests refuse to
     * touch any of them, as far as they are visible in the process environment.
     */
    private const array APPLICATION_DATABASE_VARIABLES = [
        'DB_NAME',
        'DB_MIGRATION_LOG_DATABASE',
    ];

    protected PDO $pdo;
    protected Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refuseUnsafeDatabases();

        $factory = new MariaDbConnectionFactory();
        $this->pdo = $factory->create($this->createConfig());
        $this->database = new Database($this->pdo);
    }

    protected function createConfig(): Config
    {
        return new Config([
            'database' => [
                'host' => $this->optionalEnv('TEST_DB_HOST') ?? '127.0.0.1',
                'port' => (int) ($this->optionalEnv('TEST_DB_PORT') ?? '3306'),
                'name' => $this->testDatabaseName(),
                'username' => $this->requiredEnv('TEST_DB_USERNAME'),
                'password' => $this->optionalEnv('TEST_DB_PASSWORD') ?? '',
            ],
        ]);
    }

    /**
     * The database the tests connect to and, for the schema tests, wipe.
     */
    protected function testDatabaseName(): string
    {
        return $this->requiredEnv('TEST_DB_NAME');
    }

    /**
     * Where db:deploy keeps its log when it is configured to use a database of
     * its own. Throwaway, like the test database itself.
     */
    protected function logDatabaseName(): string
    {
        return $this->optionalEnv('TEST_DB_LOG_NAME') ?? $this->testDatabaseName() . '_log';
    }

    private function refuseUnsafeDatabases(): void
    {
        $applicationDatabases = [];

        foreach (self::APPLICATION_DATABASE_VARIABLES as $variable) {
            $database = $this->optionalEnv($variable);

            if (null !== $database) {
                $applicationDatabases[$variable] = $database;
            }
        }

        $violations = TestDatabaseGuard::violations(
            $this->testDatabaseName(),
            $this->logDatabaseName(),
            $applicationDatabases,
        );

        if ([] !== $violations) {
            self::fail(implode(' ', $violations) . ' Refusing to run against it.');
        }
    }

    private function requiredEnv(string $key): string
    {
        $value = $this->optionalEnv($key);

        if (null === $value) {
            self::markTestSkipped(sprintf('Set %s to run MariaDB integration tests.', $key));
        }

        return $value;
    }

    private function optionalEnv(string $key): ?string
    {
        $value = getenv($key);

        if (false === $value || '' === $value) {
            return null;
        }

        return $value;
    }
}
