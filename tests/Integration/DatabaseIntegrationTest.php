<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

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
}
