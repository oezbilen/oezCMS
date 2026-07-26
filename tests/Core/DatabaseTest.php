<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Database;
use OezCMS\Core\DatabaseException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private PDO $pdo;
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

        $this->database = new Database($this->pdo);
    }

    public function testFetchAllReturnsAllMatchingRows(): void
    {
        $rows = $this->database->fetchAll('SELECT id, name FROM users ORDER BY id');

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    public function testFetchOneReturnsSingleRow(): void
    {
        $row = $this->database->fetchOne(
            'SELECT id, name FROM users WHERE name = :name',
            ['name' => 'Alice'],
        );

        self::assertNotNull($row);
        self::assertSame('Alice', $row['name']);
    }

    public function testFetchOneReturnsNullWhenNoRowMatches(): void
    {
        $row = $this->database->fetchOne(
            'SELECT id, name FROM users WHERE name = :name',
            ['name' => 'Nobody'],
        );

        self::assertNull($row);
    }

    public function testExecuteInsertsRowAndReturnsAffectedCount(): void
    {
        $affected = $this->database->execute(
            'INSERT INTO users (id, name) VALUES (:id, :name)',
            ['id' => 3, 'name' => 'Charlie'],
        );

        self::assertSame(1, $affected);
        self::assertNotNull(
            $this->database->fetchOne('SELECT id FROM users WHERE name = :name', ['name' => 'Charlie']),
        );
    }

    public function testExecuteDeletesMatchingRowsAndReturnsAffectedCount(): void
    {
        $affected = $this->database->execute('DELETE FROM users WHERE id = :id', ['id' => 2]);

        self::assertSame(1, $affected);
        self::assertCount(1, $this->database->fetchAll('SELECT id FROM users'));
    }

    public function testWrapsPdoErrorsInDatabaseException(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->fetchAll('SELECT id FROM non_existing_table');
    }

    public function testKeepsOriginalPdoExceptionAsPrevious(): void
    {
        try {
            $this->database->fetchAll('SELECT id FROM non_existing_table');
            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException $exception) {
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
        }
    }

    public function testTransactionCommitsOnSuccessAndReturnsResult(): void
    {
        $result = $this->database->transaction(static function (Database $database): int {
            return $database->execute(
                'INSERT INTO users (id, name) VALUES (:id, :name)',
                ['id' => 3, 'name' => 'Charlie'],
            );
        });

        self::assertSame(1, $result);
        self::assertNotNull(
            $this->database->fetchOne('SELECT id FROM users WHERE name = :name', ['name' => 'Charlie']),
        );
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->database->transaction(static function (Database $database): void {
                $database->execute(
                    'INSERT INTO users (id, name) VALUES (:id, :name)',
                    ['id' => 3, 'name' => 'Charlie'],
                );

                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertNull(
            $this->database->fetchOne('SELECT id FROM users WHERE name = :name', ['name' => 'Charlie']),
        );
    }

    public function testLastInsertIdReturnsIdOfInsertedRow(): void
    {
        $this->database->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'Charlie']);

        self::assertSame('3', $this->database->lastInsertId());
    }

    public function testNestedTransactionCommitsWithOuter(): void
    {
        $this->database->transaction(function (Database $outer): void {
            $outer->execute('INSERT INTO users (id, name) VALUES (:id, :name)', ['id' => 3, 'name' => 'Charlie']);

            $outer->transaction(static function (Database $inner): void {
                $inner->execute('INSERT INTO users (id, name) VALUES (:id, :name)', ['id' => 4, 'name' => 'Dave']);
            });
        });

        self::assertCount(4, $this->database->fetchAll('SELECT id FROM users'));
    }

    public function testNestedTransactionRollsBackOnlyInnerChanges(): void
    {
        $this->database->transaction(function (Database $outer): void {
            $outer->execute('INSERT INTO users (id, name) VALUES (:id, :name)', ['id' => 3, 'name' => 'Charlie']);

            try {
                $outer->transaction(static function (Database $inner): void {
                    $inner->execute('INSERT INTO users (id, name) VALUES (:id, :name)', ['id' => 4, 'name' => 'Dave']);

                    throw new \RuntimeException('inner boom');
                });
            } catch (\RuntimeException $exception) {
                self::assertSame('inner boom', $exception->getMessage());
            }
        });

        self::assertNotNull(
            $this->database->fetchOne('SELECT id FROM users WHERE name = :name', ['name' => 'Charlie']),
        );
        self::assertNull(
            $this->database->fetchOne('SELECT id FROM users WHERE name = :name', ['name' => 'Dave']),
        );
    }

    public function testWrapsTransactionControlErrorsInDatabaseException(): void
    {
        $this->pdo->beginTransaction();

        $this->expectException(DatabaseException::class);

        $this->database->transaction(static fn (): bool => true);
    }

    public function testKeepsOriginalPdoExceptionAsPreviousForControlErrors(): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->database->transaction(static fn (): bool => true);
            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException $exception) {
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
        }
    }

    public function testAttachesSqlAndParametersToQueryErrors(): void
    {
        try {
            $this->database->fetchAll('SELECT id FROM non_existing_table WHERE id = :id', ['id' => 1]);
            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException $exception) {
            self::assertSame('SELECT id FROM non_existing_table WHERE id = :id', $exception->getSql());
            self::assertSame(['id' => 1], $exception->getParameters());
        }
    }

    public function testFailedQueryKeepsParameterValuesOutOfMessage(): void
    {
        try {
            $this->database->fetchAll(
                'SELECT id FROM users WHERE secret = :secret',
                ['secret' => 'super-secret-token'],
            );
            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException $exception) {
            self::assertStringNotContainsString('super-secret-token', $exception->getMessage());
            self::assertSame('SELECT id FROM users WHERE secret = :secret', $exception->getSql());
            self::assertSame(['secret' => 'super-secret-token'], $exception->getParameters());
        }
    }

    public function testCallProcedureRejectsInvalidProcedureName(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->callProcedure('users; DROP TABLE users');
    }

    public function testCallProcedureRejectsInvalidParameterName(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->callProcedure('valid_name', ['bad) OR (1' => 1]);
    }

    public function testCallProcedureRejectsOverlongProcedureName(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->callProcedure(str_repeat('a', 65));
    }

    public function testExecuteRawRunsDdlStatement(): void
    {
        $this->database->executeRaw('CREATE TABLE raw_table (id INTEGER PRIMARY KEY, label TEXT)');

        $this->database->execute('INSERT INTO raw_table (id, label) VALUES (:id, :label)', ['id' => 1, 'label' => 'raw']);

        self::assertNotNull($this->database->fetchOne('SELECT id FROM raw_table WHERE id = :id', ['id' => 1]));
    }

    public function testExecuteRawReturnsAffectedRowCount(): void
    {
        self::assertSame(2, $this->database->executeRaw('DELETE FROM users'));
    }

    public function testExecuteRawWrapsPdoErrorsInDatabaseException(): void
    {
        try {
            $this->database->executeRaw('NOT VALID SQL');
            self::fail('Expected DatabaseException was not thrown.');
        } catch (DatabaseException $exception) {
            self::assertSame('NOT VALID SQL', $exception->getSql());
            self::assertSame([], $exception->getParameters());
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
        }
    }

    public function testFailedRollbackKeepsTheOriginalException(): void
    {
        $database = new Database($this->pdoRefusingRollback());

        try {
            $database->transaction(static function (): void {
                throw new RuntimeException('callback failed');
            });
        } catch (RuntimeException $exception) {
            // The cleanup failure must never replace the failure that caused it:
            // the caller needs to see why the transaction was abandoned.
            self::assertSame('callback failed', $exception->getMessage());
        }
    }

    public function testFailedRollbackMakesTheConnectionUnusable(): void
    {
        $database = new Database($this->pdoRefusingRollback());

        try {
            $database->transaction(static function (): void {
                throw new RuntimeException('callback failed');
            });
        } catch (RuntimeException) {
            // Expected; the rollback failure is what this test is about.
        }

        // A connection whose rollback failed may still be in a transaction and
        // its savepoint state is unknown, so continuing to use it is the actual
        // danger. The swallowed error surfaces at the next access instead.
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/rollback refused/');

        $database->fetchAll('SELECT 1');
    }

    public function testRejectsTransactionWhenConnectionAlreadyHasOne(): void
    {
        $this->pdo->beginTransaction();

        // PDO already refuses this, but with a message that does not say which
        // rule was broken. Matching on the rule keeps this test honest: the
        // driver's own wording cannot satisfy it.
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/must go through Database::transaction/');

        $this->database->transaction(static fn (): int => 1);
    }

    private function pdoRefusingRollback(): PDO
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function rollBack(): bool
            {
                throw new PDOException('rollback refused');
            }
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
