<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class PdoConnectionFactory
{
    private const string DEFAULT_HOST = '127.0.0.1';
    private const int DEFAULT_PORT = 3306;
    private const string DEFAULT_CHARSET = 'utf8mb4';

    public function dsn(Config $config): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->getString('database.host', self::DEFAULT_HOST),
            $config->getInt('database.port', self::DEFAULT_PORT),
            $config->getString('database.name'),
            $config->getString('database.charset', self::DEFAULT_CHARSET),
        );
    }
}
