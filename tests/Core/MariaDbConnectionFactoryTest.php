<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Config;
use OezCMS\Core\DatabaseException;
use OezCMS\Core\MariaDbConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class MariaDbConnectionFactoryTest extends TestCase
{
    public function testBuildsDsnFromConfig(): void
    {
        $config = new Config([
            'database' => [
                'host' => 'db.example.com',
                'port' => 3307,
                'name' => 'oezcms',
                'charset' => 'utf8mb4',
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        self::assertSame(
            'mysql:host=db.example.com;port=3307;dbname=oezcms;charset=utf8mb4',
            $factory->dsn($config),
        );
    }

    public function testDsnUsesDefaultsForMissingValues(): void
    {
        $config = new Config([
            'database' => [
                'name' => 'oezcms',
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        self::assertSame(
            'mysql:host=127.0.0.1;port=3306;dbname=oezcms;charset=utf8mb4',
            $factory->dsn($config),
        );
    }

    public function testThrowsWhenDatabaseNameIsMissing(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config([]));
    }

    public function testThrowsWhenDatabaseNameIsEmpty(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config(['database' => ['name' => '']]));
    }

    public function testOptionsDisableMultiStatementExecution(): void
    {
        $options = (new MariaDbConnectionFactory())->options();

        self::assertArrayHasKey(PDO::MYSQL_ATTR_MULTI_STATEMENTS, $options);
        self::assertFalse($options[PDO::MYSQL_ATTR_MULTI_STATEMENTS]);
    }

    public function testOptionsAppendOnlyFullGroupByToSessionSqlMode(): void
    {
        $options = (new MariaDbConnectionFactory())->options();

        self::assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);

        $command = $options[PDO::MYSQL_ATTR_INIT_COMMAND];

        self::assertIsString($command);
        self::assertStringContainsString('CONCAT_WS', $command);
        self::assertStringContainsString('@@SESSION.sql_mode', $command);
        self::assertStringContainsString('ONLY_FULL_GROUP_BY', $command);
    }

    public function testOptionsKeepSecurePdoDefaults(): void
    {
        $options = (new MariaDbConnectionFactory())->options();

        self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        self::assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        self::assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        self::assertFalse($options[PDO::ATTR_STRINGIFY_FETCHES]);
    }
}
