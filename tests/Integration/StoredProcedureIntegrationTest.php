<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

final class StoredProcedureIntegrationTest extends DatabaseIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_single_result()
            BEGIN
                SELECT 1 AS first_value;
            END
            SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_multiple_results()
            BEGIN
                SELECT 1 AS first_value;
                SELECT 2 AS second_value;
            END
            SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_echo(IN input_value INT)
            BEGIN
                SELECT input_value AS echoed;
            END
            SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_empty_result_set()
            BEGIN
                SELECT 1 AS first_value WHERE FALSE;
            END
            SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_trailing_empty_result()
            BEGIN
                SELECT 1 AS first_value;
                SELECT 2 AS second_value WHERE FALSE;
            END
            SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE OR REPLACE PROCEDURE test_no_result()
            BEGIN
                SET @test_no_result_ran = 1;
            END
            SQL);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_single_result');
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_multiple_results');
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_echo');
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_empty_result_set');
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_trailing_empty_result');
            $this->pdo->exec('DROP PROCEDURE IF EXISTS test_no_result');
        }

        parent::tearDown();
    }

    public function testReturnsSingleResultSet(): void
    {
        $resultSets = $this->database->callProcedure('test_single_result');

        self::assertCount(1, $resultSets);
        self::assertSame([['first_value' => 1]], $resultSets[0]);
    }

    public function testReturnsAllResultSetsInOrder(): void
    {
        $resultSets = $this->database->callProcedure('test_multiple_results');

        self::assertCount(2, $resultSets);
        self::assertSame([['first_value' => 1]], $resultSets[0]);
        self::assertSame([['second_value' => 2]], $resultSets[1]);
    }

    public function testPassesParametersToProcedure(): void
    {
        $resultSets = $this->database->callProcedure('test_echo', ['input_value' => 41]);

        self::assertSame([['echoed' => 41]], $resultSets[0]);
    }

    public function testConnectionRemainsUsableAfterProcedureCall(): void
    {
        $this->database->callProcedure('test_multiple_results');

        self::assertNotNull($this->database->fetchOne('SELECT 1 AS one'));
    }

    public function testCallProcedurePreservesEmptyResultSet(): void
    {
        $resultSets = $this->database->callProcedure('test_empty_result_set');

        self::assertSame([], $resultSets[0]);
    }

    public function testKeepsLegitimatelyEmptyTrailingResultSet(): void
    {
        $resultSets = $this->database->callProcedure('test_trailing_empty_result');

        self::assertCount(2, $resultSets);
        self::assertSame([['first_value' => 1]], $resultSets[0]);
        self::assertSame([], $resultSets[1]);
    }

    public function testReturnsNoResultSetsForProcedureWithoutSelect(): void
    {
        // The boundary case of removing the completion packet by position: here
        // it is the only row set the driver produces. A call that did not run
        // would raise rather than return an empty list.
        self::assertSame([], $this->database->callProcedure('test_no_result'));
    }
}
