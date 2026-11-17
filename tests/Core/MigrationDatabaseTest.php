<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Database;
use OezCMS\Core\DatabaseException;
use OezCMS\Core\MigrationDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationDatabaseTest extends TestCase
{
    public function testKeepsTheLogInTheApplicationDatabaseByDefault(): void
    {
        self::assertSame('oezcms_migrations', (new MigrationDatabase($this->database()))->logTable());
    }

    public function testQualifiesTheLogWithTheLogDatabase(): void
    {
        $migrationDatabase = new MigrationDatabase($this->database(), 'oezcms_log');

        self::assertSame('`oezcms_log`.oezcms_migrations', $migrationDatabase->logTable());
    }

    public function testRejectsALogDatabaseNameOutsideTheIdentifierAllowList(): void
    {
        // The name is placed into SQL text, so it has to pass the same
        // allow-list as every other identifier that is.
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/log database/');

        $migrationDatabase = new MigrationDatabase($this->database(), 'oezcms`; DROP DATABASE oezcms; --');

        self::fail(sprintf('Accepted %s as the deploy log.', $migrationDatabase->logTable()));
    }

    private function database(): Database
    {
        return new Database(new PDO('sqlite::memory:'));
    }
}
