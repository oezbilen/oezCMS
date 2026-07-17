<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Config;
use OezCMS\Core\DatabaseException;
use OezCMS\Core\MariaDbConnectionFactory;
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
}
