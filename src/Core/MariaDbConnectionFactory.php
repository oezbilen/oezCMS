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

    private const string APPEND_SQL_MODE = 'SET SESSION sql_mode = '
        . 'sys.list_add(@@SESSION.sql_mode, \'ONLY_FULL_GROUP_BY\')';

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

    /**
     * @return array<int, bool|int|string>
     */
    public function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            // Stacked queries are never legitimate in this code base.
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => self::APPEND_SQL_MODE,
        ];
    }

    public function create(Config $config): PDO
    {
        return new PDO(
            $this->dsn($config),
            $config->getString('database.username', self::DEFAULT_USERNAME),
            $config->getString('database.password', self::DEFAULT_PASSWORD),
            $this->options(),
        );
    }
}
