<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\DatabaseException;
use PHPUnit\Framework\TestCase;

final class DatabaseExceptionTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $this->expectException(DatabaseException::class);

        throw new DatabaseException('database error');
    }

    public function testCarriesSqlAndParameters(): void
    {
        $exception = new DatabaseException(
            message: 'query failed',
            sql: 'SELECT id FROM users WHERE id = :id',
            parameters: ['id' => 7],
        );

        self::assertSame('SELECT id FROM users WHERE id = :id', $exception->getSql());
        self::assertSame(['id' => 7], $exception->getParameters());
    }

    public function testDoesNotExposeParameterValuesInMessage(): void
    {
        $exception = new DatabaseException(
            message: 'Database query failed: syntax error',
            sql: 'SELECT * FROM users WHERE token = :token',
            parameters: ['token' => 'super-secret-token'],
        );

        self::assertStringNotContainsString('super-secret-token', $exception->getMessage());
        self::assertSame(['token' => 'super-secret-token'], $exception->getParameters());
    }
}
