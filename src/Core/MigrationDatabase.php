<?php

declare(strict_types=1);

namespace OezCMS\Core;

/**
 * The connection used to deploy schema changes, and where its log is kept.
 *
 * The container is keyed by class name, so a second connection to the same
 * database needs a type of its own; asking for the wrong one is then a type
 * error rather than a runtime surprise.
 *
 * With separate migration credentials the log lives in a database of its own:
 * in the application database the runtime's privileges would cover it, and the
 * runtime could rewrite the record of what has been deployed. The name of that
 * database is placed into SQL text, so it passes the same allow-list as every
 * other identifier that is.
 */
final class MigrationDatabase
{
    private const string LOG_TABLE = 'oezcms_migrations';

    public function __construct(
        private readonly Database $database,
        private readonly ?string $logDatabase = null,
    ) {
        if (null !== $logDatabase && 1 !== preg_match(Database::IDENTIFIER_PATTERN, $logDatabase)) {
            throw new DatabaseException(sprintf('Invalid deploy log database name "%s".', $logDatabase));
        }
    }

    public function database(): Database
    {
        return $this->database;
    }

    /**
     * The deploy log's table name, qualified with its database when it has one
     * of its own, ready to be placed into SQL text.
     */
    public function logTable(): string
    {
        if (null === $this->logDatabase) {
            return self::LOG_TABLE;
        }

        return sprintf('`%s`.%s', $this->logDatabase, self::LOG_TABLE);
    }
}
