<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Config;
use OezCMS\Core\Database;
use OezCMS\Core\Environment;
use OezCMS\Core\Kernel;
use OezCMS\Core\MigrationDatabase;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private string $basePath;
    private string $envFile;
    private const array ENVIRONMENT_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_NAME',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_CHARSET',
        'DB_MIGRATION_USERNAME',
        'DB_MIGRATION_PASSWORD',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/oezcms-kernel-test-' . uniqid();
        if (!mkdir($this->basePath, 0777, true) && !is_dir($this->basePath)) {
            self::fail('Unable to create temporary test directory.');
        }


        $this->envFile = $this->basePath . '/.env';
        $contents = "APP_ENV=testing\nAPP_DEBUG=true\nAPP_NAME=oezCMS\n"
            . "DB_HOST=db.example.com\nDB_PORT=3307\nDB_NAME=oezcms\n";
        if (false === file_put_contents($this->envFile, $contents) && !is_file($this->envFile)) {
            self::fail('Unable to create temporary env file.');
        }

        $this->clearEnvironment();
    }

    protected function tearDown(): void
    {
        $this->clearEnvironment();

        if (is_file($this->envFile)) {
            unlink($this->envFile);
        }

        if (is_dir($this->basePath)) {
            rmdir($this->basePath);
        }

        parent::tearDown();
    }

    private function clearEnvironment(): void
    {
        foreach (self::ENVIRONMENT_KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    private function appendToEnvFile(string $contents): void
    {
        if (false === file_put_contents($this->envFile, $contents, FILE_APPEND)) {
            self::fail('Unable to extend the temporary env file.');
        }
    }

    public function testBootRegistersEnvironment(): void
    {
        $kernel = new Kernel($this->envFile);
        $container = $kernel->boot();

        $environment = $container->get(Environment::class);

        self::assertSame('oezCMS', $environment->getString('APP_NAME'));
    }

    public function testBootRegistersConfigBuiltFromEnvironment(): void
    {
        $container = (new Kernel($this->envFile))->boot();

        $config = $container->get(Config::class);

        self::assertSame('testing', $config->getString('app.env'));
        self::assertTrue($config->getBool('app.debug'));
        self::assertSame('db.example.com', $config->getString('database.host'));
        self::assertSame(3307, $config->getInt('database.port'));
        self::assertSame('oezcms', $config->getString('database.name'));
    }

    public function testConfigOmitsAbsentDatabaseKeys(): void
    {
        $container = (new Kernel($this->envFile))->boot();

        $config = $container->get(Config::class);

        self::assertFalse($config->has('database.charset'));
    }

    public function testBootRegistersLazyDatabaseFactory(): void
    {
        $container = (new Kernel($this->envFile))->boot();

        self::assertTrue($container->has(Database::class));
    }

    public function testBootWithoutEnvFileStillBootsContainer(): void
    {
        $kernel = new Kernel($this->basePath . '/missing.env');

        $container = $kernel->boot();

        self::assertTrue($container->has(Database::class));
        self::assertSame('production', $container->get(Config::class)->getString('app.env'));
    }

    public function testBootWithoutEnvFileReadsProcessEnvironment(): void
    {
        putenv('APP_ENV=staging');

        $container = (new Kernel($this->basePath . '/missing.env'))->boot();

        self::assertSame('staging', $container->get(Config::class)->getString('app.env'));
    }

    public function testConfigMapsMigrationCredentials(): void
    {
        $this->appendToEnvFile("DB_MIGRATION_USERNAME=oezcms_deploy\nDB_MIGRATION_PASSWORD=deploy-secret\n");

        $config = (new Kernel($this->envFile))->boot()->get(Config::class);

        self::assertSame('oezcms_deploy', $config->getString('database.migration.username'));
        self::assertSame('deploy-secret', $config->getString('database.migration.password'));
    }

    public function testConfigOmitsAbsentMigrationCredentials(): void
    {
        $config = (new Kernel($this->envFile))->boot()->get(Config::class);

        // The factory reads a present key as a configured credential, so an absent
        // one must stay absent rather than be written as an empty string — that
        // would make half a pair look configured and trip the pair rule.
        self::assertFalse($config->has('database.migration.username'));
        self::assertFalse($config->has('database.migration.password'));
    }

    public function testBootRegistersLazyMigrationDatabaseFactory(): void
    {
        $container = (new Kernel($this->envFile))->boot();

        self::assertTrue($container->has(MigrationDatabase::class));
    }
}
