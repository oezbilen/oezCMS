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

    public function testCarriesSqlAndParameterNames(): void
    {
        $exception = new DatabaseException(
            message: 'query failed',
            sql: 'SELECT id FROM users WHERE id = :id',
            parameterNames: ['id'],
        );

        self::assertSame('SELECT id FROM users WHERE id = :id', $exception->getSql());
        self::assertSame(['id'], $exception->getParameterNames());
    }
}
