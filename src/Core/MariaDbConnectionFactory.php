<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;

final class MariaDbConnectionFactory
{
    private const string DEFAULT_HOST = '127.0.0.1';
    private const int DEFAULT_PORT = 3306;
    private const string DEFAULT_CHARSET = 'utf8mb4';
    private const string DEFAULT_USERNAME = 'root';
    private const string DEFAULT_PASSWORD = '';

    public function dsn(Config $config): string
    {
        $name = $config->getString('database.name');

        if ('' === $name) {
            throw new DatabaseException('Missing required configuration: database.name');
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->getString('database.host', self::DEFAULT_HOST),
            $config->getInt('database.port', self::DEFAULT_PORT),
            $name,
            $config->getString('database.charset', self::DEFAULT_CHARSET),
        );
    }

    public function create(Config $config): PDO
    {
        return new PDO(
            $this->dsn($config),
            $config->getString('database.username', self::DEFAULT_USERNAME),
            $config->getString('database.password', self::DEFAULT_PASSWORD),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }
}
