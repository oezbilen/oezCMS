<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Database;
use OezCMS\Core\DatabaseException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

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
}
