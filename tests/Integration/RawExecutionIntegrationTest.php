<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Core\DatabaseException;

final class RawExecutionIntegrationTest extends DatabaseIntegrationTestCase
{
    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_raw_ddl');
        }

        parent::tearDown();
    }

    public function testExecuteRawCreatesStoredProcedure(): void
    {
        $this->database->executeRaw(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_raw_ddl()
            BEGIN
                SELECT 1 AS raw_value;
            END
            SQL);

        $resultSets = $this->database->callProcedure('test_raw_ddl');

        self::assertSame([['raw_value' => 1]], $resultSets[0]);
    }

    public function testExecuteRawRejectsStackedStatements(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->executeRaw('SELECT 1; SELECT 2');
    }
}
