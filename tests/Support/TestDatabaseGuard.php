<?php

declare(strict_types=1);

namespace OezCMS\Tests\Support;

/**
 * Decides whether the integration tests may use a pair of databases.
 *
 * The schema tests drop every object in the test database before each test, and
 * the deploy tests drop the deploy log in the log database. That is safe only
 * when the names say the databases are throwaway: the test database has to end
 * in "_test", the log database in "_test_log", and neither may be a database the
 * application is configured to use. The two suffixes also keep the pair apart,
 * since no name ends in both.
 *
 * Names are restricted to lower case on purpose. A refused name is an
 * inconvenience; an accepted one is a database that gets wiped.
 */
final class TestDatabaseGuard
{
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_]*_test$/D';
    private const string LOG_NAME_PATTERN = '/^[a-z][a-z0-9_]*_test_log$/D';

    /**
     * @param array<string, string> $applicationDatabases keyed by the variable naming each
     *
     * @return list<string> one message per rule broken, empty when both are usable
     */
    public static function violations(string $name, string $logName, array $applicationDatabases): array
    {
        $violations = [];

        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            $violations[] = sprintf('TEST_DB_NAME "%s" is not a test database name (lower case, ending in "_test").', $name);
        }

        if (1 !== preg_match(self::LOG_NAME_PATTERN, $logName)) {
            $violations[] = sprintf('TEST_DB_LOG_NAME "%s" is not a test database name (lower case, ending in "_test_log").', $logName);
        }

        foreach ($applicationDatabases as $variable => $database) {
            if ($database === $name) {
                $violations[] = sprintf('TEST_DB_NAME "%s" is the database %s names.', $name, $variable);
            }

            if ($database === $logName) {
                $violations[] = sprintf('TEST_DB_LOG_NAME "%s" is the database %s names.', $logName, $variable);
            }
        }

        return $violations;
    }
}
