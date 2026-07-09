<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

        $this->database = new Database($pdo);
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
}
