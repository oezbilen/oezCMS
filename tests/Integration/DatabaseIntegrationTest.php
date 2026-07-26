<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Core\Database;
use OezCMS\Core\DatabaseException;

final class DatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testConnectsAndReturnsNativeTypes(): void
    {
        $row = $this->database->fetchOne('SELECT 1 AS one');

        self::assertNotNull($row);
        self::assertSame(1, $row['one']);
    }

    public function testOnlyFullGroupByIsActive(): void
    {
        $row = $this->database->fetchOne('SELECT @@SESSION.sql_mode AS mode');

        self::assertNotNull($row);
        self::assertIsString($row['mode']);
        self::assertStringContainsString('ONLY_FULL_GROUP_BY', $row['mode']);
    }

    public function testRejectsStackedStatements(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->fetchAll('SELECT 1; SELECT 2');
    }

    public function testRejectsProcedureThatEndsTheCallersTransaction(): void
    {
        $this->pdo->exec('CREATE OR REPLACE PROCEDURE sp_test_commits() BEGIN START TRANSACTION; COMMIT; END');

        try {
            $this->database->transaction(static function (Database $database): void {
                $database->callProcedure('sp_test_commits');
            });

            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException $exception) {
            self::assertStringContainsString('committed the transaction', $exception->getMessage());
        } finally {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS sp_test_commits');
        }
    }

    public function testKeepsTheConnectionUsableAfterAProcedureEndsTheTransaction(): void
    {
        $this->pdo->exec('CREATE OR REPLACE PROCEDURE sp_test_commits() BEGIN START TRANSACTION; COMMIT; END');

        try {
            $this->database->transaction(static function (Database $database): void {
                $database->callProcedure('sp_test_commits');
            });
        } catch (DatabaseException) {
            // Expected; this test is about what the connection can do afterwards.
        } finally {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS sp_test_commits');
        }

        // A transaction that ended on the server is not a rollback failure: there
        // is nothing to roll back, and the connection itself is perfectly fine.
        self::assertSame([['one' => 1]], $this->database->fetchAll('SELECT 1 AS one'));
    }
}
