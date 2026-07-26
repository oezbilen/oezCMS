<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;

final class MariaDbConnectionFactory
{
    private const string DEFAULT_HOST = '127.0.0.1';
    private const int DEFAULT_PORT = 3306;
    private const string DEFAULT_CHARSET = 'utf8mb4';

    private const string SQL_MODE_INIT_COMMAND = 'SET SESSION sql_mode = IF('
        . "FIND_IN_SET('ONLY_FULL_GROUP_BY', @@SESSION.sql_mode) > 0,"
        . '@@SESSION.sql_mode,'
        . "CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'ONLY_FULL_GROUP_BY')"
        . ')';

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
     * Credentials are required configuration; there is no default.
     *
     * A missing username used to fall back to the most privileged account the
     * server offers, so an incomplete environment failed at the point where it
     * did the most damage instead of at boot.
     */
    public function username(Config $config): string
    {
        $username = $config->getString('database.username');

        if ('' === $username) {
            throw new DatabaseException('Missing required configuration: database.username');
        }

        return $username;
    }

    /**
     * An empty password is legitimate under socket authentication, so the key
     * must be present but may be empty. Only its absence is a misconfiguration,
     * which is why this checks existence rather than the value.
     */
    public function password(Config $config): string
    {
        if (!$config->has('database.password')) {
            throw new DatabaseException('Missing required configuration: database.password');
        }

        return $config->getString('database.password');
    }

    /**
     * Deployment may run as a different account than the runtime, so the runtime
     * does not need the DDL privileges it never uses. Everything else about the
     * connection stays shared: it is the same database, only a different login.
     *
     * Absent migration credentials fall back to the runtime ones, which keeps the
     * separation opt-in.
     */
    public function migrationUsername(Config $config): string
    {
        if (!$this->hasMigrationCredentials($config)) {
            return $this->username($config);
        }

        $username = $config->getString('database.migration.username');

        if ('' === $username) {
            throw new DatabaseException('Missing required configuration: database.migration.username');
        }

        return $username;
    }

    /**
     * As with the runtime password, an empty value is legitimate under socket
     * authentication; only an absent one is a misconfiguration.
     */
    public function migrationPassword(Config $config): string
    {
        if (!$this->hasMigrationCredentials($config)) {
            return $this->password($config);
        }

        return $config->getString('database.migration.password');
    }

    /**
     * The two migration values are configured together or not at all. Half a pair
     * would silently combine one of them with a runtime value and fail to
     * authenticate for a reason the configuration does not show.
     */
    private function hasMigrationCredentials(Config $config): bool
    {
        $hasUsername = $config->has('database.migration.username');
        $hasPassword = $config->has('database.migration.password');

        if ($hasUsername !== $hasPassword) {
            throw new DatabaseException(
                'Migration credentials must be configured together: '
                . 'database.migration.username and database.migration.password.',
            );
        }

        return $hasUsername;
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
            PDO::MYSQL_ATTR_INIT_COMMAND => self::SQL_MODE_INIT_COMMAND,
        ];
    }

    public function create(Config $config): PDO
    {
        return new PDO(
            $this->dsn($config),
            $this->username($config),
            $this->password($config),
            $this->options(),
        );
    }

    public function createForMigrations(Config $config): PDO
    {
        return new PDO(
            $this->dsn($config),
            $this->migrationUsername($config),
            $this->migrationPassword($config),
            $this->options(),
        );
    }
}
