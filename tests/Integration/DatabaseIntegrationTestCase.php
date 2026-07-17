<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Core\Config;
use OezCMS\Core\Database;
use OezCMS\Core\MariaDbConnectionFactory;
use PHPUnit\Framework\TestCase;

abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = new MariaDbConnectionFactory();
        $this->database = new Database($factory->create($this->createConfig()));
    }

    private function createConfig(): Config
    {
        return new Config([
            'database' => [
                'host' => $this->requiredEnv('TEST_DB_HOST', '127.0.0.1'),
                'port' => (int) $this->requiredEnv('TEST_DB_PORT', '3306'),
                'name' => $this->requiredEnv('TEST_DB_NAME', 'oezcms_test'),
                'username' => $this->requiredEnv('TEST_DB_USERNAME', 'root'),
                'password' => $this->requiredEnv('TEST_DB_PASSWORD', ''),
            ],
        ]);
    }

    private function requiredEnv(string $key, ?string $default = null): string
    {
        $value = getenv($key);

        if (false === $value || '' === $value) {
            if (null !== $default) {
                return $default;
            }
            self::markTestSkipped(sprintf('Set %s to run MariaDB integration tests.', $key));
        }

        return $value;
    }
}
